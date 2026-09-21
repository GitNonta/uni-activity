#!/usr/bin/env python3
"""_latency_benchmark.py — native (v2.4) vs insightface-package (v2.3 era) latency.

Benchmarks the exact per-request work the server does on one face:
    detect (det_10g, letterbox 640) -> norm_crop 112 -> ArcFace embed (w600k_mbf)

  A) insightface-era stack: FaceAnalysis(allowed_modules=["detection"]).get()
     + insightface face_align.norm_crop + model_zoo ArcFaceONNX.get_feat
  B) native stack (v2.4):  NativeSCRFD.detect + native_face.norm_crop
     + NativeArcFace.get_feat

Method: interleaved per-image rounds (A then B) to cancel thermal drift,
3 warmup rounds each, perf_counter_ns timing, p50/p95/mean over
rounds x images. Sanity gate: same-face cos(A, B) >= 0.9999 on every image
so both pipelines are verified to do equivalent work.

Run (from face_dx/):
  python _latency_benchmark.py --providers cpu --rounds 30
  python _latency_benchmark.py --providers dml --rounds 30   # separate process
"""
from __future__ import annotations

import os
import sys
import json
import time
import zipfile
import argparse

# Mirror server.py CPU tuning BEFORE onnxruntime is imported.
os.environ.setdefault("OMP_NUM_THREADS", "4")
os.environ.setdefault("OPENBLAS_NUM_THREADS", "4")
os.environ.setdefault("MKL_NUM_THREADS", "4")

import cv2
import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.abspath(os.path.join(HERE, "..", "ai_service")))

ZIP_PATH = os.path.abspath(os.path.join(HERE, "..", "face_cpp", "img_align_celeba.zip"))
DET = os.path.abspath(os.path.join(HERE, "..", "face_cpp", "models", "buffalo_l", "det_10g.onnx"))
if not os.path.isfile(DET):
    DET = r"C:/Users/Non/.insightface/models/buffalo_l/det_10g.onnx"
REC = os.path.abspath(os.path.join(HERE, "models", "w600k_mbf.onnx"))
REPORT = os.path.join(HERE, "reports", "native_vs_insightface_latency.json")

N_IMAGES = 12
UPSACLE_SCALE = 3.6  # 178x218 -> ~640x785 aspect-true "selfie-like" size


def parse_args():
    p = argparse.ArgumentParser()
    p.add_argument("--providers", choices=["cpu", "dml"], default="cpu")
    p.add_argument("--rounds", type=int, default=30)
    p.add_argument("--out", default=REPORT)
    return p.parse_args()


def cos(a, b) -> float:
    a = np.asarray(a, dtype=np.float64).ravel()
    b = np.asarray(b, dtype=np.float64).ravel()
    return float(np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b)))


def pct(xs, q):
    return round(float(np.percentile(xs, q)), 2)


def load_images():
    import zipfile
    with zipfile.ZipFile(ZIP_PATH) as zf:
        names = sorted(n for n in zf.namelist() if n.lower().endswith(".jpg"))
    idx = np.linspace(0, min(len(names), 2000) - 1, N_IMAGES).astype(int)
    imgs = []
    for i in idx:
        raw = zf_read(names[i])
        img = cv2.imdecode(np.frombuffer(raw, np.uint8), cv2.IMREAD_COLOR)
        if img is None:
            continue
        imgs.append(img)
        h, w = img.shape[:2]
        imgs.append(cv2.resize(img, (int(w * UPSACLE_SCALE), int(h * UPSACLE_SCALE))))
    return imgs


_ZF = None
def zf_read(name):
    global _ZF
    if _ZF is None:
        _ZF = zipfile.ZipFile(ZIP_PATH)
    return _ZF.read(name)


def build_pipelines(providers):
    import onnxruntime as ort
    from native_face import NativeSCRFD, NativeArcFace, norm_crop as native_norm_crop
    from insightface.app import FaceAnalysis
    from insightface.model_zoo.model_zoo import get_model as if_get_model
    from insightface.utils import face_align as if_face_align

    ctx_id = -1 if providers == ["CPUExecutionProvider"] else 0

    # A) insightface-era stack (v2.3 server contract)
    app = FaceAnalysis(name="buffalo_l", allowed_modules=["detection"], providers=providers)
    app.prepare(ctx_id=ctx_id, det_size=(640, 640), det_thresh=0.5)
    rec_a = if_get_model(REC, providers=providers)
    rec_a.prepare(ctx_id)

    # B) native stack (v2.4)
    det_b = NativeSCRFD(DET, providers=providers)
    rec_b = NativeArcFace(REC, providers=providers)

    def pipeline_a(img):
        faces = app.get(img)
        if not faces:
            return None, 0.0, 0.0, 0.0
        f = max(faces, key=lambda x: x.det_score)
        t0 = time.perf_counter_ns()
        crop = if_face_align.norm_crop(img, landmark=f.kps, image_size=112)
        t1 = time.perf_counter_ns()
        feat = rec_a.get_feat(crop)
        t2 = time.perf_counter_ns()
        emb = feat / max(float(np.linalg.norm(feat)), 1e-12)
        return emb, 0.0, (t1 - t0) / 1e6, (t2 - t1) / 1e6  # detect timed by caller

    def pipeline_b(img):
        t0 = time.perf_counter_ns()
        faces = det_b.detect(img, input_size=(640, 640), det_thresh=0.5)
        t1 = time.perf_counter_ns()
        if not faces:
            return None, (t1 - t0) / 1e6, 0.0, 0.0
        f = max(faces, key=lambda x: x.det_score)
        crop = native_norm_crop(img, f.kps, 112)
        t2 = time.perf_counter_ns()
        feat = rec_b.get_feat(crop)
        t3 = time.perf_counter_ns()
        emb = feat / max(float(np.linalg.norm(feat)), 1e-12)
        return emb, (t1 - t0) / 1e6, (t2 - t1) / 1e6, (t3 - t2) / 1e6

    def detect_time_a(img):
        t0 = time.perf_counter_ns()
        faces = app.get(img)
        return (time.perf_counter_ns() - t0) / 1e6, faces

    return pipeline_a, pipeline_b, detect_time_a


def main():
    args = parse_args()
    providers = (["DmlExecutionProvider", "CPUExecutionProvider"]
                 if args.providers == "dml" else ["CPUExecutionProvider"])

    from insightface.app import FaceAnalysis  # noqa: F401  (oracle present)

    imgs = load_images()
    print(f"providers={providers} rounds={args.rounds} images={len(imgs)} "
          f"(native ~178x218 + upscaled ~640x785)")

    pipeline_a, pipeline_b, detect_time_a = build_pipelines(providers)

    # Warmup 3 rounds (DML shader compile / session init)
    for img in imgs[:2]:
        for _ in range(3):
            pipeline_a(img)
            pipeline_b(img)

    # Sanity: equivalent work on every image (cos >= 0.9999)
    for img in imgs:
        emb_a, _, _, _ = pipeline_a(img)
        emb_b, _, _, _ = pipeline_b(img)
        assert emb_a is not None and emb_b is not None, "pipeline found no face"
        c = cos(emb_a, emb_b)
        assert c >= 0.9999, f"sanity cos {c:.6f} < 0.9999 — stacks differ"
    print("sanity: cos(insightface-era, native) >= 0.9999 on all images")

    # Interleaved timing rounds
    t = {k: [] for k in ("a_detect", "a_align", "a_embed", "a_total",
                         "b_detect", "b_align", "b_embed", "b_total")}
    for r in range(args.rounds):
        for img in imgs:
            d_ms, faces = detect_time_a(img)
            _, _, a_align, a_embed = pipeline_a(img)
            t["a_detect"].append(d_ms)
            t["a_align"].append(a_align)
            t["a_embed"].append(a_embed)
            t["a_total"].append(d_ms + a_align + a_embed)

            b_det, b_align, b_embed = pipeline_b(img)[1:]
            t["b_detect"].append(b_det)
            t["b_align"].append(b_align)
            t["b_embed"].append(b_embed)
            t["b_total"].append(b_det + b_align + b_embed)
        print(f"  round {r + 1}/{args.rounds} done", flush=True)

    keys = {"a": "insightface-era (v2.3)", "b": "native (v2.4)"}
    stages = ("detect", "align", "embed", "total")
    report = {
        "providers": providers,
        "rounds": args.rounds,
        "images_per_round": len(imgs),
        "samples_per_stage": len(t["a_total"]),
        "sanity": "cos(insightface-era, native) >= 0.9999 on all images",
        "date": time.strftime("%Y-%m-%d %H:%M"),
        "results": {},
    }
    print(f"\n{'stage':<8} {'':<22} {'p50':>8} {'p95':>8} {'mean':>8}  (ms)")
    for k_prefix, label in keys.items():
        row = {}
        for st in stages:
            xs = t[f"{k_prefix}_{st}"]
            row[st] = {"p50": pct(xs, 50), "p95": pct(xs, 95),
                       "mean": round(float(np.mean(xs)), 2)}
            print(f"{st:<8} {label:<22} {row[st]['p50']:>8.2f} {row[st]['p95']:>8.2f} "
                  f"{row[st]['mean']:>8.2f}")
        report["results"][label] = row

    a_tot, b_tot = report["results"][keys["a"]]["total"], report["results"][keys["b"]]["total"]
    speed = round(a_tot["p50"] / max(b_tot["p50"], 1e-9), 3)
    report["results"]["native_speedup_p50"] = speed
    print(f"\nnative speedup @p50 total: {speed}x  "
          f"({a_tot['p50']:.1f} -> {b_tot['p50']:.1f} ms)")

    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    with open(args.out, "w") as f:
        json.dump(report, f, indent=2)
    print(f"report: {args.out}")


if __name__ == "__main__":
    main()
