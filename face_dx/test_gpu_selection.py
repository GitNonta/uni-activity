#!/usr/bin/env python3
"""test_gpu_selection.py — real-GPU adapter selection test for
fdx_engine_create's gpu_index parameter (backed by Engine::init now actually
honoring it) and the additive fdx_engine_describe export.

Verifies on real hardware:
  1. fdx_gpu_count() enumerates >= 1 adapter; an engine can be created for
     every index 0..n-1 (each reports a non-empty adapter name + LUID)
  2. gpu_index = -1 (auto) lands on one of the enumerated adapters (LUID match)
  3. gpu_index = -2 forces WARP: describe reports is_warp = 1 and the WARP
     adapter's LUID is among the enumerated ones
  4. inference parity: same images through the hardware engine and the WARP
     engine agree (cosine >= 0.99999) — adapter choice must not change answers
  5. error paths: out-of-range gpu_index -> FDX_ERR_GPU_INIT (-3) with a
     readable reason; fdx_engine_describe(NULL) -> FDX_ERR_INVALID_ARG (-1)
  6. records per-image ms for hardware vs WARP (no gate, datapoint only)

  python test_gpu_selection.py [--n 6]
"""
from __future__ import annotations

import argparse
import ctypes as C
import json
import os
import sys
import time

import cv2
import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "bindings", "python"))
from fdx import Fdx, FdxError, rgb8_to_input  # noqa: E402

BUILD = os.path.join(HERE, "build")
DLL = os.path.abspath(os.path.join(BUILD, "face_dx.dll"))
MODEL = os.path.abspath(os.path.join(HERE, "models", "w600k_mbf.fvp"))
CROPS = os.path.abspath(os.path.join(HERE, "..", "face_cpp", "testdata", "crops"))
REPORT = os.path.abspath(os.path.join(HERE, "reports", "gpu_selection.json"))
WORK = os.path.abspath(os.path.join(HERE, "reports", "_gpusel"))

ERR_INIT, ERR_INVALID = -3, -1
PARITY_GATE = 0.99999


def fail(msg: str) -> None:
    print(f"[FAIL] {msg}")
    sys.exit(1)


def describe(lib, engine: int) -> dict:
    name = C.create_string_buffer(256)
    warp = C.c_int32(0)
    luid = C.c_ulonglong(0)
    rc = lib.fdx_engine_describe(engine, name, 256,
                                 C.byref(warp), C.byref(luid))
    if rc != 0:
        fail(f"fdx_engine_describe -> {rc}")
    return {
        "name": name.value.decode("utf-8", "replace"),
        "is_warp": bool(warp.value),
        "luid": int(luid.value),
    }


def stage_images(n: int) -> list[bytes]:
    os.makedirs(WORK, exist_ok=True)
    files = sorted(f for f in os.listdir(CROPS) if f.lower().endswith(".png"))[:n]
    if len(files) < n:
        fail(f"need {n} crops, found {len(files)}")
    outs = []
    for f in files:
        img = cv2.imread(os.path.join(CROPS, f), cv2.IMREAD_COLOR)
        if img is None:
            fail(f"cannot decode {f}")
        if img.shape[0] != 112 or img.shape[1] != 112:
            img = cv2.resize(img, (112, 112), interpolation=cv2.INTER_LINEAR)
        outs.append(cv2.cvtColor(img, cv2.COLOR_BGR2RGB).tobytes())
    return outs


def run_all(fdx: Fdx, engine, model, rgb_list: list[bytes]) -> tuple[np.ndarray, float]:
    embs, ms = [], []
    for rgb in rgb_list:
        e, m = fdx.run(engine, model, rgb)
        embs.append(e)
        ms.append(m)
    return np.asarray(embs, dtype=np.float64), float(np.mean(ms))


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--n", type=int, default=6)
    a = ap.parse_args()

    fdx = Fdx(DLL)  # ABI gate happens in the constructor
    lib = fdx.lib
    lib.fdx_engine_describe.restype = C.c_int32
    lib.fdx_engine_describe.argtypes = [
        C.c_void_p, C.c_char_p, C.c_int32,
        C.POINTER(C.c_int32), C.POINTER(C.c_ulonglong)]

    rgb_list = stage_images(a.n)
    print(f"[gpu] {len(rgb_list)} staged crops, DLL ABI v{fdx.lib.fdx_abi_version()}")

    # ---- 1. enumerate adapters; create one engine per index ----
    n = fdx.gpu_count()
    if n < 1:
        fail("fdx_gpu_count() returned 0 — no adapters to select")
    adapters: list[dict] = []
    handles: list[int] = []
    for i in range(n):
        h = fdx.create_engine(fp16=True, gpu_index=i)
        d = describe(lib, h)
        if not d["name"]:
            fail(f"adapter {i}: empty name")
        adapters.append({"index": i, **d})
        handles.append(h)
        print(f"  adapter[{i}]: {d['name']}  warp={d['is_warp']} luid={d['luid']:#x}")
    luids = {d["luid"] for d in adapters}

    # ---- 2. auto (-1) must land on an enumerated adapter ----
    h_auto = fdx.create_engine(fp16=True, gpu_index=-1)
    d_auto = describe(lib, h_auto)
    print(f"  auto(-1): {d_auto['name']}  warp={d_auto['is_warp']} luid={d_auto['luid']:#x}")
    if d_auto["luid"] not in luids:
        fail(f"auto engine LUID {d_auto['luid']:#x} not among enumerated adapters")

    # ---- 3. forced WARP (-2) ----
    h_warp = fdx.create_engine(fp16=True, gpu_index=-2)
    d_warp = describe(lib, h_warp)
    print(f"  warp(-2): {d_warp['name']}  warp={d_warp['is_warp']} luid={d_warp['luid']:#x}")
    if not d_warp["is_warp"]:
        fail("gpu_index=-2 did not report is_warp=1")
    if "warp" not in d_warp["name"].lower() and "basic render" not in d_warp["name"].lower():
        fail(f"WARP engine reported unexpected name: {d_warp['name']}")
    if d_warp["luid"] not in luids:
        fail(f"WARP LUID {d_warp['luid']:#x} not among enumerated adapters")

    # ---- 4. inference parity: hardware (auto) vs WARP ----
    model = fdx.load_model(MODEL)
    emb_auto, ms_auto = run_all(fdx, h_auto, model, rgb_list)
    emb_warp, ms_warp = run_all(fdx, h_warp, model, rgb_list)
    cos = np.sum(emb_auto * emb_warp, axis=1) / (
        np.linalg.norm(emb_auto, axis=1) * np.linalg.norm(emb_warp, axis=1))
    worst = float(cos.min())
    print(f"  parity hw-vs-warp: min cos = {worst:.9f} "
          f"(ms/img hw {ms_auto:.2f} vs warp {ms_warp:.2f})")
    if worst < PARITY_GATE:
        fail(f"WARP vs hardware min cosine {worst:.9f} < {PARITY_GATE}")

    # ---- 5. error paths ----
    try:
        fdx.create_engine(fp16=True, gpu_index=n)  # out of range
        fail(f"gpu_index={n} unexpectedly succeeded")
    except FdxError as e:
        if e.code != ERR_INIT:
            fail(f"out-of-range gpu_index: expected -3, got {e.code}: {e}")
        print(f"  out-of-range gpu_index={n} -> {e}")
    null_rc = lib.fdx_engine_describe(None, None, 0, None, None)
    if null_rc != ERR_INVALID:
        fail(f"describe(NULL) -> {null_rc}, want {ERR_INVALID}")
    print(f"  describe(NULL) -> {null_rc} (FDX_ERR_INVALID_ARG)")

    # ---- cleanup + report ----
    for h in handles:
        fdx.free_engine(h)
    fdx.free_engine(h_auto)
    fdx.free_engine(h_warp)
    fdx.free_model(model)

    report = {
        "meta": {
            "generated_utc": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            "dll": os.path.relpath(DLL, HERE),
            "model": os.path.relpath(MODEL, HERE),
            "images": len(rgb_list),
            "parity_gate": PARITY_GATE,
        },
        "adapters": adapters,
        "auto": d_auto,
        "warp": d_warp,
        "parity_min_cos_hw_vs_warp": round(worst, 9),
        "ms_per_image": {"hardware_auto": round(ms_auto, 2), "warp": round(ms_warp, 2)},
        "error_paths": {
            "out_of_range_gpu_index": ERR_INIT,
            "describe_null_engine": ERR_INVALID,
        },
    }
    with open(REPORT, "w", encoding="utf-8") as fh:
        json.dump(report, fh, indent=2)

    print(f"\n[gpu] ALL CHECKS PASS — {n} adapter(s), parity {worst:.9f}, "
          f"report -> {os.path.relpath(REPORT, HERE)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
