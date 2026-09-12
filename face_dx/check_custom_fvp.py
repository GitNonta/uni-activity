#!/usr/bin/env python3
"""
check_custom_fvp.py — numerical verifier for custom_arcface.fvp.

Parses the exported .fvp (FVK1 v2) and re-executes the graph with a numpy
im2col conv core (same approach as check_fvp.py), then compares against a
PyTorch re-execution of NativeArcFaceNet in exact serialize order.

Verifies:
  1. per-layer tensor SHAPES match the torch graph
  2. per-layer max-abs-diff within fp32 tolerance
  3. final GEMM output cosine vs the torch raw (pre-normalization) output

Usage:
  python check_custom_fvp.py                       # random-weight smoke test
  python check_custom_fvp.py --ckpt models/custom_arcface.pt \
                            --fvp  models/custom_arcface.fvp   # trained export
"""
from __future__ import annotations

import argparse
import os
import struct
import sys
import tempfile

import numpy as np
import torch
import torch.nn.functional as F

from train_custom_arcface import (
    INPUT_TENSOR, LAYER_ADD, LAYER_CONV, LAYER_GEMM, MAGIC,
    NativeArcFaceNet, serialize_model_to_fvp,
)

INPUT_SHAPE = (1, 3, 112, 112)


# ---------------------------------------------------------------------------
# .fvp parsing / execution (shape resolution mirrors dx_engine.cpp load_model)
# ---------------------------------------------------------------------------
def load_fvp(path: str):
    with open(path, "rb") as f:
        assert f.read(4) == MAGIC, "bad magic"
        ver, n_layers = struct.unpack("<II", f.read(8))
        assert ver == 2, f"unsupported version {ver}"
        layers = []
        for _ in range(n_layers):
            typ = struct.unpack("<i", f.read(4))[0]
            params = struct.unpack("<12i", f.read(48))
            n_in = struct.unpack("<I", f.read(4))[0]
            inputs = struct.unpack(f"<{n_in}I", f.read(4 * n_in))
            wbytes = struct.unpack("<i", f.read(4))[0]
            words = np.frombuffer(f.read(wbytes), dtype="<u4")
            layers.append({"type": typ, "params": params, "inputs": inputs, "words": words})
    return layers


def conv_np(inp: np.ndarray, Wt: np.ndarray, bias, kh, kw, sh, sw, ph, pw,
            group: int, slope=None) -> np.ndarray:
    """Grouped im2col conv. inp: [C,H,W]; Wt: [out_c, in_c/g, kh, kw]."""
    in_c, H, W = inp.shape
    out_c, in_c_g = Wt.shape[0], Wt.shape[1]
    out_h = (H + 2 * ph - kh) // sh + 1
    out_w = (W + 2 * pw - kw) // sw + 1
    cols = np.zeros((in_c * kh * kw, out_h * out_w), np.float32)
    pad = np.zeros((in_c, H + 2 * ph, W + 2 * pw), np.float32)
    pad[:, ph:ph + H, pw:pw + W] = inp
    for ky in range(kh):
        for kx in range(kw):
            cols[ky * kw + kx::kh * kw] = \
                pad[:, ky:ky + out_h * sh:sh, kx:kx + out_w * sw:sw].reshape(in_c, -1)
    out_g = out_c // group
    out = np.zeros((out_c, out_h, out_w), np.float32)
    for g in range(group):
        wg = Wt[g * out_g:(g + 1) * out_g].reshape(out_g, in_c_g * kh * kw)
        outc = cols[g * in_c_g * kh * kw:(g + 1) * in_c_g * kh * kw]
        out[g * out_g:(g + 1) * out_g] = (wg @ outc).reshape(out_g, out_h, out_w)
    if bias is not None:
        out += bias[:, None, None]
    if slope is not None:
        out = np.where(out > 0, out, out * slope[:, None, None])
    return out


def run_fvp(layers, x):
    """Execute the graph. Returns (tensor list, per-layer output tensor ids).
    Tensor 0 = model input; layer j writes tensor j+1 (engine convention)."""
    shapes = [list(INPUT_SHAPE)]
    counts = [int(np.prod(INPUT_SHAPE))]
    tensors: list[np.ndarray] = [x[0]]  # tensor 0 = model input, [C,H,W]
    layer_out = []

    for li, L in enumerate(layers):
        tin = L["inputs"][0]
        tin = 0 if tin == INPUT_TENSOR else int(tin)
        ish = shapes[tin]
        w = L["words"].view("<f4")
        if L["type"] == LAYER_CONV:
            in_c, out_c, kh, kw = L["params"][0], L["params"][1], L["params"][2], L["params"][3]
            sh, sw, ph, pw = L["params"][4], L["params"][5], L["params"][6], L["params"][7]
            group, has_bias, has_prelu = L["params"][8], L["params"][9], L["params"][10]
            out_h = (ish[2] + 2 * ph - kh) // sh + 1
            out_w = (ish[3] + 2 * pw - kw) // sw + 1
            nw = out_c * (in_c // group) * kh * kw
            Wt = w[:nw].reshape(out_c, in_c // group, kh, kw)
            bias = w[nw:nw + out_c] if has_bias else None
            slope = w[nw + out_c:nw + 2 * out_c] if has_prelu else None
            inp = tensors[tin]  # [C,H,W] for the conv core
            out = conv_np(inp, Wt, bias, kh, kw, sh, sw, ph, pw,
                          group, slope.reshape(-1) if slope is not None else None)
            osh, ocount = [1, out_c, out_h, out_w], out_c * out_h * out_w
        elif L["type"] == LAYER_GEMM:
            out_c, k = L["params"][0], L["params"][1]
            nw = out_c * k
            # stored transposed [k][out_c]; restore [out_c][k]
            Wt = w[:nw].reshape(k, out_c).T
            bias = w[nw:nw + out_c]
            flat = tensors[tin].reshape(-1)
            assert flat.size == k, f"gemm expects k={k}, got {flat.size}"
            out = Wt @ flat + bias
            osh, ocount = [1, out_c, 1, 1], out_c
        else:  # ADD
            tin1 = L["inputs"][1]
            tin1 = 0 if tin1 == INPUT_TENSOR else int(tin1)
            out = tensors[tin] + tensors[tin1]
            osh = [1] + list(out.shape)  # engine-format [1,C,H,W]
            ocount = int(out.size)
        tensors.append(out)
        shapes.append(osh)
        counts.append(ocount)
        layer_out.append(len(tensors))  # tensor id = li+1

    assert tensors[-1].size == 512, f"final output {tensors[-1].size} != 512"
    return tensors, layer_out


# ---------------------------------------------------------------------------
# torch reference: re-execute the graph in EXACT serialize order
# ---------------------------------------------------------------------------
def run_torch_graph(model: NativeArcFaceNet, x: torch.Tensor):
    """Returns one tensor per .fvp layer (convs, adds, gemm) in serialize order."""
    outs: list[torch.Tensor] = []

    def conv(mod, t, prelu):
        y = mod(t)
        if prelu is not None:
            y = prelu(y)
        outs.append(y)
        return y

    x = conv(model.stem_conv, x, model.stem_prelu)
    x = conv(model.dw0_conv, x, model.dw0_prelu)
    x = conv(model.conv0, x, model.prelu0)

    def stage(blocks, down0, down2):
        nonlocal x
        x = conv(down0[0], x, down0[1])
        x = conv(down2, x, None)
        for b in blocks:
            r = x
            x = conv(b.conv1, x, b.prelu1)
            x = conv(b.conv2, x, b.prelu2)
            x = conv(b.conv3, x, None)
            x = r + x
            outs.append(x)  # ADD layer

    stage(model.stage1_blocks, model.block1_down[:2], model.block1_down[2])
    x = conv(model.conv_trans1[0], x, model.conv_trans1[1])
    stage(model.stage2_blocks, model.block2_down[:2], model.block2_down[2])
    x = conv(model.conv_trans2[0], x, model.conv_trans2[1])
    stage(model.stage3_blocks, model.block3_down[:2], model.block3_down[2])
    x = conv(model.head_conv1[0], x, model.head_conv1[1])
    x = conv(model.head_conv2[0], x, model.head_conv2[1])
    x = torch.flatten(x, 1)
    raw = model.fc(x)  # GEMM (pre-normalization, matches the .fvp graph)
    outs.append(raw)
    return outs


# ---------------------------------------------------------------------------
# comparison
# ---------------------------------------------------------------------------
def verify(fvp_path: str, model: NativeArcFaceNet, seed_input: int = 7) -> bool:
    layers = load_fvp(fvp_path)
    g = torch.Generator().manual_seed(seed_input)
    x = torch.rand(INPUT_SHAPE, generator=g) * 2.0 - 1.0
    x_np = x.numpy().astype(np.float32)

    t_fvp, layer_out = run_fvp(layers, x_np)
    t_torch = run_torch_graph(model, x)

    if len(layers) != len(t_torch):
        print(f"FAIL: layer count {len(layers)} != torch graph {len(t_torch)}")
        return False

    ok = True
    worst = {}
    for li, L in enumerate(layers):
        a = t_fvp[layer_out[li] - 1].reshape(-1)
        b = t_torch[li].detach().numpy().reshape(-1)
        if a.shape != b.shape:
            print(f"FAIL: layer {li} shape {a.shape} != torch {b.shape}")
            ok = False
            continue
        d = float(np.max(np.abs(a - b))) if a.size else 0.0
        kind = {LAYER_CONV: "conv", LAYER_ADD: "add", LAYER_GEMM: "gemm"}.get(L["type"], "?")
        if kind not in worst or d > worst[kind][0]:
            worst[kind] = (d, li)
        if d > 2e-3:
            print(f"FAIL: layer {li} ({kind}) maxdiff {d:.4e}")
            ok = False

    for kind, (d, li) in sorted(worst.items()):
        print(f"  worst {kind:4s}: layer {li:2d} maxdiff {d:.4e}")

    # final GEMM: cosine between .fvp output and torch raw output
    a = t_fvp[-1].reshape(-1)
    b = t_torch[-1].detach().numpy().reshape(-1)
    cos = float(np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b)))
    print(f"  gemm cosine vs torch raw = {cos:.8f}")
    if cos < 0.999999:
        print("FAIL: gemm cosine below 0.999999")
        ok = False

    # embedding normalization sanity (engine L2-normalizes before returning)
    n = float(np.linalg.norm(t_fvp[-1]))
    print(f"  raw gemm output norm = {n:.6f}")
    print("PASS" if ok else "FAIL", f"({len(layers)} layers verified)")
    return ok


def main() -> None:
    ap = argparse.ArgumentParser(description="Verify custom_arcface.fvp against the torch model")
    ap.add_argument("--ckpt", help="trained .pt checkpoint to load (default: random weights)")
    ap.add_argument("--fvp", help="exported .fvp to verify AND byte-compare against a fresh export")
    ap.add_argument("--seed", type=int, default=7, help="input seed")
    args = ap.parse_args()

    torch.manual_seed(1234)
    model = NativeArcFaceNet(embedding_dim=512)
    if args.ckpt:
        model.load_state_dict(torch.load(args.ckpt, map_location="cpu", weights_only=True))
        print(f"loaded checkpoint {args.ckpt}")
    model.eval()

    if args.fvp and os.path.isfile(args.fvp):
        target = args.fvp
    else:
        target = os.path.join(tempfile.gettempdir(), "custom_arcface_check.fvp")
        serialize_model_to_fvp(model, target)
        print(f"freshly exported {target}")

    if args.fvp and os.path.isfile(args.fvp) and args.ckpt:
        fresh = target + ".fresh"
        serialize_model_to_fvp(model, fresh)
        with open(fresh, "rb") as f1, open(args.fvp, "rb") as f2:
            same = f1.read() == f2.read()
        os.remove(fresh)
        print(f"byte-identical with fresh export: {same}")
        if not same:
            print("WARNING: exported file is stale (older than the checkpoint)")

    ok = verify(target, model, seed_input=args.seed)
    sys.exit(0 if ok else 1)


if __name__ == "__main__":
    main()
