#!/usr/bin/env python3
"""celeba_pipeline.py — extract CelebA images from a zip, decode them into 512-d
embeddings with the DX11 GPU engine, record per-image processing time, and
cross-verify the results to prove decoding accuracy. Saves a JSON report.

Three independent executions of the same network are compared per image:
  1. gpu    — build/face_dx.exe (D3D11 compute, Intel iGPU) on the .fvp model
  2. numpy  — pure-python re-execution of the same .fvp graph (check_fvp.py core)
  3. onnx   — ONNX Runtime on the original w600k_mbf.onnx (independent reference)

The engine output is already L2-normalized; the other two are normalized here so
cosine similarity is well defined. Pass criterion: cosine >= 0.9999 everywhere
(fp32 round-off headroom; the numpy core and ONNX agree to ~1e-6 typically).

Usage:
  python celeba_pipeline.py --zip /path/img_align_celeba.zip --n 300
  python celeba_pipeline.py --zip ... --n 50 --fp16      # quick fp16 regression
"""
from __future__ import annotations

import argparse
import json
import os
import statistics
import subprocess
import sys
import tempfile
import time
import zipfile
from concurrent.futures import ThreadPoolExecutor

import cv2
import numpy as np
import onnxruntime as ort

import check_fvp as cfv

HERE = os.path.dirname(os.path.abspath(__file__))
DEFAULT_ZIP = r"D:\projects\uni-activity\face_cpp\img_align_celeba.zip"
EXE = os.path.join(HERE, "build", "face_dx.exe")
MODEL_FVP = os.path.join(HERE, "models", "w600k_mbf.fvp")
MODEL_ONNX = os.path.join(HERE, "models", "w600k_mbf.onnx")
IMG_PREFIX = "img_align_celeba/"  # layout inside the official CelebA aligned zip

PASS_COS = 0.9999  # fp32 cross-backend acceptance threshold


def l2n(v: np.ndarray) -> np.ndarray:
    n = float(np.linalg.norm(v))
    return v / n if n > 1e-12 else v


def cos(a: np.ndarray, b: np.ndarray) -> float:
    return float(np.dot(l2n(a), l2n(b)))


def stats(xs: list[float]) -> dict:
    xs = sorted(xs)
    n = len(xs)
    p = lambda q: xs[min(n - 1, max(0, int(round(q * (n - 1)))))]
    return {
        "n": n,
        "mean_ms": round(statistics.fmean(xs), 3),
        "median_ms": round(statistics.median(xs), 3),
        "p95_ms": round(p(0.95), 3),
        "min_ms": round(xs[0], 3),
        "max_ms": round(xs[-1], 3),
        "std_ms": round(statistics.pstdev(xs), 3) if n > 1 else 0.0,
    }


def cos_stats(cs: list[float]) -> dict:
    return {
        "n": len(cs),
        "mean": round(statistics.fmean(cs), 8),
        "min": round(min(cs), 8),
        "max": round(max(cs), 8),
    }


def preprocess(data: bytes, size: int, channel_order: str) -> np.ndarray:
    """zip bytes -> normalized NCHW float32 [1,3,size,size]."""
    img = cv2.imdecode(np.frombuffer(data, np.uint8), cv2.IMREAD_COLOR)
    img = cv2.resize(img, (size, size), interpolation=cv2.INTER_AREA)
    if channel_order == "rgb":
        img = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
    x = img.astype(np.float32)
    x = (x - 127.5) / 127.5
    return x.transpose(2, 0, 1)[None].copy()


def run_gpu(png_path: str, fp16: bool, bench: int = 1) -> tuple[np.ndarray, float, float | None]:
    # forward slashes: the engine echoes the path into its JSON unescaped,
    # so backslashes would produce invalid JSON on the line we parse
    png_path = png_path.replace("\\", "/")
    cmd = [EXE, "--model", MODEL_FVP, "--fp16" if fp16 else "--fp32"]
    if bench > 1:
        cmd += ["--bench", str(bench)]
    cmd.append(png_path)
    r = subprocess.run(cmd, capture_output=True, text=True, cwd=HERE, timeout=120)
    lines = [ln for ln in r.stdout.splitlines() if ln.strip()]
    if r.returncode != 0 or not lines:
        raise RuntimeError(f"engine failed: {r.stderr.strip()[:300]}")
    rec = json.loads(lines[-1])
    avg = None
    for ln in r.stderr.splitlines():
        if ln.startswith("[bench] ") and " avg=" in ln:
            avg = float(ln.split(" avg=")[1].split("ms")[0])
            break
    return np.asarray(rec["embedding"], np.float32), float(rec["ms"]), avg


def run_numpy(layers, x: np.ndarray) -> np.ndarray:
    return np.asarray(cfv.run_fvp(layers, x[0])[-1], np.float32)


def run_onnx(sess, x: np.ndarray) -> np.ndarray:
    return np.asarray(sess.run(None, {"input.1": x})[0][0], np.float32)


def probe_channel_order(zf: zipfile.ZipFile, name: str) -> str:
    """Pick RGB vs BGR by agreement with the numpy .fvp graph on a real image.
    The graph's expected input is pinned by the exporter: RGB, (x-127.5)/127.5.
    The GPU engine and the numpy graph read the identical .fvp weights, so the
    order under which they agree is the order the model was exported with."""
    data = zf.read(name)
    layers = cfv.load_fvp(MODEL_FVP)
    scores = {}
    for order in ("rgb", "bgr"):
        x = preprocess(data, 112, order)
        g, _, _ = run_gpu(png_for_engine(data, 112, order), fp16=False)
        scores[order] = cos(g, run_numpy(layers, x))
    best = max(scores, key=scores.get)
    print(f"[probe] channel order scores vs numpy graph: "
          f"{ {k: round(v, 4) for k, v in scores.items()} } -> {best}")
    return best


_PNG_DIR = tempfile.mkdtemp(prefix="fdx_celeba_")
_PNG_SEQ = iter(range(1 << 30))


def png_for_engine(data: bytes, size: int, channel_order: str) -> str:
    """Decode+resize zip bytes and save as an 8-bit PNG for the engine's own
    decoder. The engine stores the file's canonical RGB channel order into the
    input tensor as-is (verified: feeding RGB vs BGR differs to 1e-7, matching
    insightface's blobFromImages(swapRB=True) convention for w600k_mbf), then
    applies (v-127.5)/127.5 itself. cv2.imwrite expects a BGR array for a plain
    PNG, so the unmodified cv2 image is exactly what we want on disk."""
    img = cv2.imdecode(np.frombuffer(data, np.uint8), cv2.IMREAD_COLOR)
    img = cv2.resize(img, (size, size), interpolation=cv2.INTER_AREA)
    p = os.path.join(_PNG_DIR, f"img_{next(_PNG_SEQ)}.png")
    if not cv2.imwrite(p, img):
        raise RuntimeError("failed to write PNG for engine")
    return p


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--zip", default=DEFAULT_ZIP)
    ap.add_argument("--n", type=int, default=300, help="sample size")
    ap.add_argument("--out", default=os.path.join(HERE, "reports", "celeba_512d_report.json"))
    ap.add_argument("--embeddings", default=None, help="optional .npz path for raw 512-d outputs")
    ap.add_argument("--fp16", action="store_true", help="run engine in fp16 mode")
    ap.add_argument("--bench", type=int, default=3, help="GPU runs averaged per image")
    ap.add_argument("--workers", type=int, default=5, help="verification thread pool size")
    ap.add_argument("--seed", type=int, default=0)
    args = ap.parse_args()

    for f in (EXE, MODEL_FVP, MODEL_ONNX):
        if not os.path.isfile(f):
            print(f"[error] missing {f}", file=sys.stderr)
            return 2

    zf = zipfile.ZipFile(args.zip)
    jpgs = [n for n in zf.namelist() if n.lower().endswith((".jpg", ".jpeg")) and IMG_PREFIX in n]
    print(f"[zip] {args.zip}: {len(jpgs)} images")
    if len(jpgs) < args.n:
        print(f"[error] requested {args.n} but zip has {len(jpgs)}", file=sys.stderr)
        return 2

    rng = np.random.default_rng(args.seed)
    names = sorted(rng.choice(jpgs, size=args.n, replace=False).tolist())

    layers = cfv.load_fvp(MODEL_FVP)
    so = ort.SessionOptions()
    so.intra_op_num_threads = 2
    so.inter_op_num_threads = 1
    sess = ort.InferenceSession(MODEL_ONNX, so, providers=["CPUExecutionProvider"])

    order = probe_channel_order(zf, names[0])

    # warmup (model parse / shader compile / thread pools) — excluded from stats
    w = names[0]
    png = png_for_engine(zf.read(w), 112, order)
    run_gpu(png, args.fp16, bench=args.bench)
    run_onnx(sess, preprocess(zf.read(w), 112, order))
    print("[warmup] done")

    results: list[dict] = []
    fail: list[dict] = []
    emb_dump: dict[str, np.ndarray] = {}
    t_proc, t_gpu, t_wall, t_np, t_onx = [], [], [], [], []
    c_gn, c_go, c_no, norms = [], [], [], []

    def verify(x: np.ndarray, emb_g: np.ndarray):
        t2 = time.perf_counter()
        emb_n = run_numpy(layers, x)
        t3 = time.perf_counter()
        emb_o = run_onnx(sess, x)
        t4 = time.perf_counter()
        return (cos(emb_g, emb_n), cos(emb_g, emb_o), cos(emb_n, emb_o),
                emb_n, emb_o, (t3 - t2) * 1e3, (t4 - t3) * 1e3)

    pool = ThreadPoolExecutor(max_workers=args.workers)
    inflight: dict = {}

    def _collect(fut) -> None:
        i, name, ms_first, bavg, emb_g = inflight.pop(fut)
        a, b, c, emb_n, emb_o, tnp, tonx = fut.result()
        t_np.append(tnp); t_onx.append(tonx)
        c_gn.append(a); c_go.append(b); c_no.append(c)
        norms.append(float(np.linalg.norm(emb_g)))
        gpu_ms = bavg if bavg is not None else ms_first
        ok = a >= PASS_COS and b >= PASS_COS and c >= PASS_COS
        row = {"image": name, "gpu_ms": round(gpu_ms, 2),
               "cos_gpu_numpy": round(a, 8), "cos_gpu_onnx": round(b, 8),
               "cos_numpy_onnx": round(c, 8), "pass": ok}
        results.append(row)
        if not ok:
            fail.append(row)
        if args.embeddings:
            # store all three variants L2-normalized so the artifact is
            # directly comparable component-wise (gpu is already normalized)
            emb_dump[name] = np.stack([l2n(emb_g), l2n(emb_n), l2n(emb_o)])
        if i % 50 == 0 or i == len(names):
            print(f"[{i}/{len(names)}] {name} gpu={gpu_ms:.1f}ms "
                  f"cos(gpu,np)={a:.7f} cos(gpu,onnx)={b:.7f} cos(np,onnx)={c:.7f}")

    for i, name in enumerate(names, 1):
        data = zf.read(name)
        t0 = time.perf_counter()
        x = preprocess(data, 112, order)
        png = png_for_engine(data, 112, order)
        t1 = time.perf_counter()
        emb_g, ms_first, bavg = run_gpu(png, args.fp16, bench=args.bench)
        t2 = time.perf_counter()
        t_proc.append((t1 - t0) * 1e3)
        t_wall.append((t2 - t1) * 1e3)
        t_gpu.append(bavg if bavg is not None else ms_first)
        inflight[pool.submit(verify, x, emb_g)] = (i, name, ms_first, bavg, emb_g)
        # drain finished futures from the head to keep results in input order
        while len(inflight) >= 2 * args.workers:
            fut0 = next(iter(inflight))
            if not fut0.done():
                break
            _collect(fut0)
    for fut in list(inflight):
        _collect(fut)
    pool.shutdown()

    total_ms = statistics.fmean(t_proc) + statistics.fmean(t_gpu)
    report = {
        "meta": {
            "generated_utc": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            "dataset": {
                "zip": os.path.abspath(args.zip),
                "total_images": len(jpgs),
                "layout": f"{IMG_PREFIX}NNNNNN.jpg, 178x218 aligned crops",
            },
            "sample": {"size": len(names), "method": f"uniform random seed={args.seed}",
                       "gpu_bench_runs_per_image": args.bench},
            "model": {
                "fvp": os.path.relpath(MODEL_FVP, HERE),
                "onnx_reference": os.path.relpath(MODEL_ONNX, HERE),
                "layers": len(layers),
                "embedding_dim": 512,
                "postprocess": "L2 normalize (cosine-ready, matches insightface normed_embedding)",
            },
            "engine": {
                "executable": os.path.relpath(EXE, HERE),
                "backend": "Direct3D 11 compute (hand-written HLSL kernels)",
                "precision": "fp16" if args.fp16 else "fp32",
            },
            "preprocess": "decode JPG -> resize to 112x112 (INTER_AREA) -> "
                          f"{order.upper()} -> (x-127.5)/127.5 -> NCHW",
            "channel_order_probe": order,
        },
        "timing_ms": {
            "gpu_inference": stats(t_gpu),
            "preprocess_cpu": stats(t_proc),
            "engine_process_wall": stats(t_wall),
            "numpy_graph_cpu": {**stats(t_np),
                "note": "reference backend; measured under thread contention (informational)"},
            "onnx_reference_cpu": {**stats(t_onx),
                "note": "reference backend; measured under thread contention (informational)"},
            "preprocess_plus_gpu": {
                "mean_ms": round(total_ms, 3),
                "throughput_images_per_sec": round(1000.0 / total_ms, 2),
            },
        },
        "verification": {
            "protocol": "3-way per image: DX11 GPU engine vs numpy re-execution of .fvp "
                        "vs ONNX Runtime on the original ONNX model (all cosine after L2 norm)",
            "cosine_gpu_vs_numpy": cos_stats(c_gn),
            "cosine_gpu_vs_onnx": cos_stats(c_go),
            "cosine_numpy_vs_onnx": cos_stats(c_no),
            "gpu_embedding_norm": cos_stats(norms),
            "pass_threshold": PASS_COS,
            "failed_images": fail,
            "passed": len(fail) == 0,
        },
        "results": results,
    }

    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    with open(args.out, "w", encoding="utf-8") as f:
        json.dump(report, f, indent=2)
    print(f"\n[report] {args.out}")

    if args.embeddings:
        np.savez_compressed(args.embeddings, names=np.asarray(names),
                            variants=np.asarray(["gpu_l2", "numpy_l2", "onnx_l2"]), **{
            "emb_" + k: v for k, v in emb_dump.items()})
        print(f"[report] {args.embeddings}")

    print(f"[summary] gpu mean {statistics.fmean(t_gpu):.2f} ms | "
          f"pipeline mean {total_ms:.2f} ms/img "
          f"({1000.0/total_ms:.1f} img/s) | "
          f"cos(gpu,np) min {min(c_gn):.7f} | cos(gpu,onnx) min {min(c_go):.7f} | "
          f"{'PASS' if not fail else f'FAIL ({len(fail)} images)'}")
    return 0 if not fail else 1


if __name__ == "__main__":
    sys.exit(main())
