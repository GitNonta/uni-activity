#!/usr/bin/env python3
"""
eval_custom_arcface.py — identity-separation evaluation for the custom ArcFace model.

Builds all same-person and different-person pairs from an identity-labeled
crops directory (same derivation as training: subfolders, or <person>_<n>
filename prefixes), embeds every image, scores pairs by cosine similarity,
and reports:

  * ROC curve (written to CSV) + AUC (Mann-Whitney, tie-aware)
  * accuracy at the optimal (Youden J) threshold
  * TAR @ FAR 1e-1 / 1e-2 / 1e-3
  * d-prime: normalized separation of the same/diff score distributions

Embedding backends:
  --ckpt  : PyTorch NativeArcFaceNet from a training checkpoint
  --fvp   : the exported model graph (numpy re-execution, identical math to
            what face_dx.exe runs on the iGPU; see check_custom_fvp.py)
Giving both additionally cross-checks the .fvp against PyTorch per image.

Usage:
  python eval_custom_arcface.py --ckpt models/custom_arcface.pt
  python eval_custom_arcface.py --fvp models/custom_arcface.fvp --csv roc.csv
"""
from __future__ import annotations

import argparse
import csv
import math
import os
import sys

import numpy as np
import torch

from check_custom_fvp import load_fvp, run_fvp
from train_custom_arcface import NativeArcFaceNet, SimpleFacesDataset


# ---------------------------------------------------------------------------
# embedding backends
# ---------------------------------------------------------------------------
def embed_torch(ckpt_path: str, tensors: torch.Tensor, batch: int = 32) -> np.ndarray:
    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    model = NativeArcFaceNet(embedding_dim=512).to(device)
    model.load_state_dict(torch.load(ckpt_path, map_location=device, weights_only=True))
    model.eval()
    out = []
    with torch.no_grad():
        for i in range(0, len(tensors), batch):
            x = tensors[i:i + batch].to(device)
            out.append(model(x, normalize=True).cpu())
    return torch.cat(out).numpy().astype(np.float32)


def embed_fvp(fvp_path: str, tensors: torch.Tensor) -> np.ndarray:
    layers = load_fvp(fvp_path)
    out = np.zeros((len(tensors), 512), np.float32)
    for i, t in enumerate(tensors):
        x = t.numpy().astype(np.float32)[None]  # [1,3,112,112]
        res, _ = run_fvp(layers, x)
        e = res[-1].reshape(-1)
        out[i] = e / max(np.linalg.norm(e), 1e-12)
    return out


# ---------------------------------------------------------------------------
# pair scoring + metrics
# ---------------------------------------------------------------------------
def pair_scores(embs: np.ndarray, labels: list[int]):
    """All i<j pairs -> (same_similarity_list, diff_similarity_list)."""
    same: list[float] = []
    diff: list[float] = []
    n = len(embs)
    for i in range(n):
        for j in range(i + 1, n):
            s = float(np.dot(embs[i], embs[j]))  # rows are L2-normalized
            (same if labels[i] == labels[j] else diff).append(s)
    return np.array(same), np.array(diff)


def auc_mann_whitney(pos: np.ndarray, neg: np.ndarray) -> float:
    """AUC = P(score_pos > score_neg) + 0.5 P(equal), tie-aware via ranks."""
    if len(pos) == 0 or len(neg) == 0:
        return float("nan")
    all_s = np.concatenate([pos, neg])
    order = np.argsort(all_s, kind="mergesort")
    ranks = np.empty_like(order, dtype=np.float64)
    sorted_s = all_s[order]
    i = 0
    while i < len(sorted_s):  # average ranks within tie groups
        j = i
        while j + 1 < len(sorted_s) and sorted_s[j + 1] == sorted_s[i]:
            j += 1
        ranks[order[i:j + 1]] = 0.5 * (i + j) + 1.0
        i = j + 1
    r_pos = ranks[:len(pos)].sum()
    return float((r_pos - len(pos) * (len(pos) + 1) / 2.0) / (len(pos) * len(neg)))


def roc_curve(pos: np.ndarray, neg: np.ndarray):
    """(thresholds, tpr, fpr) swept from +inf down to min score."""
    thresholds = np.concatenate([[np.inf], np.unique(np.concatenate([pos, neg]))[::-1]])
    tpr = [(pos >= t).mean() if len(pos) else float("nan") for t in thresholds]
    fpr = [(neg >= t).mean() if len(neg) else float("nan") for t in thresholds]
    return thresholds, np.array(tpr), np.array(fpr)


def tar_at_far(pos: np.ndarray, neg: np.ndarray, far: float) -> float:
    """Best TAR over thresholds whose FAR does not exceed `far`."""
    if len(pos) == 0 or len(neg) == 0:
        return float("nan")
    best = 0.0
    for t in np.unique(np.concatenate([pos, neg])):
        if (neg >= t).mean() <= far:
            best = max(best, float((pos >= t).mean()))
    return best


def d_prime(pos: np.ndarray, neg: np.ndarray) -> float:
    if len(pos) < 2 or len(neg) < 2:
        return float("nan")
    var = 0.5 * (pos.var(ddof=1) + neg.var(ddof=1))
    if var <= 0:
        return float("inf") if pos.mean() != neg.mean() else 0.0
    return float((pos.mean() - neg.mean()) / math.sqrt(var))


def write_roc_csv(path: str, thresholds, tpr, fpr) -> None:
    with open(path, "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["threshold", "tpr", "fpr"])
        for t, tp, fp in zip(thresholds, tpr, fpr):
            w.writerow([f"{t:.8g}", f"{tp:.6f}", f"{fp:.6f}"])


# ---------------------------------------------------------------------------
# main
# ---------------------------------------------------------------------------
def main() -> None:
    here = os.path.dirname(os.path.abspath(__file__))
    default_crops = os.path.normpath(os.path.join(here, "..", "face_cpp", "testdata", "crops"))

    ap = argparse.ArgumentParser(description="Evaluate same/different-person separation")
    ap.add_argument("--crops-dir", type=str, default=default_crops,
                    help="Identity-labeled crops (subfolders or <person>_<n>.png)")
    ap.add_argument("--ckpt", help="PyTorch checkpoint to evaluate")
    ap.add_argument("--fvp", help="Exported .fvp to evaluate (numpy graph, matches the GPU engine)")
    ap.add_argument("--csv", help="Optional output path for the ROC curve CSV")
    args = ap.parse_args()

    if not args.ckpt and not args.fvp:
        ap.error("give --ckpt and/or --fvp")

    ds = SimpleFacesDataset(crops_dir=args.crops_dir, augment=False)
    tensors = torch.stack(ds.tensors)
    labels = ds.labels
    n = len(ds)

    print(f"[eval] {n} images, {ds.num_classes} identities: "
          + ", ".join(f"{name}(x{labels.count(i)})" for i, name in enumerate(ds.class_names)))

    backends: dict[str, np.ndarray] = {}
    if args.ckpt:
        backends["torch"] = embed_torch(args.ckpt, tensors)
    if args.fvp:
        backends["fvp"] = embed_fvp(args.fvp, tensors)

    if len(backends) == 2:
        c = np.sum(backends["torch"] * backends["fvp"], axis=1)
        print(f"[eval] .fvp vs torch per-image cosine: min={c.min():.8f} mean={c.mean():.8f}")

    for tag, embs in backends.items():
        same, diff = pair_scores(embs, labels)
        n_same, n_diff = len(same), len(diff)
        if n_same == 0 or n_diff == 0:
            print(f"[{tag}] not enough pairs (same={n_same}, diff={n_diff})")
            continue

        auc = auc_mann_whitney(same, diff)
        dp = d_prime(same, diff)

        # optimal accuracy threshold (Youden J on the ROC sweep)
        thresholds, tpr, fpr = roc_curve(same, diff)
        j = tpr - fpr
        bi = int(np.nanargmax(j))
        thr = thresholds[bi]
        acc = ((same >= thr).sum() + (diff < thr).sum()) / (n_same + n_diff)

        print(f"\n[{tag}] pairs: same={n_same}  diff={n_diff}")
        print(f"  AUC                    = {auc:.4f}")
        print(f"  d-prime                = {dp:.3f}")
        print(f"  best threshold         = {thr:.6f}  (accuracy {acc:.3f}, "
              f"TAR {tpr[bi]:.3f} @ FAR {fpr[bi]:.3f})")
        for far in (1e-1, 1e-2, 1e-3):
            print(f"  TAR @ FAR {far:g}      = {tar_at_far(same, diff, far):.4f}")
        print(f"  same:  mean={same.mean():+.4f} min={same.min():+.4f} max={same.max():+.4f}")
        print(f"  diff:  mean={diff.mean():+.4f} min={diff.min():+.4f} max={diff.max():+.4f}")

        if n_same < 8 or n_diff < 8:
            print("  note: very few pairs — treat these numbers as smoke signals, "
                  "not statistics")
        if args.csv:
            out = args.csv if len(backends) == 1 else \
                args.csv.replace(".csv", f"_{tag}.csv")
            write_roc_csv(out, thresholds, tpr, fpr)
            print(f"  ROC curve written to {out}")


if __name__ == "__main__":
    sys.exit(main())
