#!/usr/bin/env python3
"""
threshold_calibration.py — validate fdx/mbf match-threshold bands at scale.

Uses REAL CelebA identity labels (identity_CelebA.txt, 10,177 identities) and
the REAL server contract: 5-point landmark norm_crop 112x112 -> fdx engine
(w600k_mbf.fvp fp16, same weights as the server's insightface fallback).

Enrollment protocols compared (all "enroll once, verify many", like prod):
  * single   : first enrollment image = stored embedding (old behavior)
  * max      : attempt score = max cosine vs each of the N enrollment images
  * centroid : attempt score = cosine vs the L2-normalized mean of the N
               enrollment vectors (one stored vector; cheap + robust)

Usage:
  python threshold_calibration.py                       # 500 ids x 12, enroll 5
  python threshold_calibration.py --identities 200 --enroll 0,1,2
"""
from __future__ import annotations

import argparse
import json
import os
import random
import sys
import zipfile
from collections import defaultdict

import cv2
import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "pylib"))  # repo fdx fallback

ZIP_PATH = os.path.abspath(os.path.join(HERE, "..", "face_cpp", "img_align_celeba.zip"))
IDENTITY_TXT = os.path.join(HERE, "identity_CelebA.txt")
REPORT_PATH = os.path.join(HERE, "reports", "threshold_calibration.json")
CACHE_PATH = os.path.join(HERE, "reports", "calib_embed_cache.npz")
CACHE_VERSION = "v3"  # bump to invalidate stale caches

THRESHOLDS = [0.10, 0.15, 0.20, 0.25, 0.30, 0.35, 0.40, 0.45, 0.50]
DEFAULT_THR = 0.40


def parse_idx_list(s: str) -> list[int]:
    out = [int(x) for x in s.split(",") if x != ""]
    if not out:
        raise argparse.ArgumentTypeError("need at least one enrollment index")
    return sorted(out)


# ── score functions per enrollment protocol ──────────────────────────────
# E: (sum of per-id enrollment counts, D); P: (n_probe, D);
# bounds: (n_id, 2) start/stop rows of each identity's enrollment block.


def _score_single(E: np.ndarray, P: np.ndarray, bounds: np.ndarray) -> np.ndarray:
    C = E[bounds[:, 0]]                      # first enrollment image per id
    return P @ C.T


def _score_centroid(E: np.ndarray, P: np.ndarray, bounds: np.ndarray) -> np.ndarray:
    C = np.stack([E[s:e].mean(axis=0) for s, e in bounds])
    C = C / np.maximum(np.linalg.norm(C, axis=1, keepdims=True), 1e-12)
    return P @ C.T


def _score_max(E: np.ndarray, P: np.ndarray, bounds: np.ndarray) -> np.ndarray:
    S = P @ E.T                               # (n_probe, n_enroll_total)
    out = np.empty((P.shape[0], bounds.shape[0]), dtype=np.float64)
    for i, (s, e) in enumerate(bounds):
        out[:, i] = S[:, s:e].max(axis=1)
    return out


PROTOCOLS = {"single": _score_single, "max": _score_max, "centroid": _score_centroid}


# ── metrics ───────────────────────────────────────────────────────────────

def auc_mann_whitney(pos: np.ndarray, neg: np.ndarray) -> float:
    """Tie-aware AUC = P(score_pos > score_neg) + 0.5 P(equal)."""
    if len(pos) == 0 or len(neg) == 0:
        return float("nan")
    all_s = np.concatenate([pos, neg])
    order = np.argsort(all_s, kind="mergesort")
    ranks = np.empty_like(order, dtype=np.float64)
    sorted_s = all_s[order]
    i = 0
    while i < len(sorted_s):
        j = i
        while j + 1 < len(sorted_s) and sorted_s[j + 1] == sorted_s[i]:
            j += 1
        ranks[order[i:j + 1]] = 0.5 * (i + j) + 1.0
        i = j + 1
    r_pos = ranks[:len(pos)].sum()
    return float((r_pos - len(pos) * (len(pos) + 1) / 2.0) / (len(pos) * len(neg)))


def tar_at_far(pos: np.ndarray, neg: np.ndarray, far: float) -> float:
    """True-accept rate when the threshold is set so that FAR <= far."""
    if len(neg) == 0:
        return float("nan")
    neg_sorted = np.sort(neg)
    k = int(np.floor(far * len(neg_sorted)))          # allowed false accepts
    thr = neg_sorted[len(neg_sorted) - 1 - k] if k < len(neg_sorted) else neg_sorted[0]
    return float(np.mean(pos > thr))


def threshold_for_far(neg: np.ndarray, far: float) -> tuple[float, float]:
    """Highest threshold with FAR(neg >= thr) <= far; returns (thr, achieved_far)."""
    if len(neg) == 0:
        return float("nan"), float("nan")
    neg_sorted = np.sort(neg)                          # ascending
    k = int(np.floor(far * len(neg_sorted)))           # allowed false accepts
    if k <= 0:
        thr = float(neg_sorted[-1]) + 1e-6
    else:
        thr = float(neg_sorted[len(neg_sorted) - k])   # k-th largest value
    achieved = float(np.mean(neg >= thr))
    return thr, achieved


def d_prime(pos: np.ndarray, neg: np.ndarray) -> float:
    m_p, m_n = float(np.mean(pos)), float(np.mean(neg))
    s_p, s_n = float(np.std(pos)), float(np.std(neg))
    pooled = (0.5 * (s_p * s_p + s_n * s_n)) ** 0.5
    return (m_p - m_n) / pooled if pooled > 0 else float("nan")


def stats(a: np.ndarray) -> dict:
    return {"n": int(a.size), "mean": float(np.mean(a)), "std": float(np.std(a)),
            "min": float(np.min(a)), "p5": float(np.quantile(a, 0.05)),
            "p50": float(np.quantile(a, 0.5)), "p95": float(np.quantile(a, 0.95)),
            "max": float(np.max(a))}


def l2(v: np.ndarray) -> np.ndarray:
    return v / max(float(np.linalg.norm(v)), 1e-12)


# ── main ──────────────────────────────────────────────────────────────────

def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--identities", type=int, default=500)
    ap.add_argument("--per-id", type=int, default=12)
    ap.add_argument("--seed", type=int, default=0)
    ap.add_argument("--enroll", type=parse_idx_list, default="0,1,2,3,4",
                    help="comma list of image indices used for enrollment")
    ap.add_argument("--protocols", default="single,max,centroid")
    ap.add_argument("--recommend", default="centroid", choices=["single", "max", "centroid"])
    ap.add_argument("--fast-det", action="store_true",
                    help="det_size=320 fast path; escalate to 640 when risky "
                         "(multi-face or det_score < --det-min-score)")
    ap.add_argument("--det-min-score", type=float, default=0.7)
    ap.add_argument("--out", default=REPORT_PATH)
    args = ap.parse_args()
    protocols = [p.strip() for p in args.protocols.split(",") if p.strip()]
    for p in protocols:
        if p not in PROTOCOLS:
            ap.error(f"unknown protocol {p!r}; choose from {sorted(PROTOCOLS)}")

    # ── identity map ────────────────────────────────────────────────────
    by_ident: dict[int, list[str]] = defaultdict(list)
    for line in open(IDENTITY_TXT, encoding="utf-8"):
        parts = line.split()
        if len(parts) != 2:
            continue
        name, ident = parts
        by_ident[int(ident)].append(name)

    rng = random.Random(args.seed)
    need = max(args.per_id, max(args.enroll) + 1)
    eligible = sorted(i for i, files in by_ident.items() if len(files) >= need)
    chosen = rng.sample(eligible, args.identities)
    plan = {i: sorted(by_ident[i])[:args.per_id] for i in chosen}
    print(f"[plan] {len(plan)} identities x <= {args.per_id} images, "
          f"enroll idx {args.enroll} (from {len(eligible)} eligible)", flush=True)

    # ── load the fdx engine (production embedder) ───────────────────────
    import fdx
    fx = fdx.FaceEmbedder()
    print(f"[fdx] {fx.describe()}", flush=True)

    # ── embed through the server contract (with embedding cache) ────────
    # Production runs SCRFD at det_size 640. --fast-det runs 320 (4x faster on
    # CPU) and escalates to 640 whenever the pick could be ambiguous (multiple
    # faces or low confidence), so every ambiguous image gets the exact
    # production-quality landmarks.
    from insightface.app import FaceAnalysis

    def _det_app(size: int):
        a = FaceAnalysis(name="buffalo_l", allowed_modules=["detection"],
                         providers=["CPUExecutionProvider"])
        a.prepare(ctx_id=-1, det_size=(size, size), det_thresh=0.5)
        return a

    app640 = _det_app(640)
    app320 = _det_app(320) if args.fast_det else None

    def detect_best(img):
        """Pick the face exactly like production (max det_score); 640 kps
        whenever ambiguity exists or --fast-det is off."""
        f320 = app320.get(img) if app320 is not None else None
        risky = (not f320 or len(f320) > 1
                 or max(f320, key=lambda x: x.det_score).det_score < args.det_min_score)
        if not risky:
            return max(f320, key=lambda x: x.det_score)
        f640 = app640.get(img)
        if f640:
            return max(f640, key=lambda x: x.det_score)
        return max(f320, key=lambda x: x.det_score) if f320 else None

    from insightface.utils import face_align

    cache: dict[str, np.ndarray] = {}
    if os.path.exists(CACHE_PATH):
        try:
            with np.load(CACHE_PATH, allow_pickle=False) as z:
                if str(z["version"]) == CACHE_VERSION:
                    cache = {k: v for k, v in z.items() if k != "version"}
        except Exception as e:  # corrupt cache -> recompute
            print(f"[cache] ignoring stale cache ({e})")
        if cache:
            print(f"[cache] {len(cache)} vectors loaded", flush=True)

    z = zipfile.ZipFile(ZIP_PATH)
    embs: dict[str, np.ndarray] = {}
    labels: dict[str, int] = {}
    n_failed = 0
    persist = dict(cache)          # cache as last written to disk
    unsaved: list[str] = []        # keys computed since the last disk write

    def flush_cache() -> None:
        if not unsaved:
            return
        for k in unsaved:
            persist[k] = embs[k]
        unsaved.clear()
        os.makedirs(os.path.dirname(CACHE_PATH), exist_ok=True)
        np.savez_compressed(CACHE_PATH, version=CACHE_VERSION, **persist)

    for k, (ident, files) in enumerate(sorted(plan.items())):
        for fname in files:
            key = f"{ident}/{fname}"
            if key in cache:
                embs[key] = cache[key]
                labels[key] = ident
                continue
            raw = z.read(f"img_align_celeba/{fname}")
            img = cv2.imdecode(np.frombuffer(raw, np.uint8), cv2.IMREAD_COLOR)
            f = detect_best(img)
            if f is None:
                n_failed += 1
                continue
            if f.kps is None or len(f.kps) != 5:
                n_failed += 1
                continue
            crop = face_align.norm_crop(img, landmark=f.kps, image_size=112)
            rgb = np.ascontiguousarray(crop[:, :, ::-1])
            embs[key] = l2(fx.embed(rgb))
            unsaved.append(key)
            labels[key] = ident
        if (k + 1) % 50 == 0:
            flush_cache()
            print(f"  ... {k + 1}/{len(plan)} identities "
                  f"({len(embs)} vectors, {n_failed} skipped)", flush=True)
    flush_cache()
    fx.close()

    if len(persist) > len(cache):
        print(f"[cache] {len(persist)} vectors on disk", flush=True)

    # ── per-identity vector groups ───────────────────────────────────────
    idents = sorted({lab for lab in labels.values()})
    groups: dict[int, list[np.ndarray]] = {}
    for key, lab in labels.items():
        groups.setdefault(lab, []).append(embs[key])
    # groups are already in deterministic (identity, filename) order

    # ── build enrollment matrix + probe pool ────────────────────────────
    # Keep only identities whose enrollment set is complete.
    used = [i for i in idents if all(e < len(groups[i]) for e in args.enroll)]
    E_rows: list[np.ndarray] = []
    bounds: list[list[int]] = []
    for i in used:
        s = len(E_rows)
        E_rows.extend(groups[i][e] for e in args.enroll)
        bounds.append([s, len(E_rows)])
    E = np.stack(E_rows)
    bounds_a = np.asarray(bounds, dtype=np.int64)

    P_rows: list[np.ndarray] = []
    P_ids: list[int] = []
    for i in used:
        for idx, v in enumerate(groups[i]):
            if idx not in args.enroll:
                P_rows.append(v)
                P_ids.append(i)
    P = np.stack(P_rows)
    P_ids_a = np.asarray(P_ids)
    print(f"[eval] {len(used)} ids, {E.shape[0]} enrollment vectors, "
          f"{P.shape[0]} probe images", flush=True)

    same_mask = P_ids_a[:, None] == np.asarray(used)[None, :]   # (n_probe, n_id)

    # ── evaluate each protocol ───────────────────────────────────────────
    out_protocols = {}
    raw_results = {}
    for name in protocols:
        scores = PROTOCOLS[name](E, P, bounds_a)     # (n_probe, n_id)
        pos = scores[same_mask]
        neg = scores[~same_mask]
        raw_results[name] = (pos, neg)

        auc = auc_mann_whitney(pos, neg)
        dp = d_prime(pos, neg)
        tar1 = tar_at_far(pos, neg, 1e-1)
        tar2 = tar_at_far(pos, neg, 1e-2)
        tar3 = tar_at_far(pos, neg, 1e-3)

        all_s = np.concatenate([pos, neg])
        best_thr, best_j = 0.0, -1.0
        for thr in np.quantile(all_s, np.linspace(0.01, 0.99, 197)):
            tpr = float(np.mean(pos > thr))
            fpr = float(np.mean(neg > thr))
            j = tpr - fpr
            if j > best_j:
                best_j, best_thr = j, float(thr)

        sweep = {}
        for thr in THRESHOLDS:
            sweep[f"{thr:.2f}"] = {
                "far": float(np.mean(neg >= thr)),
                "frr": float(np.mean(pos < thr)),
            }
        out_protocols[name] = {
            "n_same": int(pos.size), "n_diff": int(neg.size),
            "same_stats": stats(pos), "diff_stats": stats(neg),
            "auc": auc, "d_prime": dp,
            "tar_at_far": {"1e-1": tar1, "1e-2": tar2, "1e-3": tar3},
            "optimal_threshold": {"youden_j": best_j, "threshold": best_thr},
            "threshold_sweep": sweep,
        }

    # ── recommendation from the chosen protocol ──────────────────────────
    rec_pos, rec_neg = raw_results[args.recommend]
    rec_thresholds = {}
    for far_t in [0.001, 0.01, 0.05, 0.10]:
        thr, achieved = threshold_for_far(rec_neg, far_t)
        rec_thresholds[f"far<={far_t}"] = {
            "threshold": thr, "achieved_far": achieved,
            "tar": float(np.mean(rec_pos > thr)),
        }
    frr20 = next((t for t in THRESHOLDS
                  if out_protocols[args.recommend]["threshold_sweep"][f"{t:.2f}"]["frr"] <= 0.20),
                 None)
    far_at_default = float(np.mean(rec_neg >= DEFAULT_THR))
    frr_at_default = float(np.mean(rec_pos < DEFAULT_THR))

    report = {
        "meta": {
            "identities": len(used),
            "images_per_identity": args.per_id,
            "enroll_indices": args.enroll,
            "seed": args.seed,
            "embedder": "fdx-d3d11 w600k_mbf.fvp fp16",
            "alignment": ("scrfd 5-point kps -> norm_crop 112x112 (server contract, "
                          "det_size 640)" if not args.fast_det else
                          "scrfd 5-point kps -> norm_crop 112x112 (fast-det: "
                          "det_size 320, escalate to 640 if multi-face/low-conf)"),
            "identity_source": "identity_CelebA.txt (ground-truth labels)",
            "skipped_images": n_failed,
        },
        "protocols": out_protocols,
        "recommendation": {
            "protocol": args.recommend,
            "thresholds": rec_thresholds,
            "min_std_threshold_with_frr<=20%": frr20,
            f"default_{DEFAULT_THR:.2f}_check": {
                "far": far_at_default, "frr": frr_at_default,
            },
        },
    }
    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    with open(args.out, "w", encoding="utf-8") as fh:
        json.dump(report, fh, indent=2)

    # ── summary ──────────────────────────────────────────────────────────
    print(f"\n==== results ({len(used)} ids x {args.per_id} images, "
          f"enroll {args.enroll}) ====")
    for name, p in out_protocols.items():
        pos, neg = raw_results[name]
        print(f"\n-- {name}: same mean={pos.mean():.4f} p5={np.quantile(pos, 0.05):.4f} "
              f"min={pos.min():.4f} | diff mean={neg.mean():.4f} "
              f"p95={np.quantile(neg, 0.95):.4f} max={neg.max():.4f}")
        print(f"   AUC={p['auc']:.5f}  d'={p['d_prime']:.2f}  TAR@FAR "
              f"1e-1={p['tar_at_far']['1e-1']:.4f} 1e-2={p['tar_at_far']['1e-2']:.4f} "
              f"1e-3={p['tar_at_far']['1e-3']:.4f}")
        print(f"   Youden-J thr={p['optimal_threshold']['threshold']:.4f} "
              f"(J={p['optimal_threshold']['youden_j']:.4f})")
        for thr, v in p["threshold_sweep"].items():
            print(f"   thr={thr}: FAR={v['far']:.5f}  FRR={v['frr']:.4f}")
    print(f"\nrecommendation ({args.recommend}) -> {args.out}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
