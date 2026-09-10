#!/usr/bin/env python3
"""compare_dump.py — compare FDX_DUMP_DIR tensor dumps against the numpy
reference graph from check_fvp.py. Usage:
    python compare_dump.py <dump_dir> <crop.png> [fvp] [onnx]
"""
import sys
import numpy as np
from PIL import Image

from check_fvp import load_fvp, build_onnx_plan, run_fvp, run_onnx

dump, crop = sys.argv[1], sys.argv[2]
fvp = sys.argv[3] if len(sys.argv) > 3 else "models/w600k_mbf.fvp"
onnx_path = sys.argv[4] if len(sys.argv) > 4 else "models/w600k_mbf.onnx"

import glob, re, os
f16files = sorted(glob.glob(os.path.join(dump, "t*_f161.f32")))
files = sorted(glob.glob(os.path.join(dump, "t*_f160.f32"))) or f16files
tag = "f16" if (not files and f16files) or (files == [] and f16files) else "fp32"
if not files:
    files, tag = f16files, "f16"

im = Image.open(crop).convert("RGB").resize((112, 112))
x = (np.transpose(np.asarray(im, np.float32), (2, 0, 1)) - 127.5) / 127.5

layers = load_fvp(fvp)
plan = build_onnx_plan(onnx_path)
t_onnx = run_onnx(plan, x)
t_fvp = run_fvp(layers, x)

first_bad = None
for f in files:
    i = int(re.search(r"t(\d+)", f).group(1))
    if i >= len(t_onnx):
        break
    mine = np.fromfile(f, dtype=np.float32)
    refi = t_onnx[i].reshape(-1)
    if mine.size != refi.size:
        print(f"t{i:03d}: SIZE {mine.size} vs {refi.size}")
        first_bad = first_bad or i
        continue
    a, b = t_fvp[i].reshape(-1), t_onnx[i].reshape(-1)
    d = np.abs(mine - refi).max()
    dv = np.abs(a - b).max()
    if d > 2e-3:
        print(f"t{i:03d}: maxdiff {d:.4g} (model-vs-onnx {dv:.2e})  <-- DIVERGES")
        if first_bad is None:
            first_bad = i
if first_bad is None:
    print(f"all {len(files)} dumped tensors match (fp32 dump vs onnx, tol 2e-3)")
else:
    print(f"first divergence at tensor {first_bad}")
