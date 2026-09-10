#!/usr/bin/env python3
"""
gen_references.py — Python reference generator (runs on the Windows machine).

Uses the exact InsightFace buffalo_s components (SCRFD-500MF detection +
MobileFaceNet w600k_mbf recognition) loaded from local .onnx files — the same
model pack the C++ side converts to ncnn.

For each input face photo:
  1. Detect face + 5 keypoints (SCRFD)
  2. Produce the aligned 112x112 crop exactly as the recognition model sees it
     (norm_crop, arcface 5-point reference, warpAffine borderValue=0)
  3. Compute the L2-normalized 512-d embedding (normed_embedding)

Writes:
  testdata/crops/<id>.png    aligned 112x112 RGB crop
  testdata/references.json   [{id, src, crop, embedding(512 floats), norm}]
"""
from __future__ import annotations

import argparse
import json
import os
import sys

import cv2
import numpy as np

ROOT = os.path.dirname(os.path.abspath(__file__))
TESTDATA = os.path.join(ROOT, "testdata")
CROPS = os.path.join(TESTDATA, "crops")
MODELS = os.path.join(ROOT, "models", "buffalo_s")

DET_ONNX = os.path.join(MODELS, "det_500m.onnx")
REC_ONNX = os.path.join(MODELS, "w600k_mbf.onnx")


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--images", nargs="+", default=None,
                    help="input face photos (default: all images in testdata/)")
    ap.add_argument("--out", default=os.path.join(TESTDATA, "references.json"))
    args = ap.parse_args()

    if args.images is None:
        args.images = []
        for ext in ("jpg", "jpeg", "webp", "png"):
            args.images += sorted(
                os.path.join(TESTDATA, f)
                for f in os.listdir(TESTDATA) if f.endswith("." + ext)
            )
    args.images = [p for p in args.images if os.path.exists(p)]
    if not args.images:
        sys.exit("no input images found")

    from insightface.model_zoo import scrfd, arcface_onnx
    from insightface.utils import face_align

    det = scrfd.SCRFD(DET_ONNX)
    det.prepare(ctx_id=0, input_size=(640, 640), det_thresh=0.5)
    rec = arcface_onnx.ArcFaceONNX(REC_ONNX)
    rec.prepare(ctx_id=0)

    os.makedirs(CROPS, exist_ok=True)
    refs = []
    for img_path in args.images:
        img = cv2.imread(img_path)
        if img is None:
            print(f"!! cannot read {img_path}")
            continue
        bboxes, kpss = det.detect(img, input_size=(640, 640), max_num=1)
        if bboxes is None or len(bboxes) == 0 or kpss is None or len(kpss) == 0:
            print(f"!! no face in {img_path}")
            continue
        kps = kpss[0]

        class _Face:
            pass
        face = _Face()
        face.kps = kps

        crop = face_align.norm_crop(img, landmark=kps, image_size=112)
        emb = rec.get(img, face).flatten().astype(np.float32)
        n = float(np.linalg.norm(emb))
        emb_normed = (emb / n).astype(np.float32) if n > 0 else emb

        base = os.path.splitext(os.path.basename(img_path))[0]
        crop_path = os.path.join(CROPS, f"{base}.png")
        cv2.imwrite(crop_path, crop)
        refs.append({
            "id": base,
            "src": img_path,
            "crop": crop_path,
            "det_score": round(float(bboxes[0][4]), 4),
            "norm": round(n, 6),
            "embedding": [round(float(v), 8) for v in emb_normed],
        })
        print(f"OK {base}: det={bboxes[0][4]:.3f} norm={n:.4f} crop={crop_path}")

    with open(args.out, "w", encoding="utf-8") as f:
        json.dump(refs, f, indent=2)
    print(f"wrote {len(refs)} references -> {args.out}")


if __name__ == "__main__":
    main()