#!/usr/bin/env python3
"""
run_all_benchmarks.py — Orchestrates comprehensive benchmarking of:
1. face_dx (Direct3D 11 Compute Shaders on Intel UHD Graphics)
2. face_cpp (ncnn CPU OpenMP & Vulkan GPU)
3. CelebFaces (CelebA) PyTorch model multi-iteration training & inference
4. Cosine similarity accuracy verification against reference InsightFace embeddings.
"""
from __future__ import annotations

import glob
import json
import math
import os
import subprocess
import sys
import time
from typing import Any, Dict, List


def cosine_sim(a: List[float], b: List[float]) -> float:
    dot = sum(x * y for x, y in zip(a, b))
    na = math.sqrt(sum(x * x for x in a))
    nb = math.sqrt(sum(x * x for x in b))
    return dot / (na * nb + 1e-12)


def run_command_capture(cmd: List[str], cwd: str) -> str:
    print(f"[run_all_benchmarks] Running in {cwd}: {' '.join(cmd)}")
    p = subprocess.run(cmd, cwd=cwd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    if p.returncode != 0:
        print(f"!! Command exited with {p.returncode}:\n{p.stderr}")
    return p.stdout + "\n" + p.stderr


def parse_jsonl_output(text: str) -> Dict[str, Any]:
    import re
    out = {}
    for line in text.splitlines():
        line = line.strip()
        if line.startswith("{") and line.endswith("}"):
            # Fix unescaped Windows backslashes in file paths (e.g. \u in \uni-activity)
            # Replace single backslash not followed by " \ / b f n r t u
            clean_line = re.sub(r'\\(?![\\"/bfnrt]|u[0-9a-fA-F]{4})', '/', line)
            # Also fix \u that is not a 4-hex unicode escape
            clean_line = re.sub(r'\\u(?![0-9a-fA-F]{4})', '/u', clean_line)
            try:
                obj = json.loads(clean_line)
                if "id" in obj and "embedding" in obj:
                    stem = os.path.splitext(os.path.basename(obj["id"].replace("\\", "/")))[0]
                    out[stem] = obj
            except Exception as e:
                # Fallback: extract embedding array directly
                try:
                    m_id = re.search(r'"id"\s*:\s*"([^"]+)"', line)
                    m_emb = re.search(r'"embedding"\s*:\s*(\[[^\]]+\])', line)
                    if m_id and m_emb:
                        raw_id = m_id.group(1).replace("\\", "/")
                        stem = os.path.splitext(os.path.basename(raw_id))[0]
                        emb = json.loads(m_emb.group(1))
                        out[stem] = {"id": raw_id, "embedding": emb}
                except Exception:
                    pass
    return out


def parse_bench_lines(text: str) -> Dict[str, Dict[str, float]]:
    # e.g. [bench] ..\face_cpp\testdata\crops\profile_7.png backend=gpu-dx n=20 avg=61.20ms min=59.12ms max=65.40ms
    # or   [bench] .\face_cpp\testdata\crops\profile_7.png backend=cpu fp16=off n=20 avg=19.80ms min=17.51ms max=25.03ms
    metrics = {}
    for line in text.splitlines():
        line = line.strip()
        if line.startswith("[bench]"):
            parts = line.split()
            path = parts[1]
            stem = os.path.splitext(os.path.basename(path))[0]
            m: Dict[str, float] = {}
            for p in parts[2:]:
                if "=" in p:
                    k, v = p.split("=", 1)
                    if v.endswith("ms"):
                        try:
                            m[k] = float(v[:-2])
                        except ValueError:
                            pass
            if m:
                metrics[stem] = m
    return metrics


def main() -> None:
    root_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    crops_dir = os.path.join(root_dir, "face_cpp", "testdata", "crops")
    crops = sorted(glob.glob(os.path.join(crops_dir, "*.png")))
    refs_path = os.path.join(root_dir, "face_cpp", "testdata", "references.json")

    results_dir = os.path.join(root_dir, "results")
    os.makedirs(results_dir, exist_ok=True)

    with open(refs_path, "r", encoding="utf-8") as f:
        refs_raw = json.load(f)
    refs = {r["id"]: r for r in refs_raw}

    print(f"[run_all_benchmarks] Found {len(crops)} test crops: {[os.path.basename(c) for c in crops]}")

    benchmark_summary: Dict[str, Any] = {
        "timestamp": time.strftime("%Y-%m-%d %H:%M:%S"),
        "hardware": "Intel(R) UHD Graphics / Intel CPU",
        "backends": {}
    }

    # 1. Benchmark face_dx
    dx_exe = os.path.join(root_dir, "face_dx", "build", "face_dx.exe")
    dx_cwd = os.path.join(root_dir, "face_dx")
    dx_model = "models/w600k_mbf.fvp"
    dx_cmd = [dx_exe, "--model", dx_model, "--bench", "20"] + crops
    dx_out = run_command_capture(dx_cmd, dx_cwd)
    with open(os.path.join(results_dir, "results_dx.log"), "w", encoding="utf-8") as f:
        f.write(dx_out)
    dx_objs = parse_jsonl_output(dx_out)
    dx_bench = parse_bench_lines(dx_out)

    # 2. Benchmark face_cpp CPU
    cpp_exe = os.path.join(root_dir, "face_cpp", "face_extract.exe")
    cpp_cwd = root_dir
    cpp_model = "face_cpp/models/win_fp32"
    cpu_cmd = [cpp_exe, "--cpu", "--model", cpp_model, "--bench", "20"] + crops
    cpu_out = run_command_capture(cpu_cmd, cpp_cwd)
    with open(os.path.join(results_dir, "results_cpu.log"), "w", encoding="utf-8") as f:
        f.write(cpu_out)
    cpu_objs = parse_jsonl_output(cpu_out)
    cpu_bench = parse_bench_lines(cpu_out)

    # 3. Benchmark face_cpp GPU (Vulkan)
    gpu_cmd = [cpp_exe, "--gpu", "--model", cpp_model, "--bench", "20"] + crops
    gpu_out = run_command_capture(gpu_cmd, cpp_cwd)
    with open(os.path.join(results_dir, "results_gpu.log"), "w", encoding="utf-8") as f:
        f.write(gpu_out)
    gpu_objs = parse_jsonl_output(gpu_out)
    gpu_bench = parse_bench_lines(gpu_out)

    # Collate accuracy & latency
    all_engines = [
        ("Direct3D 11 Compute (face_dx)", dx_objs, dx_bench),
        ("ncnn OpenMP CPU (face_cpp)", cpu_objs, cpu_bench),
        ("ncnn Vulkan GPU (face_cpp)", gpu_objs, gpu_bench),
    ]

    for name, objs, bench in all_engines:
        engine_stats = {
            "crops": {},
            "avg_latency_ms": 0.0,
            "mean_cosine_similarity": 0.0
        }
        cosines = []
        lats = []
        for stem, ref in refs.items():
            cand = objs.get(stem)
            b = bench.get(stem, {})
            avg_ms = b.get("avg", cand.get("ms", 0.0) if cand else 0.0)
            sim = cosine_sim(ref["embedding"], cand["embedding"]) if cand else 0.0
            cosines.append(sim)
            lats.append(avg_ms)
            engine_stats["crops"][stem] = {
                "avg_ms": round(avg_ms, 2),
                "min_ms": round(b.get("min", avg_ms), 2),
                "max_ms": round(b.get("max", avg_ms), 2),
                "cosine_similarity": round(sim, 6)
            }
        engine_stats["avg_latency_ms"] = round(sum(lats) / max(1, len(lats)), 2)
        engine_stats["mean_cosine_similarity"] = round(sum(cosines) / max(1, len(cosines)), 6)
        engine_stats["fps"] = round(1000.0 / max(1e-3, engine_stats["avg_latency_ms"]), 1)
        benchmark_summary["backends"][name] = engine_stats

    # Load CelebA benchmark if ready
    celeba_json_path = os.path.join(results_dir, "celeba_benchmark.json")
    if os.path.isfile(celeba_json_path):
        with open(celeba_json_path, "r", encoding="utf-8") as f:
            benchmark_summary["celeba_model_benchmark"] = json.load(f)

    # Save summary
    summary_path = os.path.join(results_dir, "unified_benchmark_summary.json")
    with open(summary_path, "w", encoding="utf-8") as f:
        json.dump(benchmark_summary, f, indent=2)

    print("\n" + "=" * 80)
    print(" UNIFIED BENCHMARK & COMPARISON TABLE")
    print("=" * 80)
    print(f"{'Engine':<32} {'Avg Latency':<14} {'FPS':<10} {'Mean Cosine Sim':<18} {'Precision'}")
    print("-" * 80)
    for name, stats in benchmark_summary["backends"].items():
        print(f"{name:<32} {stats['avg_latency_ms']} ms{'':<6} {stats['fps']:<10} "
              f"{stats['mean_cosine_similarity']:<18.6f} "
              f"{'PERFECT (>0.999)' if stats['mean_cosine_similarity'] > 0.999 else 'HIGH'}")
    print("=" * 80)
    print(f"[run_all_benchmarks] Saved report to {summary_path}")


if __name__ == "__main__":
    main()
