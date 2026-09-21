#!/usr/bin/env python3
"""
alignment_probe.py — find where the AI server's cross-stack parity breaks.

The integration report (reports/server_integration_test.json) shows
engine_vs_onnx cos 0.999974 but server_vs_engine cos -0.037..0.094.
This probe isolates which stage diverges: detection/alignment, crop
preprocessing, or the engine itself.

Matrix per face image (from the CelebA zip, which the prod pipeline uses):
  engines : insightface (SCRFD det + w600k_mbf ONNX via ORT)
            fdx        (w600k_mbf.fvp via the fdx wheel, same weights)
  crops   : norm_crop      (5-point landmark warp — InsightFace canonical)
            plain          (whole image bilinear resize — what server.py
                            feeds when landmarks are missing at 112x112)

Outputs pairwise cosines so we can see whether the two engines agree on
the SAME crop (engine parity) and whether one engine diverges when the
crop path differs (alignment sensitivity).
"""
from __future__ import annotations

import os
import sys
import zipfile

import cv2
import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "pylib"))  # repo fdx fallback

ZIP_PATH = os.path.abspath(os.path.join(HERE, "..", "face_cpp", "img_align_celeba.zip"))
PROBE_IDS = ["000001", "000050", "000123", "000238", "000451"]


def l2(v: np.ndarray) -> np.ndarray:
    return v / max(float(np.linalg.norm(v)), 1e-12)


def cos(a: np.ndarray, b: np.ndarray) -> float:
    return float(np.dot(a, b))


def main() -> int:
    # ── InsightFace reference (det + rec, exactly like gen_references.py) ──
    from insightface.app import FaceAnalysis
    import onnxruntime as ort

    app = FaceAnalysis(name="buffalo_l", allowed_modules=["detection", "recognition"],
                       providers=["CPUExecutionProvider"])
    app.prepare(ctx_id=-1, det_size=(640, 640), det_thresh=0.5)

    # ── fdx engine (same weights as w600k_mbf.onnx) ────────────────────────
    import fdx
    fx = fdx.FaceEmbedder()          # bundled w600k_mbf.fvp fp16
    print(f"[fdx] {fx.describe()}")

    # arcface onnx for the onnx-reference column
    onnx_model_path = os.path.join(HERE, "models", "w600k_mbf.onnx")
    so = ort.SessionOptions()
    sess = ort.InferenceSession(onnx_model_path, so, providers=["CPUExecutionProvider"])
    inp_name = sess.get_inputs()[0].name

    def onnx_embed(img_rgb112: np.ndarray) -> np.ndarray:
        blob = ((img_rgb112.astype(np.float32) - 127.5) / 127.5).transpose(2, 0, 1)[None]
        out = sess.run(None, {inp_name: blob})[0].ravel()
        return l2(out.astype(np.float32))

    def fdx_embed(img_rgb112: np.ndarray) -> np.ndarray:
        return fx.embed(img_rgb112)   # wheel resizes (no-op at 112) + normalizes

    z = zipfile.ZipFile(ZIP_PATH)
    rows = []
    for pid in PROBE_IDS:
        raw = z.read(f"img_align_celeba/{pid}.jpg")
        img = cv2.imdecode(np.frombuffer(raw, np.uint8), cv2.IMREAD_COLOR)
        faces = app.get(img)
        if not faces:
            print(f"[skip] {pid}: no SCRFD face")
            continue
        f = max(faces, key=lambda x: x.det_score)

        # crop A: canonical landmark warp (what insightface feeds its own rec)
        from insightface.utils import face_align
        aligned_bgr = face_align.norm_crop(img, landmark=f.kps, image_size=112)
        aligned_rgb = np.ascontiguousarray(aligned_bgr[:, :, ::-1])  # engine contract: RGB
        # crop B: plain whole-image bilinear resize (server.py no-landmark path)
        plain = np.ascontiguousarray(
            cv2.resize(img, (112, 112), interpolation=cv2.INTER_LINEAR)[:, :, ::-1])

        e = {}
        e["if_aligned"] = l2(f.normed_embedding)
        e["if_plain"] = onnx_embed(plain)
        e["onnx_aligned"] = onnx_embed(aligned_rgb)
        e["fdx_aligned"] = fdx_embed(aligned_rgb)
        e["fdx_plain"] = fdx_embed(np.ascontiguousarray(plain))

        row = {
            "id": pid,
            "if_vs_onnx_aligned": cos(e["if_aligned"], e["onnx_aligned"]),
            "if_vs_fdx_aligned": cos(e["if_aligned"], e["fdx_aligned"]),
            "if_vs_fdx_plain": cos(e["if_plain"], e["fdx_plain"]),
            "fdx_vs_onnx_aligned": cos(e["fdx_aligned"], e["onnx_aligned"]),
            "fdx_vs_if_plain": cos(e["fdx_plain"], e["if_plain"]),
            "fdx_aligned_vs_plain": cos(e["fdx_aligned"], e["fdx_plain"]),
            "if_aligned_vs_plain": cos(e["if_aligned"], e["if_plain"]),
        }
        rows.append(row)
        print(f"[{pid}] " + "  ".join(f"{k}={v:+.4f}" for k, v in row.items() if k != "id"))

    keys = [k for k in rows[0].keys() if k != "id"] if rows else []
    print("\n== mean over", len(rows), "faces ==")
    for k in keys:
        vals = [r[k] for r in rows]
        print(f"{k:26s} mean={np.mean(vals):+.4f}  min={np.min(vals):+.4f}")

    fx.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
