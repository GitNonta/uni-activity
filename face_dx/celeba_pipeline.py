#!/usr/bin/env python3
"""celeba_pipeline.py — extract CelebA images from a zip, decode them into 512-d
embeddings with the DX11 GPU engine, record per-image processing time, and
cross-verify the results to prove decoding accuracy. Saves a JSON report.

Three independent executions of the same network can be compared per image:
  1. gpu    — build/face_dx.exe (D3D11 compute, Intel iGPU) on the .fvp model
  2. numpy  — pure-python re-execution of the same .fvp graph (check_fvp.py core)
  3. onnx   — ONNX Runtime on the original w600k_mbf.onnx (independent reference)

Modes:
  default (full verification) — every image is cross-checked gpu-vs-numpy-vs-onnx
      in a process pool while the engine runs chunks. This is the acceptance
      harness; it intentionally spends CPU next to the GPU.
  --production — engine only at steady state. No verifier processes, no ONNX
      session, no numpy graph: the GPU reclaims 100% of the memory bandwidth.
      Results stream to a JSONL embeddings file in the engine's native schema.
      Guards that keep production honest:
        * startup channel-order probe (one-time, 2 images) — skip with --skip-probe
        * --spotcheck N: every Nth image still gets the full 3-way check
          (default 100 ~= 1%); a spot-check failure FAILS the run (exit 1)
      --spotcheck 0 disables checking entirely. numpy/onnxruntime are imported
      lazily, so a deployed box without them installed still runs production.
  --mode spawn — one engine process per image (per-image isolation reference).

Long runs are resumable: every processed image is appended to a state file
(<out>.state.jsonl) as it lands; --resume skips already-processed images.

Usage:
  python celeba_pipeline.py --zip /path/img_align_celeba.zip --n 300
  python celeba_pipeline.py --zip ... --n 5000 --mode batch
  python celeba_pipeline.py --zip ... --n 5000 --resume        # after a kill
  python celeba_pipeline.py --zip ... --n all --production     # engine-only
  python celeba_pipeline.py --zip ... --n 50 --fp16            # fp16 regression
"""
from __future__ import annotations

import argparse
import base64
import json
import os
import re
import shutil
import statistics
import subprocess
import sys
import threading
import time
import zipfile
from concurrent.futures import ProcessPoolExecutor, as_completed, wait as futures_wait

import cv2
import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
DEFAULT_ZIP = r"D:\projects\uni-activity\face_cpp\img_align_celeba.zip"
EXE = os.path.join(HERE, "build", "face_dx.exe")
MODEL_FVP = os.path.join(HERE, "models", "w600k_mbf.fvp")
MODEL_ONNX = os.path.join(HERE, "models", "w600k_mbf.onnx")
IMG_PREFIX = "img_align_celeba/"  # layout inside the official CelebA aligned zip

# fp16 quantization (fp32-accumulate, fp16-weight storage) has a measured cosine tail
# down to ~0.99985 vs the fp32 references — far from real breakage (~0.88) but below
# the fp32 gate, so the gate is precision-aware.
PASS_COS_FP32 = 0.9999
PASS_COS_FP16 = 0.9998


def pass_cos(fp16: bool) -> float:
    return PASS_COS_FP16 if fp16 else PASS_COS_FP32


def l2n(v: np.ndarray) -> np.ndarray:
    n = float(np.linalg.norm(v))
    return v / n if n > 1e-12 else v


def cos(a: np.ndarray, b: np.ndarray) -> float:
    return float(np.dot(l2n(a), l2n(b)))


def stats(xs: list[float]) -> dict | None:
    if not xs:
        return None
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


_PNG_DIR = os.path.join(HERE, "reports", ".tmp_png")
_PNG_SEQ = iter(range(1 << 30))


def png_for_engine(data: bytes, size: int) -> str:
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


def engine_model(fp16: bool) -> str:
    """The engine's --fp16 is quantize-on-load from the fp32 .fvp (load_model
    reads fp32 words and sizes aux offsets in elements; feeding it the packed
    w600k_mbf_f16.fvp corrupts the aux offsets and crashes). The on-load
    quantization produces the identical packed-half GPU buffers, so fp16
    memory-traffic savings are fully realized either way. numpy/ONNX
    references always stay on the fp32 graph: they are the accuracy ground
    truth the engine is judged against."""
    return MODEL_FVP


def run_gpu_spawn(png_path: str, fp16: bool, bench: int = 1) -> tuple[np.ndarray, float, float | None]:
    """One engine process for a single image (spawn mode / probes)."""
    # forward slashes: the engine echoes the path into its JSON unescaped,
    # so backslashes would produce invalid JSON on the line we parse
    png_path = png_path.replace("\\", "/")
    cmd = [EXE, "--model", engine_model(fp16), "--fp16" if fp16 else "--fp32"]
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


class BatchChunk:
    """One engine process handling a chunk of images via --list/--out.

    A reader thread parses result lines as they stream in (the engine flushes
    per line), so `records` fills while the GPU is still working. join()
    waits for process exit and reports (ok, failed, missing) using the
    engine's own summary. Incremental byte-offset reader: never re-reads the
    growing file. Binary mode so seek offsets are exact."""

    def __init__(self, png_paths: list[str], fp16: bool, bench: int):
        self.png_paths = png_paths
        self.list_path = os.path.join(_PNG_DIR, f"list_{id(self)}.txt")
        self.out_path = os.path.join(_PNG_DIR, f"out_{id(self)}.jsonl")
        with open(self.list_path, "w", encoding="utf-8") as f:
            f.write("\n".join(p.replace("\\", "/") for p in png_paths) + "\n")
        if os.path.exists(self.out_path):
            os.remove(self.out_path)

        cmd = [EXE, "--model", engine_model(fp16), "--fp16" if fp16 else "--fp32",
               "--list", self.list_path.replace("\\", "/"),
               "--out", self.out_path.replace("\\", "/")]
        if bench > 1:
            cmd += ["--bench", str(bench)]
        self.proc = subprocess.Popen(cmd, cwd=HERE, stdout=subprocess.DEVNULL,
                                     stderr=subprocess.PIPE, text=True)
        self.records: list[dict] = []  # {"id": png_path, "ms": float, "embedding": [...]}
        self.stderr_text: str | None = None
        self.missing: list[str] = []   # filled by join()
        self.join_wall_ms: float = 0.0
        self._thread = threading.Thread(target=self._reader, daemon=True)
        self._thread.start()

    def _reader(self) -> None:
        offset = 0
        buf = ""
        idle_polls = 0
        while True:
            try:
                with open(self.out_path, "rb") as f:
                    f.seek(offset)
                    chunk = f.read()
            except FileNotFoundError:
                chunk = b""
            if chunk:
                idle_polls = 0
                buf += chunk.decode("utf-8", errors="replace")
                offset += len(chunk)
                while "\n" in buf:
                    line, buf = buf.split("\n", 1)
                    if line.strip():
                        try:
                            self.records.append(json.loads(line))
                        except json.JSONDecodeError:
                            pass
            if len(self.records) >= len(self.png_paths):
                return
            alive = self.proc.poll() is None
            if not alive:
                idle_polls += 1
                if idle_polls > 10 and not chunk:
                    # engine exited; drain a possibly unterminated final line
                    if buf.strip():
                        try:
                            self.records.append(json.loads(buf.strip()))
                        except json.JSONDecodeError:
                            pass
                    return
            time.sleep(0.02 if alive else 0.05)

    def join(self, timeout: float = 600.0) -> tuple[int, int, list[str]]:
        t0 = time.perf_counter()
        try:
            _, stderr = self.proc.communicate(timeout=timeout)
        except subprocess.TimeoutExpired:
            self.proc.kill()
            raise RuntimeError("engine batch timed out")
        self.stderr_text = stderr
        self._thread.join(timeout=30)
        ok = failed = 0
        gm = re.search(r"ok=(\d+) failed=(\d+)", stderr or "")
        if gm:
            ok, failed = int(gm.group(1)), int(gm.group(2))
        delivered = {r["id"].replace("\\", "/") for r in self.records}
        self.missing = [p for p in self.png_paths if p.replace("\\", "/") not in delivered]
        if not gm:
            ok, failed = len(self.records), len(self.missing)
        self.cleanup()
        self.join_wall_ms = (time.perf_counter() - t0) * 1e3
        return ok, failed, self.missing

    def cleanup(self) -> None:
        for p in (self.list_path, self.out_path):
            try:
                os.remove(p)
            except OSError:
                pass


def run_numpy(layers, x: np.ndarray) -> np.ndarray:
    import check_fvp as cfv  # lazy: production needs no numpy graph
    return np.asarray(cfv.run_fvp(layers, x[0])[-1], np.float32)


# ---- process-pool verification (the numpy core is GIL-bound in threads) ----
_VCTX: dict = {}


def _vinit(fp16: bool) -> None:
    """Per-worker setup: graph layers + private ONNX session (not picklable).
    Only called when verification will actually run. Takes the precision flag
    via initargs — spawn'd children re-import this module, so mutated globals
    from the parent would NOT be visible here."""
    import check_fvp as cfv
    import onnxruntime as ort
    _VCTX["layers"] = cfv.load_fvp(MODEL_FVP)
    _VCTX["pass_cos"] = pass_cos(fp16)
    so = ort.SessionOptions()
    so.intra_op_num_threads = 2
    so.inter_op_num_threads = 1
    _VCTX["sess"] = ort.InferenceSession(MODEL_ONNX, so,
                                         providers=["CPUExecutionProvider"])


def _verify_task(name: str, emb_g: np.ndarray, x: np.ndarray, gpu_ms: float) -> dict:
    """Runs in a worker process; returns a plain picklable result row."""
    t2 = time.perf_counter()
    emb_n = run_numpy(_VCTX["layers"], x)
    t3 = time.perf_counter()
    emb_o = np.asarray(_VCTX["sess"].run(None, {"input.1": x})[0][0], np.float32)
    t4 = time.perf_counter()
    a, b, c = cos(emb_g, emb_n), cos(emb_g, emb_o), cos(emb_n, emb_o)
    g = _VCTX["pass_cos"]
    ok = a >= g and b >= g and c >= g
    stack = np.stack([l2n(emb_g), l2n(emb_n), l2n(emb_o)]).astype("<f4")
    return {
        "image": name, "gpu_ms": round(gpu_ms, 2),
        "cos_gpu_numpy": round(a, 8), "cos_gpu_onnx": round(b, 8),
        "cos_numpy_onnx": round(c, 8), "pass": ok,
        "norm_g": float(np.linalg.norm(emb_g)),
        "t_np": (t3 - t2) * 1e3, "t_onx": (t4 - t3) * 1e3,
        "emb_b64": base64.b64encode(stack.tobytes()).decode("ascii"),
    }


def probe_channel_order(zf: zipfile.ZipFile, name: str, fp16: bool) -> str:
    """Pick RGB vs BGR by agreement with the numpy .fvp graph on a real image.
    The graph's expected input is pinned by the exporter: RGB, (x-127.5)/127.5.
    The numpy side always uses the fp32 graph (ground truth); the engine side
    runs the same precision as the benchmark. BGR visibly fails (~0.9) either
    way, so the discrimination survives the fp16 quantization gap."""
    import check_fvp as cfv  # lazy: startup-only sanity check
    data = zf.read(name)
    layers = cfv.load_fvp(MODEL_FVP)  # fp32 reference
    scores = {}
    for order in ("rgb", "bgr"):
        x = preprocess(data, 112, order)
        g, _, _ = run_gpu_spawn(png_for_engine(data, 112), fp16=fp16)
        scores[order] = cos(g, run_numpy(layers, x))
    best = max(scores, key=scores.get)
    print(f"[probe] channel order scores vs numpy graph: "
          f"{ {k: round(v, 4) for k, v in scores.items()} } -> {best}")
    return best


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--zip", default=DEFAULT_ZIP)
    ap.add_argument("--n", default="300",
                    help="sample size, or 'all' for every image in the zip")
    ap.add_argument("--mode", choices=("batch", "spawn"), default="batch",
                    help="batch: one engine process per chunk (fast); "
                         "spawn: one process per image")
    ap.add_argument("--chunk", type=int, default=256,
                    help="images per engine process (batch mode)")
    ap.add_argument("--production", action="store_true",
                    help="engine-only at steady state: no verifier processes, "
                         "no ONNX session, no numpy graph. Guards: startup "
                         "channel probe + --spotcheck N (default every 100th "
                         "image gets the full 3-way check; 0 disables)")
    ap.add_argument("--spotcheck", type=int, default=100,
                    help="production mode: full 3-way check every Nth image "
                         "(0 = never). Ignored outside production")
    ap.add_argument("--skip-probe", action="store_true",
                    help="production mode: skip the startup channel-order probe")
    ap.add_argument("--embeddings-jsonl", default=None,
                    help="stream one {id, ms, embedding} JSON line per image "
                         "here as it is processed (engine-native schema)")
    ap.add_argument("--out", default=os.path.join(HERE, "reports", "celeba_512d_report.json"))
    ap.add_argument("--embeddings", default=None, help="optional .npz path for raw 512-d outputs")
    ap.add_argument("--fp16", action="store_true", help="run engine in fp16 mode")
    ap.add_argument("--bench", type=int, default=1,
                    help="extra GPU runs per image for averaged timing "
                         "(spawn mode records the average; batch mode records "
                         "first-run ms and reports averages separately)")
    ap.add_argument("--workers", type=int, default=5, help="verification process pool size")
    ap.add_argument("--resume", action="store_true",
                    help="continue from the per-image state file written next to "
                         "--out; already-processed images are skipped")
    ap.add_argument("--seed", type=int, default=0)
    args = ap.parse_args()

    production = args.production
    spot = args.spotcheck if production else 0
    do_verify = not production or spot > 0

    zf = zipfile.ZipFile(args.zip)
    jpgs = [n for n in zf.namelist() if n.lower().endswith((".jpg", ".jpeg")) and IMG_PREFIX in n]
    print(f"[zip] {args.zip}: {len(jpgs)} images "
          f"({'production' if production else 'full-verify'} mode)")
    if str(args.n).lower() == "all":
        n_req = len(jpgs)
    else:
        n_req = int(args.n)
    if len(jpgs) < n_req:
        print(f"[error] requested {n_req} but zip has {len(jpgs)}", file=sys.stderr)
        return 2

    for f in (EXE, engine_model(args.fp16)):
        if not os.path.isfile(f):
            print(f"[error] missing {f}", file=sys.stderr)
            return 2
    if do_verify and not os.path.isfile(MODEL_ONNX):
        print(f"[error] missing {MODEL_ONNX} (needed for verification; "
              f"use --production --spotcheck 0 for engine-only)", file=sys.stderr)
        return 2

    rng = np.random.default_rng(args.seed)
    all_names = sorted(rng.choice(jpgs, size=n_req, replace=False).tolist())

    # ---- resume state: one JSON line per processed image, flushed as it lands
    state_path = args.out + ".state.jsonl"
    done: dict[str, tuple[dict, np.ndarray | None]] = {}
    if args.resume and os.path.exists(state_path):
        with open(state_path, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line:
                    continue
                try:
                    obj = json.loads(line)
                except json.JSONDecodeError:
                    continue  # torn final line from a killed run
                row = {k: v for k, v in obj.items()
                       if k not in ("emb", "emb_b64", "norm_g", "t_np", "t_onx")}
                emb = obj.get("emb")
                if emb is None and "emb_b64" in obj:
                    raw = np.frombuffer(base64.b64decode(obj["emb_b64"]), np.float32)
                    emb = raw.reshape(3, 512) if raw.size == 1536 else raw  # verified stack or production single
                done[row["image"]] = (row, emb)
        if done:
            print(f"[resume] {len(done)} processed images loaded from {os.path.basename(state_path)}")
    names = [n for n in all_names if n not in done]

    layers = None
    sess = None
    if do_verify or not args.skip_probe:
        import check_fvp as cfv
        layers = cfv.load_fvp(MODEL_FVP)  # main-thread copy for the probe
    if do_verify:
        import onnxruntime as ort
        so = ort.SessionOptions()
        so.intra_op_num_threads = 2
        so.inter_op_num_threads = 1
        sess = ort.InferenceSession(MODEL_ONNX, so, providers=["CPUExecutionProvider"])

    os.makedirs(_PNG_DIR, exist_ok=True)
    order = "rgb"  # the exporter-pinned contract; probe re-confirms below
    if names:
        if not args.skip_probe:
            order = probe_channel_order(zf, names[0], args.fp16)
        # engine warmup (model parse / shader compile) — excluded from stats
        w = names[0]
        run_gpu_spawn(png_for_engine(zf.read(w), 112), args.fp16, bench=args.bench)
        if sess is not None:
            warm = np.asarray(sess.run(None, {"input.1": preprocess(zf.read(w), 112, order)})[0][0])
            del warm
        print(f"[warmup] done (mode={args.mode}{' production' if production else ''})")

    results: list[dict] = []
    fail: list[dict] = []
    emb_dump: dict[str, np.ndarray] = {}
    t_proc, t_gpu, t_gpu_bench, t_chunk, t_np, t_onx = [], [], [], [], [], []
    c_gn, c_go, c_no, norms = [], [], [], []
    n_spot = 0

    # seed accumulators with resumed rows so the final report is complete
    for row, emb in done.values():
        results.append(row)
        if not row.get("pass"):
            fail.append(row)
        if row.get("cos_gpu_numpy") is not None:
            c_gn.append(row["cos_gpu_numpy"])
            c_go.append(row["cos_gpu_onnx"])
            c_no.append(row["cos_numpy_onnx"])
            norms.append(1.0)
            if row.get("gpu_ms") is not None:
                t_gpu.append(row["gpu_ms"])
        if emb is not None:
            emb_dump[row["image"]] = np.asarray(emb, np.float32)

    n_total = len(all_names)
    n_session = 0
    state_f = open(state_path, "a", encoding="utf-8")
    jsonl_f = open(args.embeddings_jsonl, "a", encoding="utf-8") if args.embeddings_jsonl else None
    lock = threading.Lock()
    t_run0 = time.perf_counter()
    vpool = ProcessPoolExecutor(max_workers=max(1, args.workers),
                                initializer=_vinit, initargs=(bool(args.fp16),)) \
        if do_verify else None
    futs: list = []

    row_idx: dict[str, int] = {}  # image -> position in results (for merges)

    def absorb_verified(res: dict) -> None:
        nonlocal n_session
        stack = np.frombuffer(base64.b64decode(res["emb_b64"]),
                              np.float32).reshape(3, 512).copy()
        row = {k: res[k] for k in ("image", "gpu_ms", "cos_gpu_numpy",
                                   "cos_gpu_onnx", "cos_numpy_onnx", "pass")}
        with lock:
            t_np.append(res["t_np"])
            t_onx.append(res["t_onx"])
            c_gn.append(res["cos_gpu_numpy"])
            c_go.append(res["cos_gpu_onnx"])
            c_no.append(res["cos_numpy_onnx"])
            norms.append(res["norm_g"])
            if res["image"] in row_idx:
                # production spot-check: replace the placeholder row instead
                # of duplicating it (state file is last-wins on resume)
                results[row_idx[res["image"]]] = row
            else:
                row_idx[res["image"]] = len(results)
                results.append(row)
                n_session += 1
            if not res["pass"]:
                fail.append(row)
            if args.embeddings:
                emb_dump[res["image"]] = stack
            res["ts"] = time.time()  # wall clock for per-hour monitoring
            state_f.write(json.dumps(res) + "\n")
            state_f.flush()
        if len(results) % 50 == 0 or len(results) == n_total:
            print(f"[{len(results)}/{n_total}] {res['image']} gpu={res['gpu_ms']}ms "
                  f"cos(gpu,np)={res['cos_gpu_numpy']:.7f} "
                  f"cos(gpu,onnx)={res['cos_gpu_onnx']:.7f}")

    def absorb_production(png: str, rec: dict, name: str, x: np.ndarray | None,
                          verify: bool) -> None:
        """Production fast path: take the engine record as-is, persist it,
        and (optionally) hand this one image to the verifier pool."""
        nonlocal n_session, n_spot
        emb_g = np.asarray(rec["embedding"], np.float32)
        row = {"image": name, "gpu_ms": round(rec["ms"], 2),
               "cos_gpu_numpy": None, "cos_gpu_onnx": None,
               "cos_numpy_onnx": None, "pass": True, "ts": time.time(),
               "emb_b64": base64.b64encode(
                   l2n(emb_g).astype("<f4").tobytes()).decode("ascii")}
        with lock:
            t_gpu.append(rec["ms"])
            row_idx[name] = len(results)
            results.append(row)
            if args.embeddings:
                emb_dump[name] = l2n(emb_g)
            if jsonl_f is not None:
                jsonl_f.write(json.dumps({"id": name, "backend": rec["backend"],
                                          "fp16": rec["fp16"], "ms": rec["ms"],
                                          "embedding": rec["embedding"]}) + "\n")
                jsonl_f.flush()
            state_f.write(json.dumps(row) + "\n")
            state_f.flush()
            n_session += 1
            if verify:
                n_spot += 1
        if verify and vpool is not None and x is not None:
            futs.append(vpool.submit(_verify_task, name, emb_g, x,
                                     float(rec["ms"])))
        if len(results) % 50 == 0 or len(results) == n_total:
            el = time.perf_counter() - t_run0
            print(f"[{len(results)}/{n_total}] {name} gpu={rec['ms']:.1f}ms "
                  f"({n_session/max(el,1e-9):.1f} img/s)"
                  + (" [spot-checked]" if verify else ""))

    def wait_futures(fs: list) -> None:
        for f in as_completed(fs):
            absorb_verified(f.result())
        fs.clear()

    def drain_futures(timeout: float = 0.0) -> None:
        """Absorb whatever verification futures already finished (non-blocking
        by default). Called opportunistically in the batch loop so spot-check
        rows stream into the state file mid-run instead of landing only at
        end-of-run."""
        if vpool is None or not futs:
            return
        done, not_done = futures_wait(futs, timeout=timeout)
        for f in done:
            absorb_verified(f.result())
        futs[:] = list(not_done)

    def collect_records(bc: BatchChunk,
                        png_info: dict[str, tuple[str, np.ndarray | None]],
                        idx0: int) -> None:
        """Hand each engine record of a finished chunk to the right absorber;
        mark images the engine dropped as failures (and persist them to state
        so resume doesn't retry them forever)."""
        bench_avg: dict[str, float] = {}
        for ln in (bc.stderr_text or "").splitlines():
            if ln.startswith("[bench] ") and " avg=" in ln:
                path = ln[len("[bench] "):].split(" backend=")[0]
                try:
                    bench_avg[path] = float(ln.split(" avg=")[1].split("ms")[0])
                except ValueError:
                    pass
        for rec in bc.records:
            png = rec["id"].replace("\\", "/")
            info = png_info.get(png)
            if info is None:
                continue  # unknown id — should not happen
            name, x = info
            if production:
                verify = spot > 0 and (idx0 % spot == 0)
                absorb_production(png, rec, name, x, verify)
            else:
                gpu_ms = bench_avg.get(png, rec["ms"])
                with lock:
                    t_gpu.append(rec["ms"])
                    if png in bench_avg:
                        t_gpu_bench.append(bench_avg[png])
                emb_g = np.asarray(rec["embedding"], np.float32)
                futs.append(vpool.submit(_verify_task, name, emb_g, x, gpu_ms))
            idx0 += 1
        for png in bc.missing:
            info = png_info.get(png.replace("\\", "/"))
            name = info[0] if info else png
            row = {"image": name, "gpu_ms": None,
                   "cos_gpu_numpy": None, "cos_gpu_onnx": None,
                   "cos_numpy_onnx": None, "pass": False, "ts": time.time(),
                   "error": "engine batch dropped this image"}
            with lock:
                results.append(row)
                fail.append(row)
                state_f.write(json.dumps(row) + "\n")
                state_f.flush()

    if args.mode == "spawn":
        window: list = []
        for idx, name in enumerate(names):
            data = zf.read(name)
            t0 = time.perf_counter()
            x = preprocess(data, 112, order)
            png = png_for_engine(data, 112)
            t1 = time.perf_counter()
            emb_g, _ms_first, bavg = run_gpu_spawn(png, args.fp16, bench=args.bench)
            t2 = time.perf_counter()
            with lock:
                t_proc.append((t1 - t0) * 1e3)
            rec = {"id": name, "ms": bavg if bavg is not None else _ms_first,
                   "embedding": emb_g.tolist(), "backend": "gpu-dx16" if args.fp16 else "gpu-dx",
                   "fp16": args.fp16}
            if production:
                absorb_production(png, rec, name, x, spot > 0 and (idx % spot == 0))
            else:
                with lock:
                    t_gpu.append(rec["ms"])
                window.append(vpool.submit(_verify_task, name, emb_g, x, rec["ms"]))
                if len(window) >= args.workers * 2:
                    wait_futures(window)
        wait_futures(window)
    else:
        chunks = [names[i:i + args.chunk] for i in range(0, len(names), args.chunk)]
        pending: list[tuple[BatchChunk, dict[str, tuple[str, np.ndarray | None]], int, int]] = []
        idx_base = 0
        for ci, chunk_names in enumerate(chunks, 1):
            # throttle: at most one finished-but-unverified chunk behind the
            # currently running one, so the GPU never waits on verification
            if len(pending) >= 2:
                old_bc, old_info, old_cnt, old_idx = pending.pop(0)
                ok, failed, _ = old_bc.join()
                with lock:
                    t_chunk.append(old_bc.join_wall_ms / old_cnt)
                collect_records(old_bc, old_info, old_idx)
                drain_futures()
                print(f"[chunk done] ok={ok} failed={failed} ({old_cnt} imgs, "
                      f"engine wall {old_bc.join_wall_ms/1000.0:.2f}s)")
            drain_futures(0.05)  # stream finished spot-checks while GPU works
            tc0 = time.perf_counter()
            png_list: list[str] = []
            png_info: dict[str, tuple[str, np.ndarray | None]] = {}
            for j, name in enumerate(chunk_names):
                data = zf.read(name)
                t0 = time.perf_counter()
                x = preprocess(data, 112, order)
                t1 = time.perf_counter()
                with lock:
                    t_proc.append((t1 - t0) * 1e3)
                png = png_for_engine(data, 112)
                png_list.append(png)
                # production keeps the input tensor only for spot-checked
                # images; full-verify keeps every one for the pool
                keep_x = x if (not production or (spot > 0 and (idx_base + j) % spot == 0)) else None
                png_info[png.replace("\\", "/")] = (name, keep_x)
            bc = BatchChunk(png_list, args.fp16, args.bench)
            pending.append((bc, png_info, len(chunk_names), idx_base))
            idx_base += len(chunk_names)
            print(f"[chunk {ci}/{len(chunks)}] prepped+launched "
                  f"({time.perf_counter() - tc0:.2f}s prep)")
        for old_bc, old_info, old_cnt, old_idx in pending:
            ok, failed, _ = old_bc.join()
            with lock:
                t_chunk.append(old_bc.join_wall_ms / old_cnt)
            collect_records(old_bc, old_info, old_idx)
            print(f"[chunk done] ok={ok} failed={failed} ({old_cnt} imgs, "
                  f"engine wall {old_bc.join_wall_ms/1000.0:.2f}s)")
        wait_futures(futs)
    if vpool is not None:
        vpool.shutdown()
    wall_total = (time.perf_counter() - t_run0) * 1e3
    state_f.close()
    if jsonl_f is not None:
        jsonl_f.close()

    shutil.rmtree(_PNG_DIR, ignore_errors=True)

    n = n_total
    report = {
        "meta": {
            "generated_utc": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            "dataset": {
                "zip": os.path.abspath(args.zip),
                "total_images": len(jpgs),
                "layout": f"{IMG_PREFIX}NNNNNN.jpg, 178x218 aligned crops",
            },
            "sample": {"size": n, "method": f"uniform random seed={args.seed}",
                       "gpu_bench_runs_per_image": args.bench,
                       "resumed_from_state": len(done),
                       "processed_this_session": n_session},
            "model": {
                "fvp": os.path.relpath(engine_model(args.fp16), HERE),
                "onnx_reference": os.path.relpath(MODEL_ONNX, HERE) if do_verify else None,
                "layers": len(layers) if layers else None,
                "embedding_dim": 512,
                "postprocess": "L2 normalize (cosine-ready, matches insightface normed_embedding)",
            },
            "engine": {
                "executable": os.path.relpath(EXE, HERE),
                "backend": "Direct3D 11 compute (hand-written HLSL kernels)",
                "precision": "fp16" if args.fp16 else "fp32",
                "mode": "production" if production else args.mode,
                "chunk_size": args.chunk if args.mode == "batch" else 1,
            },
            "preprocess": "decode JPG -> resize to 112x112 (INTER_AREA) -> "
                          f"{order.upper()} -> (x-127.5)/127.5 -> NCHW",
            "channel_order_probe": None if args.skip_probe else order,
        },
        "timing_ms": {
            "gpu_inference": stats(t_gpu),
            "gpu_inference_bench_avg": stats(t_gpu_bench),
            "preprocess_cpu": stats(t_proc),
            "chunk_engine_wall_per_image": stats(t_chunk) if args.mode == "batch" else None,
            "end_to_end": {
                "wall_total_ms": round(wall_total, 1),
                "processed_total": n,
                "processed_this_session": n_session,
                "wall_per_image_ms": round(wall_total / n_session, 3) if n_session else None,
                "throughput_images_per_sec": round(n_session / (wall_total / 1000.0), 2) if n_session else None,
            },
            "numpy_graph_cpu": ({**stats(t_np),
                "note": "reference backend; measured under process-pool contention (informational)"}
                if t_np else None),
            "onnx_reference_cpu": ({**stats(t_onx),
                "note": "reference backend; measured under process-pool contention (informational)"}
                if t_onx else None),
        },
        "verification": ({
            "mode": "production",
            "steady_state": "engine only — no verifier processes run per image",
            "spotcheck_every_n": spot,
            "spotchecked": n_spot,
            "protocol": "spot-checked images: DX11 GPU engine vs numpy re-execution "
                        "of .fvp vs ONNX Runtime on the original ONNX model "
                        "(all cosine after L2 norm)",
            "cosine_gpu_vs_numpy": cos_stats(c_gn) if c_gn else None,
            "cosine_gpu_vs_onnx": cos_stats(c_go) if c_go else None,
            "cosine_numpy_vs_onnx": cos_stats(c_no) if c_no else None,
            "gpu_embedding_norm": cos_stats(norms) if norms else None,
            "pass_threshold": pass_cos(args.fp16),
            "failed_images": fail,
            "passed": len(fail) == 0,
        } if production else {
            "mode": "full",
            "protocol": "3-way per image: DX11 GPU engine vs numpy re-execution of .fvp "
                        "vs ONNX Runtime on the original ONNX model (all cosine after L2 norm)",
            "cosine_gpu_vs_numpy": cos_stats(c_gn) if c_gn else None,
            "cosine_gpu_vs_onnx": cos_stats(c_go) if c_go else None,
            "cosine_numpy_vs_onnx": cos_stats(c_no) if c_no else None,
            "gpu_embedding_norm": cos_stats(norms) if norms else None,
            "pass_threshold": pass_cos(args.fp16),
            "failed_images": fail,
            "passed": len(fail) == 0,
        }),
        "results": results,
    }

    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    with open(args.out, "w", encoding="utf-8") as f:
        json.dump(report, f, indent=2)
    print(f"\n[report] {args.out}")

    if args.embeddings:
        variants = (["gpu_l2"] if production
                    else ["gpu_l2", "numpy_l2", "onnx_l2"])
        np.savez_compressed(args.embeddings, names=np.asarray(all_names),
                            variants=np.asarray(variants), **{
            "emb_" + k: v for k, v in emb_dump.items()})
        print(f"[report] {args.embeddings}")

    g = stats(t_gpu) or {"mean_ms": float("nan")}
    thr = f"{n_session/(wall_total/1000.0):.1f} img/s" if n_session else "n/a"
    cos_part = (f"cos(gpu,np) min {min(c_gn):.7f} | cos(gpu,onnx) min {min(c_go):.7f} | "
                if c_gn and c_go else "")
    print(f"[summary] mode={args.mode}{' production' if production else ''} "
          f"gpu mean {g['mean_ms']:.2f} ms | "
          f"wall {wall_total/1000.0:.1f}s this session, {n_session} new, "
          f"{n_total} total ({thr}) | {cos_part}"
          f"{'PASS' if not fail else f'FAIL ({len(fail)} images)'}")
    return 0 if not fail else 1


if __name__ == "__main__":
    sys.exit(main())
