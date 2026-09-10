#!/usr/bin/env python3
"""
compare_embeddings.py — validate the C++ (ncnn) extractor against the Python
(insightface) reference.

Usage:
    compare_embeddings.py references.json results_cpp.jsonl [results_gpu.jsonl ...]

references.json : from gen_references.py  ({id, crop, embedding, ...})
results_*.jsonl : JSON-lines output of face_extract   ({id, backend, fp16, ms, embedding})
"""
from __future__ import annotations

import json
import math
import os
import sys


def load_refs(path: str) -> dict:
    with open(path, encoding="utf-8") as f:
        data = json.load(f)
    return {r["id"]: r for r in data}


def load_results(path: str) -> dict:
    out = {}
    with open(path, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("{"):
                try:
                    obj = json.loads(line)
                except json.JSONDecodeError:
                    continue
                out[obj["id"]] = obj
    return out


def cosine(a, b) -> float:
    assert len(a) == len(b)
    dot = sum(x * y for x, y in zip(a, b))
    na = math.sqrt(sum(x * x for x in a))
    nb = math.sqrt(sum(x * x for x in b))
    return dot / (na * nb + 1e-12)


def main() -> None:
    if len(sys.argv) < 3:
        print(__doc__)
        sys.exit(2)
    refs = load_refs(sys.argv[1])
    result_files = sys.argv[2:]

    all_rows = []
    for rf in result_files:
        results = load_results(rf)
        for rid, ref in refs.items():
            cand = None
            for key, obj in results.items():
                if os.path.splitext(os.path.basename(key))[0] == rid:
                    cand = obj
                    break
            if cand is None:
                continue
            sim = cosine(ref["embedding"], cand["embedding"])
            all_rows.append({
                "id": rid,
                "file": os.path.basename(rf),
                "backend": cand.get("backend", "?"),
                "fp16": cand.get("fp16", False),
                "ms": cand.get("ms", 0.0),
                "cosine": sim,
            })

    if not all_rows:
        print("!! no matching rows — check ids in references vs results")
        sys.exit(1)

    print(f"{'id':<14} {'backend':<7} {'fp16':<5} {'ms':>8} {'cosine':>10}")
    print("-" * 52)
    groups: dict = {}
    for r in sorted(all_rows, key=lambda r: (r["file"], r["id"])):
        print(f"{r['id']:<14} {r['backend']:<7} {str(r['fp16']):<5} {r['ms']:>8.2f} {r['cosine']:>10.6f}")
        key = (r["file"], r["backend"], r["fp16"])
        groups.setdefault(key, []).append(r["cosine"])

    print("-" * 52)
    for (file, backend, fp16), sims in groups.items():
        print(f"{file} {backend}/fp16={fp16}: n={len(sims)} mean={sum(sims)/len(sims):.6f} "
              f"min={min(sims):.6f} max={max(sims):.6f}")


if __name__ == "__main__":
    main()