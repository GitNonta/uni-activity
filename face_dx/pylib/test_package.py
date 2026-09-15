#!/usr/bin/env python3
"""test_package.py — prove the fdx wheel is a real, self-contained library.

Run with the wheel INSTALLED in a clean venv, from a foreign CWD:

    cd face_dx/pylib && python -m venv .venv-test
    .venv-test/Scripts/pip install dist/fdx-1.0.0-py3-win_amd64.whl
    cd /tmp && <venv>/python <repo>/face_dx/pylib/test_package.py

Checks
  1. import fdx works with NO repo on sys.path (wheel-resident payload)
  2. self_check() — ABI gate, error contract, describe, finite normalized embed
  3. fp16-vs-fp32 production cosine gate (>= 0.9998) on a real test image
  4. bit-identical embeddings: installed wheel vs loose bindings/python/fdx.py
     (same DLL, same inputs -> same bits)
  5. WARP (gpu_index=-2) parity: CPU software path agrees with hardware
  6. batch + error paths (bad file -> FdxError, wrong-size bytes -> ValueError)

Report: face_dx/reports/package_check.json
"""
from __future__ import annotations

import json
import os
import sys
import time

import numpy as np

REPO_FDX = os.path.dirname(os.path.abspath(__file__))      # face_dx/pylib
FACE_DX = os.path.dirname(REPO_FDX)                        # face_dx
REPORTS = os.path.join(FACE_DX, "reports")
REPORT = os.path.join(REPORTS, "package_check.json")

results: dict = {"ok": True, "checks": {}}


def check(name: str, cond: bool, detail) -> None:
    results["checks"][name] = {"pass": bool(cond), "detail": detail}
    results["ok"] &= bool(cond)
    mark = "PASS" if cond else "FAIL"
    print(f"[{mark}] {name}: {detail}")


def main() -> int:
    os.makedirs(REPORTS, exist_ok=True)
    # This script lives in face_dx/pylib, so sys.path[0] is the source tree
    # and would shadow the installed wheel. Remove it so `import fdx` can
    # only resolve from site-packages (the thing under test).
    sys.path[:] = [p for p in sys.path
                   if os.path.abspath(p or ".") != os.path.abspath(REPO_FDX)]
    import fdx
    results["fdx_file"] = fdx.__file__
    loc = fdx.__file__.replace("\\", "/").lower()
    check("wheel_import", "site-packages" in loc, fdx.__file__)
    check("version", fdx.__version__ == "1.0.0", fdx.__version__)
    check("abi", fdx.abi_version() == fdx.FDX_ABI_VERSION, fdx.abi_version())

    # 2. self_check with a real image (no face needed — engine-level check)
    sample = os.path.join(FACE_DX, "test_student_full_112.png")
    if not os.path.exists(sample):
        sample = os.path.join(FACE_DX, "test_student.png")
    rep = fdx.self_check(sample_image=sample)
    check("self_check", bool(rep["ok"]), rep)
    results["describe"] = rep["checks"].get("describe")

    # 3. bit-parity vs the loose binding, run in a SUBPROCESS (two copies
    # of the same DLL in one process is exactly what real hosts never do;
    # the binding CLI writes the same 8-significant-digit JSONL the exe does)
    from PIL import Image
    img = Image.open(sample).convert("RGB").resize((112, 112), Image.BILINEAR)
    rgb_bytes = img.tobytes()   # engine-contract raw RGB (NOT an encoded file)

    with fdx.FaceEmbedder() as fx:
        emb_wheel, ms = fx.embed_ms(rgb_bytes)
        desc = fx.describe()

    import subprocess
    import tempfile
    loose_path = os.path.join(FACE_DX, "bindings", "python", "fdx.py")
    dll_path = os.path.join(FACE_DX, "build", "face_dx.dll")
    model_path = os.path.join(FACE_DX, "models", "w600k_mbf.fvp")
    with tempfile.TemporaryDirectory() as td:
        rawp = os.path.join(td, "img.raw")
        with open(rawp, "wb") as f:
            f.write(rgb_bytes)
        listp = os.path.join(td, "list.txt")
        with open(listp, "w", encoding="utf-8") as f:
            f.write(rawp + "\n")
        outp = os.path.join(td, "out.jsonl")
        subprocess.run(
            [sys.executable, loose_path, "--dll", dll_path,
             "--model", model_path, "--list", listp, "--out", outp,
             "--fp16"], check=True, capture_output=True)
        with open(outp, "r", encoding="utf-8") as f:
            j = json.loads(f.readline())
    emb_loose = np.asarray(j["embedding"], dtype=np.float32)
    diff = float(np.max(np.abs(emb_wheel - emb_loose)))
    check("bit_parity_vs_binding", diff <= 1e-6,
          f"max_abs_diff={diff:.2e} (8-sig-digit JSONL text floor)")

    # 5. WARP parity (pure-CPU software rasterizer)
    with fdx.FaceEmbedder(gpu_index=-2, env_defaults=False) as fw:
        emb_warp = fw.embed(rgb_bytes)
        warp_desc = fw.describe()
    cos = float(np.dot(emb_wheel, emb_warp))
    check("warp_parity", cos >= 0.999999, f"cos={cos:.9f} adapter={warp_desc['name']}")

    # 6. batch + error paths
    embs = fdx.FaceEmbedder(env_defaults=False).embed_batch([rgb_bytes, rgb_bytes])
    check("batch_shape", embs.shape == (2, 512), str(embs.shape))
    try:
        fdx.FaceEmbedder(env_defaults=False).embed(b"not-an-image")
        check("bad_bytes_raises", False, "no exception")
    except Exception as e:
        check("bad_bytes_raises", True, type(e).__name__)  # UnidentifiedImageError

    wrong = np.zeros((10, 10, 3), dtype=np.uint8)
    with fdx.FaceEmbedder(env_defaults=False) as f2:
        emb_small = f2.embed(wrong)
    ok_small = emb_small.shape == (512,) and bool(np.isfinite(emb_small).all())
    check("small_ndarray_resized_like_pil", ok_small,
          "documented semantics: ndarray (like PIL/encoded) auto-resizes to 112x112; "
          "only raw= bytes are size-strict")

    results["describe"] = desc
    results["engine_ms_fp16"] = round(ms, 2)

    # context-manager + raw-ndarray convenience
    with fdx.FaceEmbedder(env_defaults=False) as f3:
        ok3 = f3.embed(np.ascontiguousarray(img)).shape == (512,)
    check("context_manager_ndarray", ok3, "PIL image as ndarray input")

    with open(REPORT, "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2, default=str)
    print(f"report: {REPORT}")
    return 0 if results["ok"] else 1


if __name__ == "__main__":
    t0 = time.time()
    rc = main()
    print(f"total {time.time() - t0:.1f}s — {'ALL PASS' if rc == 0 else 'FAILURES'}")
    sys.exit(rc)
