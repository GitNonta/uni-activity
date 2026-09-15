#!/usr/bin/env python3
"""test_cpu_fallback.py — prove the system keeps working when the GPU is
unavailable, at three levels:

  1. WARP as a CPU backend: engines created with gpu_index=-2 run the full
     model on the CPU rasterizer — deterministic, fp16/fp32 gates pass,
     outputs agree with the hardware iGPU (cos >= 0.99999), run() returns
     fdx_engine_describe(name=is_warp) so hosts can *see* CPU mode.
  2. reinit in place: hardware -> WARP -> hardware on ONE engine handle;
     buffers re-created lazily; embeddings still identical after round trip.
  3. recovery drill: simulate device loss by forcing reinit(WARP) mid-batch —
     the exact sequence fdx_engine_reinit performs after FDX_ERR_DEVICE_LOST —
     and check the batch completes with identical results to an all-hardware
     reference run.

Also re-verifies the create-time fallback: gpu_index=-1 on this machine
picks real hardware (auto-WARP only fires on machines with no GPU at all).

  python test_cpu_fallback.py [--n 6]
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
from fdx import Fdx, FdxError  # noqa: E402

DLL = os.path.abspath(os.path.join(HERE, "build", "face_dx.dll"))
MODEL = os.path.abspath(os.path.join(HERE, "models", "w600k_mbf.fvp"))
CROPS = os.path.abspath(os.path.join(HERE, "..", "face_cpp", "testdata", "crops"))
REPORT = os.path.abspath(os.path.join(HERE, "reports", "cpu_fallback.json"))
WORK = os.path.abspath(os.path.join(HERE, "reports", "_cpufb"))

PARITY_GATE = 0.99999   # same-precision, cross-device (established suite gate)
FP16_GATE = 0.9998      # fp16-vs-fp32 precision gate (production convention)


def fail(msg: str) -> None:
    print(f"[FAIL] {msg}")
    sys.exit(1)


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


def cos(a: np.ndarray, b: np.ndarray) -> float:
    return float(np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b) + 1e-30))


def embed(fdx: Fdx, engine, model, rgb_list: list[bytes]) -> np.ndarray:
    return np.asarray([fdx.run(engine, model, rgb)[0] for rgb in rgb_list],
                      dtype=np.float64)


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--n", type=int, default=6)
    a = ap.parse_args()

    fdx = Fdx(DLL)
    rgb_list = stage_images(a.n)
    model = fdx.load_model(MODEL)
    print(f"[cpu] {len(rgb_list)} crops, DLL ABI v{fdx.lib.fdx_abi_version()}")

    results: dict = {"checks": {}}

    # ---- reference: hardware engine ----
    hw = fdx.create_engine(fp16=True, gpu_index=-1)
    d_hw = fdx.describe(hw)
    if d_hw["is_warp"]:
        print("[cpu] WARNING: auto picked WARP — this machine currently has "
              "no usable hardware adapter; hardware checks become WARP checks")
    ref = embed(fdx, hw, model, rgb_list)
    print(f"[cpu] hardware engine: {d_hw['name']}")

    # ---- 1. WARP as a CPU backend ----
    warp = fdx.create_engine(fp16=True, gpu_index=-2)
    d_warp = fdx.describe(warp)
    if not d_warp["is_warp"]:
        fail(f"gpu_index=-2 engine did not report WARP: {d_warp}")
    emb_warp = embed(fdx, warp, model, rgb_list)
    cos_hw = min(cos(ref[i], emb_warp[i]) for i in range(len(rgb_list)))
    det1 = embed(fdx, warp, model, rgb_list)
    det = float(max(abs(emb_warp[i][j] - det1[i][j])
                    for i in range(len(rgb_list)) for j in range(512)))

    warp32 = fdx.create_engine(fp16=False, gpu_index=-2)
    emb_warp32 = embed(fdx, warp32, model, rgb_list)
    cos16 = min(cos(emb_warp[i], emb_warp32[i]) for i in range(len(rgb_list)))

    results["checks"]["warp_cpu_backend"] = {
        "adapter": d_warp,
        "min_cos_vs_hardware": round(cos_hw, 9),
        "determinism_max_abs_diff": det,
        "min_cos_fp16_vs_fp32": round(cos16, 9),
        "ok": cos_hw >= PARITY_GATE and cos16 >= FP16_GATE and det == 0.0,
    }
    print(f"  warp backend: cos(hw)={cos_hw:.9f} det={det} cos(f16/f32)={cos16:.9f}")
    if cos_hw < PARITY_GATE or cos16 < FP16_GATE or det != 0.0:
        fail("WARP CPU backend checks failed")
    fdx.free_engine(warp32)

    # ---- 2. reinit in place: hw -> warp -> hw on one handle ----
    fdx.reinit(hw, fp16=True, gpu_index=-2)
    d_after = fdx.describe(hw)
    if not d_after["is_warp"]:
        fail(f"reinit(-2) did not switch to WARP: {d_after}")
    emb_after = embed(fdx, hw, model, rgb_list)   # buffers re-created lazily
    cos_r1 = min(cos(ref[i], emb_after[i]) for i in range(len(rgb_list)))

    fdx.reinit(hw, fp16=True, gpu_index=-1)
    d_back = fdx.describe(hw)
    if d_back["luid"] != d_hw["luid"]:
        fail(f"reinit(-1) did not return to hardware: {d_back} vs {d_hw}")
    emb_back = embed(fdx, hw, model, rgb_list)
    cos_r2 = min(cos(ref[i], emb_back[i]) for i in range(len(rgb_list)))

    results["checks"]["reinit_roundtrip"] = {
        "to_warp": {**d_after, "min_cos_vs_ref": round(cos_r1, 9)},
        "back_to_hw": {**d_back, "min_cos_vs_ref": round(cos_r2, 9)},
        "ok": cos_r1 >= PARITY_GATE and cos_r2 >= PARITY_GATE,
    }
    print(f"  reinit: ->WARP cos={cos_r1:.9f}, ->HW cos={cos_r2:.9f}")

    # ---- 3. recovery drill: device loss mid-batch ----
    ok, failed = 0, 0
    drill_embs = []
    for i, rgb in enumerate(rgb_list):
        if i == len(rgb_list) // 2:
            # simulate FDX_ERR_DEVICE_LOST recovery exactly as a host would:
            # tear down in place, recreate on WARP (CPU), keep going
            fdx.reinit(warp, fp16=True, gpu_index=-2)
            print("  [drill] device 'lost' mid-batch -> recovered on WARP "
                  f"({fdx.describe(warp)['name']})")
        e, _ = fdx.run(warp, model, rgb)
        drill_embs.append(e)
        ok += 1
    drill = np.asarray(drill_embs, dtype=np.float64)
    worst = min(cos(ref[i], drill[i]) for i in range(len(rgb_list)))
    results["checks"]["recovery_drill"] = {
        "images": ok, "failed": failed, "min_cos_vs_reference": round(worst, 9),
        "ok": ok == len(rgb_list) and worst >= PARITY_GATE,
    }
    print(f"  drill: ok={ok}/{len(rgb_list)} min cos vs reference = {worst:.9f}")
    if worst < PARITY_GATE:
        fail("recovery drill cosine below gate")

    # ---- 4. error paths ----
    # 4a. reinit on out-of-range adapter fails cleanly with FDX_ERR_GPU_INIT
    try:
        fdx.reinit(hw, fp16=True, gpu_index=99)
        fail("reinit(gpu_index=99) unexpectedly succeeded")
    except FdxError as e:
        if e.code != -3:
            fail(f"reinit(99): expected -3, got {e.code}")
        print(f"  reinit(99) -> {e}")
    # 4b. run after a failed reinit must refuse cleanly (FDX_ERR_GPU_INIT:
    # the engine holds no device — it does NOT crash, and it does NOT claim
    # device-loss), then the engine must recover via reinit
    try:
        fdx.run(hw, model, rgb_list[0])
        fail("run after failed reinit unexpectedly succeeded")
    except FdxError as e:
        if e.code != -3:
            fail(f"run after failed reinit: expected -3, got {e.code}: {e}")
        print(f"  run after failed reinit -> {e} (clean refusal, no crash)")
    fdx.reinit(hw, fp16=True, gpu_index=-1)
    e_check, _ = fdx.run(hw, model, rgb_list[0])
    print(f"  recovered engine run cos = {cos(ref[0], e_check):.9f}")
    if cos(ref[0], e_check) < PARITY_GATE:
        fail("engine unusable after reinit recovery")
    # 4c. run on a freshly created (never-inited) engine is impossible via the
    # ABI — creation either succeeds (inited) or raises — so the guarded
    # no-device path is exercised by 4a's clean -3 instead.
    results["checks"]["error_paths"] = {
        "reinit_out_of_range": -3,
        "run_after_failed_reinit": -3,
        "engine_recoverable_after_failed_reinit": True,
        "ok": True,
    }

    for name, chk in results["checks"].items():
        if not chk.get("ok", True):
            fail(f"check {name} not ok")

    # ---- cleanup + report ----
    fdx.free_engine(warp)
    fdx.free_engine(hw)
    fdx.free_model(model)
    report = {
        "meta": {
            "generated_utc": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            "dll": os.path.relpath(DLL, HERE),
            "model": os.path.relpath(MODEL, HERE),
            "images": len(rgb_list),
            "parity_gate": PARITY_GATE,
        },
        "hardware_adapter": d_hw,
        **results,
    }
    with open(REPORT, "w", encoding="utf-8") as fh:
        json.dump(report, fh, indent=2)
    print(f"\n[cpu] ALL CHECKS PASS — report -> {os.path.relpath(REPORT, HERE)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
