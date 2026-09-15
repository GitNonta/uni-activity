#!/usr/bin/env python3
"""test_all_languages.py — prove the FDX C ABI (face_dx/fdx_capi.h) is
consumable from every language host on this machine with identical results.

Hosts: C runner (reference, LoadLibraryA), Python/ctypes, C#/P-Invoke
(system csc build), Java 25 FFM (single-file source launcher), Node 22
koffi (prebuilt FFI). Each runs the same staged crops through face_dx.dll
(fp16) from a foreign working directory, then this driver:

  1. cross-checks every embedding against the CLI exe (bit-rounded identity)
     and against the C runner (the unmanaged reference path)
  2. re-checks each host's error-path probe codes ({bad_model:-2, null_input:-1})
  3. writes reports/cross_language.json

  python test_all_languages.py [--n 6] [--fp16]
"""
from __future__ import annotations

import argparse
import json
import os
import shutil
import subprocess
import sys
import time

import cv2
import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
BUILD = os.path.join(HERE, "build")
DLL = os.path.abspath(os.path.join(BUILD, "face_dx.dll"))
EXE = os.path.abspath(os.path.join(BUILD, "face_dx.exe"))
RUNNER = os.path.abspath(os.path.join(BUILD, "face_dx_runner.exe"))
MODEL = os.path.abspath(os.path.join(HERE, "models", "w600k_mbf.fvp"))
CROPS = os.path.abspath(os.path.join(HERE, "..", "face_cpp", "testdata", "crops"))
WORK = os.path.abspath(os.path.join(HERE, "reports", "_xlang"))
REPORT = os.path.abspath(os.path.join(HERE, "reports", "cross_language.json"))

CS_DIR = os.path.join(HERE, "bindings", "csharp")
CS_EXE = os.path.join(CS_DIR, "fdx_cs.exe")
PY_DIR = os.path.join(HERE, "bindings", "python")
NODE_DIR = os.path.join(HERE, "bindings", "node")
JAVA_DIR = os.path.join(HERE, "bindings", "java")

EMBED_DIM = 512
PROBE_EXPECT = {"bad_model": -2, "null_input": -1}
DIFF_GATE = 1e-7   # text round-trip: all hosts emit 8 significant digits


def fail(msg: str) -> None:
    print(f"[FAIL] {msg}")
    sys.exit(1)


def sh(cmd: list[str], cwd: str) -> subprocess.CompletedProcess:
    return subprocess.run(cmd, cwd=cwd, capture_output=True, text=True,
                          encoding="utf-8", errors="replace", timeout=300)


def stage_raws(n: int) -> list[str]:
    """Decode crops -> raw RGB8 files + list file (shared input for all hosts)."""
    os.makedirs(WORK, exist_ok=True)
    files = sorted(f for f in os.listdir(CROPS) if f.lower().endswith(".png"))[:n]
    if len(files) < n:
        fail(f"need {n} crops, found {len(files)} in {CROPS}")
    raws = []
    for f in files:
        img = cv2.imread(os.path.join(CROPS, f), cv2.IMREAD_COLOR)
        if img is None:
            fail(f"cannot decode {f}")
        if img.shape[0] != 112 or img.shape[1] != 112:
            img = cv2.resize(img, (112, 112), interpolation=cv2.INTER_LINEAR)
        raw = os.path.join(WORK, f[:-4] + ".raw")
        cv2.cvtColor(img, cv2.COLOR_BGR2RGB).tofile(raw)  # engine wants RGB
        if os.path.getsize(raw) != 112 * 112 * 3:
            fail(f"staging bug: {raw} is {os.path.getsize(raw)} bytes")
        raws.append(raw)
    lst = os.path.join(WORK, "raws.txt")
    with open(lst, "w", encoding="utf-8") as fh:
        fh.write("\n".join(os.path.abspath(p) for p in raws) + "\n")
    return raws


def load_jsonl(path: str) -> dict[str, list[float]]:
    out: dict[str, list[float]] = {}
    with open(path, encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if line:
                obj = json.loads(line)
                out[os.path.basename(obj["id"])] = obj["embedding"]
    return out


def run_exe(tag: str, fp16: bool) -> dict[str, list[float]]:
    out = os.path.join(WORK, f"exe_{tag}.jsonl")
    cmd = [EXE, "--model", MODEL, "--list", os.path.join(WORK, "raws.txt"),
           "--fp16" if fp16 else "--fp32", "--out", out]
    r = sh(cmd, cwd=HERE)  # exe resolves shaders/ from its own cwd: face_dx
    if r.returncode != 0:
        fail(f"exe {tag}: rc={r.returncode} {r.stderr[-400:]}")
    return load_jsonl(out)


def run_host(label: str, cmd: list[str], cwd: str) -> tuple[dict[str, list[float]], float]:
    out = os.path.join(WORK, f"{label}.jsonl")
    full = cmd + ["--out", out]
    t0 = time.perf_counter()
    r = sh(full, cwd=cwd)
    wall = time.perf_counter() - t0
    if r.returncode != 0:
        fail(f"{label}: rc={r.returncode}\nstderr: {r.stderr[-600:]}")
    print(f"  [{label}] {r.stderr.strip().splitlines()[-1] if r.stderr.strip() else '(no stderr)'}")
    return load_jsonl(out), wall


def probe(label: str, cmd: list[str], cwd: str) -> dict:
    full = cmd + ["--probe-errors"]
    r = sh(full, cwd=cwd)
    if r.returncode != 0:
        fail(f"{label} probe: rc={r.returncode} {r.stderr[-300:]}")
    got = json.loads(r.stdout.strip().splitlines()[-1])
    ok = got == PROBE_EXPECT
    print(f"  [{label}] probe {got} {'OK' if ok else 'MISMATCH'}")
    if not ok:
        fail(f"{label} probe mismatch: got {got}, want {PROBE_EXPECT}")
    return got


def maxdiff(a: dict, b: dict) -> float:
    if set(a) != set(b):
        fail(f"id sets differ: {sorted(a)} vs {sorted(b)}")
    worst = 0.0
    for k in a:
        va, vb = np.asarray(a[k], dtype=np.float64), np.asarray(b[k], dtype=np.float64)
        if va.size != EMBED_DIM or vb.size != EMBED_DIM:
            fail(f"{k}: bad embedding size {va.size}/{vb.size}")
        worst = max(worst, float(np.max(np.abs(va - vb))))
    return worst


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--n", type=int, default=6)
    ap.add_argument("--fp16", action="store_true", default=True)
    a = ap.parse_args()

    for p in (DLL, EXE, RUNNER, MODEL, CS_EXE):
        if not os.path.isfile(p):
            fail(f"missing {p}")
    tmp_cwd = os.path.join(WORK, "cwd")  # foreign cwd, proves path independence
    shutil.rmtree(WORK, ignore_errors=True)
    os.makedirs(tmp_cwd, exist_ok=True)

    raws = stage_raws(a.n)
    print(f"[xlang] {len(raws)} crops staged, fp16={'on' if a.fp16 else 'off'}")

    # ---- 1. reference embeddings from the CLI exe ----
    tag = "fp16" if a.fp16 else "fp32"
    ref = run_exe(tag, a.fp16)
    print(f"[xlang] exe reference: {len(ref)} embeddings")

    # ---- 2. every host, same inputs, foreign cwd ----
    hosts = {
        "c-runner": ([RUNNER, "--dll", DLL, "--model", MODEL,
                      "--list", os.path.join(WORK, "raws.txt")] +
                     (["--fp16"] if a.fp16 else []), tmp_cwd),
        "python": ([sys.executable, os.path.join(PY_DIR, "fdx.py"), "--dll", DLL,
                    "--model", MODEL, "--list", os.path.join(WORK, "raws.txt")] +
                   (["--fp16"] if a.fp16 else []), PY_DIR),
        "node": (["node", os.path.join(NODE_DIR, "fdx_node.js"), "--dll", DLL,
                  "--model", MODEL, "--list", os.path.join(WORK, "raws.txt")] +
                 (["--fp16"] if a.fp16 else []), NODE_DIR),
        "csharp": ([CS_EXE, "--dll", DLL, "--model", MODEL,
                    "--list", os.path.join(WORK, "raws.txt")] +
                   (["--fp16"] if a.fp16 else []), tmp_cwd),
        "java": (["java", "--enable-native-access=ALL-UNNAMED",
                  os.path.join(JAVA_DIR, "FdxJava.java"), "--dll", DLL,
                  "--model", MODEL, "--list", os.path.join(WORK, "raws.txt")] +
                 (["--fp16"] if a.fp16 else []), JAVA_DIR),
    }

    t0 = time.perf_counter()
    results: dict[str, dict] = {}
    c_ref: dict[str, list[float]] | None = None
    for label, (cmd, cwd) in hosts.items():
        embs, wall = run_host(label, cmd, cwd)
        row = {
            "images": len(embs),
            "wall_s": round(wall, 3),
            "max_diff_vs_exe": maxdiff(embs, ref),
            "min_cos_vs_exe": round(min(
                float(np.dot(e, ref[k]) /
                      (np.linalg.norm(e) * np.linalg.norm(ref[k]) + 1e-30))
                for k, e in ((k, np.asarray(v, dtype=np.float64)) for k, v in embs.items())
            ), 9),
        }
        if label == "c-runner":
            c_ref = embs
        results[label] = row

    # ---- 3. cross-host matrix vs the C runner (unmanaged reference) ----
    for label, (cmd, cwd) in hosts.items():
        if label == "c-runner":
            continue
        out = os.path.join(WORK, f"{label}.jsonl")
        results[label]["max_diff_vs_c"] = maxdiff(load_jsonl(out), c_ref)

    # ---- 4. error-path probes on every language host (the C runner has no
    #      probe mode; its CLI arg handling is exercised by the run above) ----
    probes = {"c-runner": {"mode": "n/a (LoadLibrary CLI, no probe mode)"}}
    for label, (cmd, cwd) in hosts.items():
        if label == "c-runner":
            continue
        base = [x for x in cmd if x not in ("--fp16",)]
        probes[label] = probe(label, base, cwd)

    # ---- 5. gates + report ----
    for label, row in results.items():
        for key in ("max_diff_vs_exe", "max_diff_vs_c"):
            if key in row and row[key] > DIFF_GATE:
                fail(f"{label}: {key}={row[key]:.3e} > gate {DIFF_GATE:.1e}")
        if row["min_cos_vs_exe"] < 0.9999999:
            fail(f"{label}: min cos {row['min_cos_vs_exe']} below gate")

    host_timing = {}
    report = {
        "meta": {
            "generated_utc": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            "dll": os.path.relpath(DLL, HERE),
            "model": os.path.relpath(MODEL, HERE),
            "images": len(raws),
            "mode": tag,
            "diff_gate": DIFF_GATE,
            "probe_expect": PROBE_EXPECT,
            "hosts": sorted(results),
        },
        "results": results,
        "probes": probes,
    }
    os.makedirs(os.path.dirname(REPORT), exist_ok=True)
    with open(REPORT, "w", encoding="utf-8") as fh:
        json.dump(report, fh, indent=2)

    print(f"\n[xlang] ALL HOSTS PASS ({time.perf_counter() - t0:.1f}s total)")
    print(f"{'host':<10} {'imgs':>4} {'maxdiff vs exe':>15} {'maxdiff vs C':>13} {'cos':>11}")
    for label, row in results.items():
        print(f"{label:<10} {row['images']:>4} {row['max_diff_vs_exe']:>15.3e} "
              f"{row.get('max_diff_vs_c', float('nan')):>13.3e} {row['min_cos_vs_exe']:>11.7f}")
    print(f"[xlang] report -> {os.path.relpath(REPORT, HERE)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
