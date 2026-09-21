#!/usr/bin/env python3
"""_native_parity_check.py — prove native_face.py == insightface package.

For N images:
  1. detection parity: same #faces, best-face bbox IoU >= 0.95,
     kps max delta <= 1.0 px (float32 tolerance through resize/warp chains)
  2. alignment parity: norm_crop outputs identical (max |diff| in 0-255 units)
  3. embedding parity: cos(native ArcFace, insightface ArcFaceONNX) >= 0.9995
     on the same aligned crop (raw ORT sessions both sides, same providers)
  4. end-to-end: cos(native pipeline, insightface pipeline) >= 0.9995

Run:  python _native_parity_check.py [N]   (from face_dx/)
"""
import os
import sys
import cv2
import json
import numpy as np

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "ai_service")))
from native_face import NativeSCRFD, NativeArcFace, norm_crop as native_norm_crop  # noqa: E402

import onnxruntime as ort
from insightface.app import FaceAnalysis
from insightface.model_zoo.model_zoo import get_model as if_get_model
from insightface.utils import face_align as if_face_align

HERE = os.path.dirname(os.path.abspath(__file__))
DET = os.path.join(HERE, "..", "face_cpp", "models", "buffalo_l", "det_10g.onnx")
if not os.path.isfile(DET):
    DET = r"C:/Users/Non/.insightface/models/buffalo_l/det_10g.onnx"
REC = os.path.join(HERE, "models", "w600k_mbf.onnx")
ZIP_PATH = os.path.abspath(os.path.join(HERE, "..", "face_cpp", "img_align_celeba.zip"))
REPORT = os.path.join(HERE, "reports", "native_parity_check.json")


def _providers():
    avail = ort.get_available_providers()
    gpu = [p for p in ["CUDAExecutionProvider", "DmlExecutionProvider",
                       "TensorrtExecutionProvider", "CoreMLExecutionProvider"] if p in avail]
    return (gpu + ["CPUExecutionProvider"], 0) if gpu else (["CPUExecutionProvider"], -1)


def cos(a, b) -> float:
    a = np.asarray(a, dtype=np.float64).ravel()
    b = np.asarray(b, dtype=np.float64).ravel()
    return float(np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b)))


def main(n_images: int = 20) -> bool:
    providers, ctx_id = _providers()
    print(f"providers={providers}")

    det = NativeSCRFD(DET, providers=providers)
    rec = NativeArcFace(REC, providers=providers)

    # CelebA archive: parity must run on real photos, not synthetic noise.
    import zipfile
    with zipfile.ZipFile(ZIP_PATH) as zf:
        all_names = sorted(n for n in zf.namelist()
                           if n.lower().endswith((".jpg", ".jpeg")))
    assert all_names, f"no jpgs in {ZIP_PATH}"
    names = all_names[:n_images]

    # Oracle: the installed insightface package.
    app = FaceAnalysis(name="buffalo_l", allowed_modules=["detection"], providers=providers)
    app.prepare(ctx_id=ctx_id, det_size=(640, 640), det_thresh=0.5)
    oracle_rec = if_get_model(REC, providers=providers)
    oracle_rec.prepare(ctx_id)

    results, ok = [], True
    with zipfile.ZipFile(ZIP_PATH) as zf:
        for name in names:
            raw = zf.read(name)
            img = cv2.imdecode(np.frombuffer(raw, np.uint8), cv2.IMREAD_COLOR)
            if img is None:
                continue

            nf = det.detect(img, input_size=(640, 640), det_thresh=0.5)
            of = app.get(img)

            row = {"image": name, "n_native": len(nf), "n_oracle": len(of)}

            if len(nf) and len(of):
                b_best = max(nf, key=lambda f: f.det_score)
                o_best = max(of, key=lambda f: f.det_score)

                bb = b_best.bbox.astype(float)
                ob = o_best.bbox.astype(float)
                ix1, iy1 = max(bb[0], ob[0]), max(bb[1], ob[1])
                ix2, iy2 = min(bb[2], ob[2]), min(bb[3], ob[3])
                inter = max(0.0, ix2 - ix1) * max(0.0, iy2 - iy1)
                union = ((bb[2] - bb[0]) * (bb[3] - bb[1])
                         + (ob[2] - ob[0]) * (ob[3] - ob[1]) - inter)
                iou = inter / union if union > 0 else 0.0
                row["bbox_iou"] = round(iou, 4)

                if b_best.kps is not None and o_best.kps is not None:
                    row["kps_max_delta_px"] = round(
                        float(np.abs(b_best.kps - o_best.kps).max()), 3)

                crop_n = native_norm_crop(img, b_best.kps, 112)
                crop_o = if_face_align.norm_crop(img, landmark=o_best.kps, image_size=112)
                row["warp_max_abs_diff"] = round(
                    float(np.abs(crop_n.astype(np.int16) - crop_o.astype(np.int16)).max()), 2)

                row["emb_cos_same_crop"] = round(
                    cos(rec.get_feat(crop_n), oracle_rec.get_feat(crop_o)), 6)
                e_n = rec.normed_embedding(crop_n)
                feat_o = oracle_rec.get_feat(crop_o).astype(np.float32).ravel()
                e_o = feat_o / np.linalg.norm(feat_o)  # == face.normed_embedding
                row["e2e_cos"] = round(cos(e_n, e_o), 6)

                ok &= iou >= 0.95
                ok &= row["kps_max_delta_px"] <= 1.0
                ok &= row["warp_max_abs_diff"] <= 1.0
                ok &= row["emb_cos_same_crop"] >= 0.9995
                ok &= row["e2e_cos"] >= 0.9995
            else:
                if len(nf) != len(of):
                    ok = False
                    row["MISMATCH"] = "face count differs"
                else:
                    row["note"] = "both found no face"

            results.append(row)
            print(f"  {name}: iou={row.get('bbox_iou')} kpsD={row.get('kps_max_delta_px')} "
                  f"warpD={row.get('warp_max_abs_diff')} "
                  f"embCos={row.get('emb_cos_same_crop')} e2eCos={row.get('e2e_cos')}")

    agg = {
        "ok": bool(ok),
        "images": len(results),
        "min_iou": min((r.get("bbox_iou", 0) for r in results), default=None),
        "max_kps_delta": max((r.get("kps_max_delta_px", 0) for r in results), default=None),
        "max_warp_diff": max((r.get("warp_max_abs_diff", 0) for r in results), default=None),
        "min_emb_cos": min((r.get("emb_cos_same_crop", 0) for r in results), default=None),
        "min_e2e_cos": min((r.get("e2e_cos", 0) for r in results), default=None),
        "rows": results,
    }
    with open(REPORT, "w") as f:
        json.dump(agg, f, indent=2)
    print(json.dumps({k: v for k, v in agg.items() if k != "rows"}, indent=2))
    return bool(ok)


if __name__ == "__main__":
    n = int(sys.argv[1]) if len(sys.argv) > 1 else 20
    sys.exit(0 if main(n) else 1)
