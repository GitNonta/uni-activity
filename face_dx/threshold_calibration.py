#!/usr/bin/env python3
"""
threshold_calibration.py — validate the 0.40 fdx/mbf match threshold at scale.

Uses REAL CelebA identity labels (identity_CelebA.txt, 10,177 identities) and
the REAL server contract: 5-point landmark norm_crop 112x112 -> fdx engine
(w600k_mbf.fvp fp16, same weights as the server's insightface fallback).

Protocol (mirrors production enrollment/verification):
  * 50 identities x up to 12 images each (deterministic seed)
  * image 0 = "enrolled profile photo" (its vector is the stored embedding)
  * images 1.. = "selfie verification attempts" (same-person scores)
  * one image of every OTHER identity acts as an impostor attempt (diff scores)
  * reports: score distributions, ROC/AUC, TAR@FAR 1e-1/1e-2/1e-3, d-prime,
    optimal (Youden J) threshold, and the verdict for the 0.40 default

Usage:
  python threshold_calibration.py                 # default 50 ids x 12 imgs
  python threshold_calibration.py --identities 100 --per-id 8
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


def l2(v: np.ndarray) -> np.ndarray:
    return v / max(float(np.linalg.norm(v)), 1e-12)


def cos(a: np.ndarray, b: np.ndarray) -> float:
    return float(np.dot(a, b))


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


def d_prime(pos: np.ndarray, neg: np.ndarray) -> float:
    m_p, m_n = float(np.mean(pos)), float(np.mean(neg))
    s_p, s_n = float(np.std(pos)), float(np.std(neg))
    pooled = math_sqrt(0.5 * (s_p * s_p + s_n * s_n))
    return (m_p - m_n) / pooled if pooled > 0 else float("nan")


def math_sqrt(x: float) -> float:
    return x ** 0.5


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--identities", type=int, default=50)
    ap.add_argument("--per-id", type=int, default=12)
    ap.add_argument("--seed", type=int, default=0)
    ap.add_argument("--out", default=REPORT_PATH)
    args = ap.parse_args()

    # ── identity map ────────────────────────────────────────────────────
    by_ident: dict[int, list[str]] = defaultdict(list)
    for line in open(IDENTITY_TXT, encoding="utf-8"):
        parts = line.split()
        if len(parts) != 2:
            continue
        name, ident = parts
        by_ident[int(ident)].append(name)

    rng = random.Random(args.seed)
    eligible = sorted(i for i, files in by_ident.items() if len(files) >= args.per_id)
    chosen = rng.sample(eligible, args.identities)
    plan = {i: sorted(by_ident[i])[:args.per_id] for i in chosen}
    print(f"[plan] {len(plan)} identities x <= {args.per_id} images "
          f"(from {len(eligible)} eligible with >= {args.per_id})")

    # ── load the fdx engine (production embedder) ───────────────────────
    import fdx
    fx = fdx.FaceEmbedder()
    print(f"[fdx] {fx.describe()}")

    # ── embed everything through the server contract ────────────────────
    # norm_crop is done with the detector's 5-point landmarks, exactly like
    # server.py prepare_fdx_crop(). The detector only runs once per image.
    from insightface.app import FaceAnalysis
    app = FaceAnalysis(name="buffalo_l", allowed_modules=["detection"],
                       providers=["CPUExecutionProvider"])
    app.prepare(ctx_id=-1, det_size=(640, 640), det_thresh=0.5)
    from insightface.utils import face_align

    z = zipfile.ZipFile(ZIP_PATH)
    embs: dict[str, np.ndarray] = {}
    labels: dict[str, int] = {}
    n_failed = 0
    for k, (ident, files) in enumerate(sorted(plan.items())):
        for fname in files:
            key = f"{ident}/{fname}"
            raw = z.read(f"img_align_celeba/{fname}")
            img = cv2.imdecode(np.frombuffer(raw, np.uint8), cv2.IMREAD_COLOR)
            faces = app.get(img)
            if not faces:
                n_failed += 1
                continue
            f = max(faces, key=lambda x: x.det_score)
            if f.kps is None or len(f.kps) != 5:
                n_failed += 1
                continue
            crop = face_align.norm_crop(img, landmark=f.kps, image_size=112)
            rgb = np.ascontiguousarray(crop[:, :, ::-1])
            embs[key] = fx.embed(rgb)
            labels[key] = ident
        if (k + 1) % 10 == 0:
            print(f"  ... {k + 1}/{len(plan)} identities embedded "
                  f"({len(embs)} vectors, {n_failed} skipped)")
    fx.close()

    # ── pair construction: enrollment = first image per identity ────────
    same: list[float] = []
    diff: list[float] = []
    idents_sorted = sorted(plan.keys())
    first_key = {i: f"{i}/{plan[i][0]}" for i in idents_sorted}
    first_key = {i: k for i, k in first_key.items() if k in embs}
    valid_idents = sorted(first_key.keys())

    for i in valid_idents:
        e_enroll = embs[first_key[i]]
        # same-person: enrolled vs every other image of the same identity
        for fname in plan[i][1:]:
            key = f"{i}/{fname}"
            if key in embs:
                same.append(cos(e_enroll, embs[key]))
        # impostor: enrolled vs the FIRST image of every other identity
        for j in valid_idents:
            if j == i:
                continue
            diff.append(cos(e_enroll, embs[first_key[j]]))

    same_a, diff_a = np.array(same), np.array(diff)
    auc = auc_mann_whitney(same_a, diff_a)
    dp = d_prime(same_a, diff_a)
    tar1 = tar_at_far(same_a, diff_a, 1e-1)
    tar2 = tar_at_far(same_a, diff_a, 1e-2)
    tar3 = tar_at_far(same_a, diff_a, 1e-3)

    # optimal threshold (Youden J on the pooled score distribution)
    all_s = np.concatenate([same_a, diff_a])
    best_thr, best_j = 0.0, -1.0
    for thr in np.quantile(all_s, np.linspace(0.01, 0.99, 197)):
        tpr = float(np.mean(same_a > thr))
        fpr = float(np.mean(diff_a > thr))
        j = tpr - fpr
        if j > best_j:
            best_j, best_thr = j, float(thr)

    DEFAULT = 0.40
    far_at_default = float(np.mean(diff_a >= DEFAULT))
    frr_at_default = float(np.mean(same_a < DEFAULT))
    accuracy = float((np.sum(same_a >= DEFAULT) + np.sum(diff_a < DEFAULT))
                     / (len(same_a) + len(diff_a)))

    # exact sweep at candidate operating points
    sweep = {}
    for thr in [0.15, 0.20, 0.25, 0.30, 0.35, 0.40, 0.45, 0.50]:
        sweep[f"{thr:.2f}"] = {
            "far": float(np.mean(diff_a >= thr)),
            "frr": float(np.mean(same_a < thr)),
        }

    def stats(a: np.ndarray) -> dict:
        return {"n": int(a.size), "mean": float(np.mean(a)), "std": float(np.std(a)),
                "min": float(np.min(a)), "p5": float(np.quantile(a, 0.05)),
                "p50": float(np.quantile(a, 0.5)), "p95": float(np.quantile(a, 0.95)),
                "max": float(np.max(a))}

    report = {
        "meta": {
            "identities": len(valid_idents),
            "images_per_identity": args.per_id,
            "seed": args.seed,
            "embedder": "fdx-d3d11 w600k_mbf.fvp fp16",
            "alignment": "scrfd 5-point kps -> norm_crop 112x112 (server contract)",
            "identity_source": "identity_CelebA.txt (ground-truth labels)",
            "skipped_images": n_failed,
        },
        "same_person_scores": stats(same_a),
        "diff_person_scores": stats(diff_a),
        "auc": auc,
        "d_prime": dp,
        "tar_at_far": {"1e-1": tar1, "1e-2": tar2, "1e-3": tar3},
        "optimal_threshold": {"youden_j": best_j, "threshold": best_thr},
        "threshold_sweep": sweep,
        "default_0.40_check": {
            "far": far_at_default,
            "frr": frr_at_default,
            "accuracy": accuracy,
            "verdict": "PASS" if (far_at_default <= 0.02 and frr_at_default <= 0.10) else "REVIEW",
        },
    }

    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    with open(args.out, "w", encoding="utf-8") as fh:
        json.dump(report, fh, indent=2)

    print("\n==== results ====")
    print(f"same-person : n={len(same_a)}  mean={same_a.mean():.4f}  p5={np.quantile(same_a, 0.05):.4f}  min={same_a.min():.4f}")
    print(f"diff-person : n={len(diff_a)}  mean={diff_a.mean():.4f}  p95={np.quantile(diff_a, 0.95):.4f}  max={diff_a.max():.4f}")
    print(f"AUC={auc:.6f}  d-prime={dp:.3f}")
    print(f"TAR@FAR 1e-1={tar1:.4f}  1e-2={tar2:.4f}  1e-3={tar3:.4f}")
    print(f"Youden-J optimal threshold={best_thr:.4f} (J={best_j:.4f})")
    print("threshold sweep (exact):")
    for thr, v in sweep.items():
        print(f"  thr={thr}: FAR={v['far']:.5f}  FRR={v['frr']:.4f}")
    print(f"DEFAULT 0.40: FAR={far_at_default:.5f}  FRR={frr_at_default:.4f}  accuracy={accuracy:.4f}  "
          f"-> {report['default_0.40_check']['verdict']}")
    print(f"report -> {args.out}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
