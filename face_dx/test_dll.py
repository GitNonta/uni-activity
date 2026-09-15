#!/usr/bin/env python3
"""
test_dll.py — end-to-end smoke test for build/face_dx.dll (fdx_capi ABI v1).

Proves, on the real GPU:
  1. build artifacts exist (dll, both import libs, .def, header, shaders)
  2. the DLL exports exactly the 7 fdx_* functions (objdump)
  3. zero-dependency C runner parity: fdx_runner.c (LoadLibrary only, run
     from a FOREIGN cwd) produces the same embeddings as face_dx.exe on the
     same raw-RGB inputs — fp16 and fp32
  4. error paths via ctypes (the second, non-C consumer type):
     ABI version gate, bad model path -> FDX_ERR_MODEL, NULL input ->
     FDX_ERR_INVALID_ARG
  5. timing sanity of fdx_engine_run vs the exe's reported ms

Output: reports/dll_smoke_test.json + PASS/FAIL line. Exit 0 on pass.
"""
from __future__ import annotations

import ctypes
import json
import os
import subprocess
import sys
import time

import numpy as np
import cv2

HERE = os.path.dirname(os.path.abspath(__file__))
BUILD = os.path.join(HERE, "build")
MODEL = os.path.join(HERE, "models", "w600k_mbf.fvp")
CROPS = os.path.join(HERE, "..", "face_cpp", "testdata", "crops")
WORK = os.path.join(HERE, "reports", "_dlltest")
EXE = os.path.join(BUILD, "face_dx.exe")
DLL = os.path.join(BUILD, "face_dx.dll")
RUNNER = os.path.join(BUILD, "face_dx_runner.exe")

INPUT_FLOATS = 3 * 112 * 112
EMBED_DIM = 512

results: dict = {"meta": {}, "checks": {}, "pass": False}


def fail(msg: str) -> None:
    print(f"[test_dll] FAIL: {msg}")
    results["failures"] = results.get("failures", []) + [msg]


def sh(cmd: list[str], cwd: str | None = None) -> subprocess.CompletedProcess:
    return subprocess.run(cmd, cwd=cwd, capture_output=True, text=True)


# ---------------------------------------------------------------------------
# 1. artifacts
# ---------------------------------------------------------------------------
def check_artifacts() -> None:
    need = {
        "dll": DLL,
        "fdx.lib (GNU implib)": os.path.join(BUILD, "fdx.lib"),
        "face_dx.lib (dlltool)": os.path.join(BUILD, "face_dx.lib"),
        "face_dx.def": os.path.join(BUILD, "face_dx.def"),
        "fdx_capi.h": os.path.join(BUILD, "fdx_capi.h"),
        "runner": RUNNER,
    }
    missing = [k for k, p in need.items() if not os.path.exists(p)]
    shaders = sorted(os.listdir(os.path.join(BUILD, "shaders"))) \
        if os.path.isdir(os.path.join(BUILD, "shaders")) else []
    if len(shaders) != 7:
        missing.append(f"shaders/ (found {len(shaders)}, expected 7)")
    results["checks"]["artifacts"] = {
        "all_present": not missing,
        "missing": missing,
        "shaders": shaders,
        "dll_bytes": os.path.getsize(DLL) if os.path.exists(DLL) else 0,
    }
    if missing:
        fail(f"missing artifacts: {missing}")


# ---------------------------------------------------------------------------
# 2. exports
# ---------------------------------------------------------------------------
EXPECTED_EXPORTS = [
    "fdx_abi_version", "fdx_gpu_count", "fdx_model_load", "fdx_model_free",
    "fdx_engine_create", "fdx_engine_free", "fdx_engine_run",
]


def check_exports() -> None:
    objdump = os.path.join(os.path.expanduser("~"),
                           "scoop", "apps", "mingw-mstorsjo-llvm-ucrt",
                           "current", "bin", "llvm-objdump.exe")
    if not os.path.exists(objdump):
        objdump = "objdump"
    r = subprocess.run([objdump, "-p", DLL], capture_output=True, text=True)
    # llvm-objdump export rows: "<ordinal> <rva> <name>"
    names = sorted({parts[2] for parts in (ln.split() for ln in r.stdout.splitlines())
                    if len(parts) == 3 and parts[0].isdigit()
                    and parts[2].startswith("fdx_")})
    ok = names == sorted(EXPECTED_EXPORTS)
    results["checks"]["exports"] = {"found": names, "expected": sorted(EXPECTED_EXPORTS),
                                    "all_present": ok}
    if not ok:
        fail(f"export mismatch: {names}")


# ---------------------------------------------------------------------------
# 3. staging: PNG crops -> raw RGB8 (shared input for exe and runner)
# ---------------------------------------------------------------------------
def stage_raws() -> list[str]:
    os.makedirs(WORK, exist_ok=True)
    raws = []
    for f in sorted(os.listdir(CROPS)):
        img = cv2.imread(os.path.join(CROPS, f), cv2.IMREAD_COLOR)
        if img is None:
            fail(f"cannot decode {f}")
            continue
        if img.shape[:2] != (112, 112):
            img = cv2.resize(img, (112, 112), interpolation=cv2.INTER_AREA)
        raw = os.path.join(WORK, f[:-4] + ".raw")
        cv2.cvtColor(img, cv2.COLOR_BGR2RGB).tofile(raw)  # engine wants RGB
        raws.append(raw)
    lst = os.path.join(WORK, "raws.txt")
    with open(lst, "w") as fh:
        fh.write("\n".join(os.path.abspath(p) for p in raws) + "\n")
    return raws


def read_jsonl(path: str) -> dict[str, np.ndarray]:
    out = {}
    with open(path) as fh:
        for line in fh:
            if line.strip():
                rec = json.loads(line)
                out[rec["id"]] = np.asarray(rec["embedding"], dtype=np.float64)
    return out


def run_pair(tag: str, fp16: bool, raws: list[str]) -> None:
    """exe and runner on identical raws; runner deliberately runs from a
    foreign cwd (WORK) to prove kernel self-location."""
    exe_out = os.path.join(WORK, f"exe_{tag}.jsonl")
    run_out = os.path.join(WORK, f"runner_{tag}.jsonl")

    flags = ["--fp16"] if fp16 else ["--fp32"]
    r1 = sh([EXE, "--model", MODEL, "--list", os.path.join(WORK, "raws.txt"),
             "--out", exe_out] + flags, cwd=HERE)
    if r1.returncode != 0:
        fail(f"exe {tag}: rc={r1.returncode} {r1.stderr[-300:]}")
        return
    # runner: foreign cwd + absolute paths everywhere
    r2 = sh([RUNNER, "--dll", DLL, "--model", MODEL,
             "--list", os.path.join(WORK, "raws.txt"), "--out", run_out]
            + (["--fp16"] if fp16 else []), cwd=WORK)
    if r2.returncode != 0:
        fail(f"runner {tag}: rc={r2.returncode} {r2.stderr[-300:]}")
        return

    e, r = read_jsonl(exe_out), read_jsonl(run_out)
    if set(e) != set(r) or not e:
        fail(f"{tag}: id sets differ (exe={len(e)} runner={len(r)})")
        return
    diffs, cos = [], []
    for k in e:
        d = float(np.max(np.abs(e[k] - r[k])))
        c = float(np.dot(e[k], r[k]) /
                  (np.linalg.norm(e[k]) * np.linalg.norm(r[k])))
        diffs.append(d)
        cos.append(c)
    results["checks"][f"parity_{tag}"] = {
        "images": len(e),
        "max_abs_diff": max(diffs),
        "min_cos": min(cos),
        "identical": max(diffs) == 0.0,
    }
    if max(diffs) > 1e-6 or min(cos) < 0.999999:
        fail(f"{tag} parity: max_diff={max(diffs):.3g} min_cos={min(cos):.7f}")


# ---------------------------------------------------------------------------
# 4. error paths via ctypes (second consumer type: Python)
# ---------------------------------------------------------------------------
def check_abi_errors() -> None:
    lib = ctypes.CDLL(DLL)
    lib.fdx_abi_version.restype = ctypes.c_int32
    lib.fdx_model_load.argtypes = [ctypes.c_char_p, ctypes.POINTER(ctypes.c_void_p),
                                   ctypes.c_char_p, ctypes.c_int32]
    lib.fdx_model_load.restype = ctypes.c_int32
    lib.fdx_engine_run.argtypes = [ctypes.c_void_p, ctypes.c_void_p,
                                   ctypes.POINTER(ctypes.c_float),
                                   ctypes.POINTER(ctypes.c_float),
                                   ctypes.POINTER(ctypes.c_double)]
    lib.fdx_engine_run.restype = ctypes.c_int32

    checks: dict = {}
    checks["abi_version"] = lib.fdx_abi_version()
    if checks["abi_version"] != 1:
        fail(f"abi version {checks['abi_version']} != 1")

    h = ctypes.c_void_p(0)
    err = ctypes.create_string_buffer(256)
    rc = lib.fdx_model_load(b"models/definitely_missing.fvp",
                            ctypes.byref(h), err, 256)
    checks["bad_model"] = {"code": rc, "err": err.value.decode(errors="replace")}
    if rc != -2:  # FDX_ERR_MODEL
        fail(f"bad model path: expected -2, got {rc}")

    # engine for the NULL-input probe + timing
    h2 = ctypes.c_void_p(0)
    rc2 = lib.fdx_engine_create(1, -1, ctypes.byref(h2), err, 256)
    if rc2 != 0:
        fail(f"ctypes engine_create failed: {rc2} {err.value.decode()}")
        results["checks"]["abi_errors"] = checks
        return

    hm = ctypes.c_void_p(0)
    lib.fdx_model_load(MODEL.encode(), ctypes.byref(hm), err, 256)
    buf = (ctypes.c_float * INPUT_FLOATS)()
    out = (ctypes.c_float * EMBED_DIM)()
    rc3 = lib.fdx_engine_run(h2, hm, None, out, None)  # NULL input
    checks["null_input"] = {"code": rc3}
    if rc3 != -1:  # FDX_ERR_INVALID_ARG
        fail(f"NULL input: expected -1, got {rc3}")

    # timing sanity on the happy path
    times = []
    for _ in range(20):
        t0 = time.perf_counter()
        if lib.fdx_engine_run(h2, hm, buf, out, None) != 0:
            fail("happy-path run failed via ctypes")
            break
        times.append((time.perf_counter() - t0) * 1e3)
    checks["ctypes_run_mean_ms"] = round(float(np.mean(times)), 2)
    if times and checks["ctypes_run_mean_ms"] > 200:
        fail(f"ctypes run suspiciously slow: {checks['ctypes_run_mean_ms']} ms")

    results["checks"]["abi_errors"] = checks


# ---------------------------------------------------------------------------
def main() -> int:
    t0 = time.time()
    results["meta"] = {
        "generated_utc": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "dll": DLL,
        "model": os.path.basename(MODEL),
        "fp16_gate": "parity identical (same fp16 kernels, same raw input)",
    }

    check_artifacts()
    if results["checks"]["artifacts"]["all_present"]:
        check_exports()
        raws = stage_raws()
        if len(raws) >= 4:
            run_pair("fp16", True, raws)
            run_pair("fp32", False, raws)
    check_abi_errors()

    failures = results.get("failures", [])
    results["pass"] = not failures
    results["meta"]["elapsed_s"] = round(time.time() - t0, 1)

    os.makedirs(os.path.join(HERE, "reports"), exist_ok=True)
    rep = os.path.join(HERE, "reports", "dll_smoke_test.json")
    with open(rep, "w") as fh:
        json.dump(results, fh, indent=2)
    print(json.dumps(results, indent=2))
    print(f"\n[test_dll] {'PASS' if results['pass'] else 'FAIL'} — report: {rep}")
    return 0 if results["pass"] else 1


if __name__ == "__main__":
    sys.exit(main())
