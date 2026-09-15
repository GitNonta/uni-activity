#!/usr/bin/env python3
"""bench_cpu_vs_gpu.py — comparative benchmark of running the same model
(w600k_mbf / custom 512-d ArcFace) on CPU vs GPU backends.

Backends
--------
  gpu-fp16 / gpu-fp32 : build/face_dx.exe — own D3D11 compute engine
                        (Intel iGPU), fp32-accumulate / fp16-weights
  onnx-1t/2t/4t       : ONNX Runtime, CPUExecutionProvider — the model's
                        original runtime, 1/2/4 intra-op threads
  numpy               : pure-python re-execution of the .fvp graph
                        (check_fvp.run_fvp) — the verification interpreter

Protocol
--------
Every backend consumes the SAME decoded 112x112 inputs: crops are decoded
once with OpenCV, staged as raw RGB8 files for the engine (its zero-decode
path), and identical float32 NCHW tensors are fed to ONNX/numpy. Each
backend gets a warmup, then wall-clock over N images plus per-image
kernel/driver ms where the backend reports them.

Agreement: cosine of every backend's embeddings vs the ONNX CPU (4-thread)
reference. Gates follow celeba_pipeline.py: 0.9998 (fp16) / 0.9999 (fp32).

Usage:
    python bench_cpu_vs_gpu.py --n 1000
    python bench_cpu_vs_gpu.py --n 20 --numpy-rows 8
"""
from __future__ import annotations

import argparse
import json
import os
import subprocess
import sys
import time

import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)

from celeba_pipeline import (  # noqa: E402
    EXE, MODEL_FVP, MODEL_ONNX, DEFAULT_ZIP, decode_resize, stage_from_img,
    cos, l2n,
)

# shared staging dir (same one the pipeline uses; stage_from_img writes here)
_STAGE_DIR = os.path.join(HERE, "reports", ".tmp_png")


def collect_images(n: int, zip_path: str) -> list[str]:
    import zipfile
    with zipfile.ZipFile(zip_path) as zf:
        names = [x for x in zf.namelist()
                 if x.lower().endswith((".jpg", ".jpeg")) and not x.endswith("/")]
        return sorted(names[:n])


def decode_to_stage(zip_path: str, names: list[str]) -> None:
    """Decode each zip image ONCE; stage raw RGB for the engine. The float
    tensors for the CPU backends are rebuilt from these exact staged pixels,
    so every backend sees bit-identical input data."""
    os.makedirs(_STAGE_DIR, exist_ok=True)
    import zipfile
    with zipfile.ZipFile(zip_path) as zf:
        for name in names:
            stage_from_img(decode_resize(zf.read(name), 112), 112)


def stage_tags(n: int) -> list[str]:
    return [f"img_{i}.raw" for i in range(n)]


def load_stage_tensors(tags: list[str]) -> list[np.ndarray]:
    """Rebuild [1,3,112,112] float tensors from the staged raw RGB files."""
    out = []
    for tag in tags:
        rgb = np.fromfile(os.path.join(_STAGE_DIR, tag), np.uint8)
        x = rgb.reshape(112, 112, 3).astype(np.float32)   # RGB
        x = (x - 127.5) / 127.5
        out.append(x.transpose(2, 0, 1)[None].copy())
    return out


# ---- backends --------------------------------------------------------------
def run_engine(raws: list[str], fp16: bool) -> tuple[float, list[np.ndarray], list[float]]:
    """One batch engine process over all staged images (the production path).
    Returns wall seconds, embeddings (in input order), engine-reported ms."""
    list_path = os.path.join(_STAGE_DIR, "bench_list.txt")
    out_path = os.path.join(_STAGE_DIR, "bench_out.jsonl")
    with open(list_path, "w", encoding="utf-8") as f:
        f.write("\n".join(p.replace("\\", "/") for p in raws) + "\n")
    cmd = [EXE, "--model", MODEL_FVP, "--fp16" if fp16 else "--fp32",
           "--list", list_path.replace("\\", "/"), "--out",
           out_path.replace("\\", "/")]
    t0 = time.perf_counter()
    r = subprocess.run(cmd, cwd=HERE, capture_output=True, text=True)
    wall = time.perf_counter() - t0
    if r.returncode != 0:
        raise RuntimeError(f"engine failed: {r.stderr.strip()[:300]}")
    rows: dict[str, tuple[np.ndarray, float]] = {}
    with open(out_path, encoding="utf-8") as f:
        for line in f:
            rec = json.loads(line)
            rows[rec["id"]] = (np.asarray(rec["embedding"], np.float32), rec["ms"])
    embs, ms = [], []
    for p in raws:
        e, m = rows[p.replace("\\", "/")]
        embs.append(e)
        ms.append(m)
    return wall, embs, ms


def run_onnx(tensors: list[np.ndarray], threads: int) -> tuple[float, list[np.ndarray], list[float]]:
    """ONNX Runtime CPU. One warmup, then a single timed pass that also
    captures embeddings and per-image latency."""
    import onnxruntime as ort
    so = ort.SessionOptions()
    so.intra_op_num_threads = threads
    so.inter_op_num_threads = 1
    sess = ort.InferenceSession(MODEL_ONNX, so, providers=["CPUExecutionProvider"])
    _ = sess.run(None, {"input.1": tensors[0]})  # warmup: thread pool + caches
    embs: list[np.ndarray] = []
    per: list[float] = []
    t0 = time.perf_counter()
    for x in tensors:
        t1 = time.perf_counter()
        y = np.asarray(sess.run(None, {"input.1": x})[0][0], np.float32)
        per.append((time.perf_counter() - t1) * 1e3)
        embs.append(y)
    return time.perf_counter() - t0, embs, per


def run_numpy_backend(tensors: list[np.ndarray]) -> tuple[float, list[np.ndarray], list[float]]:
    """Pure-python .fvp interpreter (check_fvp.run_fvp) — same graph the GPU
    engine executes, executed element-wise in numpy."""
    import check_fvp as cfv
    layers = cfv.load_fvp(MODEL_FVP)
    embs: list[np.ndarray] = []
    per: list[float] = []
    t0 = time.perf_counter()
    for x in tensors:
        t1 = time.perf_counter()
        y = np.asarray(cfv.run_fvp(layers, x[0])[-1], np.float32)
        per.append((time.perf_counter() - t1) * 1e3)
        embs.append(y)
    return time.perf_counter() - t0, embs, per


# ---- main -------------------------------------------------------------------
def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--n", type=int, default=1000, help="images to benchmark")
    ap.add_argument("--zip", default=DEFAULT_ZIP)
    ap.add_argument("--numpy-rows", type=int, default=8,
                    help="images for the slow pure-numpy interpreter "
                         "(several times slower than onnx per image)")
    ap.add_argument("--out", default=os.path.join(HERE, "reports", "cpu_vs_gpu.json"))
    a = ap.parse_args()

    print(f"[bench] collecting {a.n} images from {a.zip}")
    names = collect_images(a.n, a.zip)
    if len(names) < a.n:
        print(f"[error] only {len(names)} images available", file=sys.stderr)
        return 2
    t0 = time.perf_counter()
    decode_to_stage(a.zip, names)
    print(f"[bench] decoded+staged {len(names)} images in "
          f"{time.perf_counter()-t0:.1f}s (one decode per image, shared)")

    raws = [os.path.join(_STAGE_DIR, t) for t in stage_tags(a.n)]
    tensors = load_stage_tensors(stage_tags(a.n))

    embs_by_backend: dict[str, list[np.ndarray]] = {}
    rows: dict[str, dict] = {}

    # -- GPU (production batch path, one process for all images)
    for label, fp16 in (("gpu-fp16", True), ("gpu-fp32", False)):
        print(f"[bench] {label}: {len(raws)} images, one batch process")
        wall, embs, ms = run_engine(raws, fp16)
        embs_by_backend[label] = embs
        rows[label] = {
            "throughput_ips": round(len(raws) / wall, 2),
            "per_image_ms_wall": round(wall * 1e3 / len(raws), 2),
            "per_image_ms_kernel": round(float(np.mean(ms)), 2),
            "min_cos_vs_onnx": None,
        }

    # -- CPU: ONNX Runtime thread scaling
    for th in (1, 2, 4):
        label = f"onnx-{th}t"
        print(f"[bench] {label}: {len(tensors)} images")
        wall, embs, per = run_onnx(tensors, th)
        embs_by_backend[label] = embs
        rows[label] = {
            "throughput_ips": round(len(tensors) / wall, 2),
            "per_image_ms_wall": round(wall * 1e3 / len(tensors), 2),
            "per_image_ms_kernel": round(float(np.mean(per)), 2),
            "min_cos_vs_onnx": None,
        }

    # agreement vs the ONNX 4-thread CPU reference (onnx-4t itself == 1.0)
    ref = [l2n(e) for e in embs_by_backend["onnx-4t"]]
    for label, embs in embs_by_backend.items():
        rows[label]["min_cos_vs_onnx"] = round(
            min(cos(l2n(e), r) for e, r in zip(embs, ref)), 7)

    # -- CPU: pure-numpy .fvp interpreter (small subset — several times slower)
    if a.numpy_rows > 0:
        k = min(a.numpy_rows, len(tensors))
        print(f"[bench] numpy interpreter: {k} images")
        wall, embs_np, per = run_numpy_backend(tensors[:k])
        rows["numpy"] = {
            "throughput_ips": round(k / wall, 2),
            "per_image_ms_wall": round(wall * 1e3 / k, 2),
            "per_image_ms_kernel": round(float(np.mean(per)), 2),
            "images_run": k,
            "min_cos_vs_onnx": round(
                min(cos(l2n(e), r) for e, r in zip(embs_np, ref[:k])), 7),
        }

    write_report(rows, a, len(raws))
    return 0


def write_report(rows: dict, a, n_imgs: int) -> None:
    gates = {"gpu-fp16": 0.9998, "gpu-fp32": 0.9999, "numpy": 0.9999}
    cpu_fastest = min(("onnx-1t", "onnx-2t", "onnx-4t"),
                      key=lambda k: rows[k]["per_image_ms_wall"])
    gpu_fastest = min(("gpu-fp16", "gpu-fp32"),
                      key=lambda k: rows[k]["per_image_ms_wall"])
    report = {
        "meta": {
            "generated_utc": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            "images": n_imgs,
            "input": os.path.abspath(a.zip),
            "model": os.path.basename(MODEL_FVP),
            "machine": "Intel i5-10210U (4C/8T) + Intel UHD iGPU, D3D11 compute",
            "protocol": "same decoded 112x112 RGB inputs for every backend; "
                        "engine = one batch process; ONNX = CPUExecutionProvider "
                        "at 1/2/4 intra-op threads; numpy = check_fvp .fvp "
                        "interpreter; agreement = cosine vs ONNX CPU (4 threads)",
        },
        "backends": rows,
        "gates": gates,
        "verdict": {
            "gpu_fastest": gpu_fastest,
            "cpu_fastest": cpu_fastest,
            "speedup_gpu_over_best_cpu": round(
                rows[cpu_fastest]["per_image_ms_wall"]
                / rows[gpu_fastest]["per_image_ms_wall"], 2),
            "all_agree": all(rows[k]["min_cos_vs_onnx"] >= g
                             for k, g in gates.items() if k in rows),
        },
    }
    os.makedirs(os.path.dirname(a.out), exist_ok=True)
    with open(a.out, "w", encoding="utf-8") as f:
        json.dump(report, f, indent=2)
    print(f"[bench] report -> {a.out}")
    for k, v in rows.items():
        print(f"  {k:10s} {v['per_image_ms_wall']:8.2f} ms/img wall  "
              f"{v['throughput_ips']:7.2f} img/s  "
              f"min_cos={v['min_cos_vs_onnx']}")
    print(f"  verdict: {gpu_fastest} is "
          f"{report['verdict']['speedup_gpu_over_best_cpu']}x faster than "
          f"{cpu_fastest}; all_agree={report['verdict']['all_agree']}")


if __name__ == "__main__":
    sys.exit(main())
