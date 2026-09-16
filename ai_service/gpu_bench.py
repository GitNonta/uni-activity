#!/usr/bin/env python3
"""gpu_bench.py — isolate the fdx engine: back-to-back embeddings.

Runs the engine directly (no HTTP, no detection) so its duty cycle
dominates, letting GPU Engine counters attribute the work to an adapter.
Prints a final JSON summary line: {"mode":..., "n":..., "ms_avg":...}

Usage: python gpu_bench.py <seconds> <gpu_index>   (-1 = hardware, -2 = WARP)
"""
import json
import sys
import time
from pathlib import Path

import numpy as np

SECONDS = float(sys.argv[1]) if len(sys.argv) > 1 else 12.0
GPU_INDEX = int(sys.argv[2]) if len(sys.argv) > 2 else -1

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE / ".." / "face_dx" / "pylib"))

import fdx  # noqa: E402

mode = "WARP" if GPU_INDEX == -2 else ("explicit" if GPU_INDEX >= 0 else "auto")
with fdx.FaceEmbedder(gpu_index=GPU_INDEX) as fx:
    desc = fx.describe()
    print(f"engine: {desc}", flush=True)
    img = (np.random.default_rng(7).integers(0, 256, (112, 112, 3), dtype=np.uint8))

    n = 0
    total = 0.0
    t_end = time.monotonic() + SECONDS
    while time.monotonic() < t_end:
        _, ms = fx.embed_ms(img)   # (emb, engine wall ms incl. submit+wait)
        total += ms
        n += 1

    print(json.dumps({
        "mode": mode, "adapter": desc.get("name"), "is_warp": desc.get("is_warp"),
        "n": n, "ms_avg": round(total / max(n, 1), 1),
    }), flush=True)
