#!/usr/bin/env python3
"""check_fvp.py — cross-check the exported .fvp model against the ONNX graph.

Both graphs are run with the SAME numpy conv core (im2col), so differences
isolate export bugs (layer order, tensor edges, params, weight layout) from
math bugs. Prints per-layer max-abs-diff and a final cosine vs the reference.
"""
from __future__ import annotations

import json
import struct
import sys

import numpy as np
import onnx
from onnx import numpy_helper
from PIL import Image

LAYER_CONV = 1
LAYER_ADD = 2
LAYER_GEMM = 3


# ---------------------------------------------------------------------------
# .fvp parsing / execution
# ---------------------------------------------------------------------------
def load_fvp(path: str):
    f = open(path, "rb")
    assert f.read(4) == b"FVK1"
    ver, n_layers = struct.unpack("<II", f.read(8))
    assert ver == 2
    layers = []
    for _ in range(n_layers):
        typ = struct.unpack("<i", f.read(4))[0]
        params = struct.unpack("<12i", f.read(48))
        n_in = struct.unpack("<I", f.read(4))[0]
        inputs = struct.unpack(f"<{n_in}I", f.read(4 * n_in))
        wbytes = struct.unpack("<i", f.read(4))[0]
        w = np.frombuffer(f.read(wbytes), dtype="<u4")
        layers.append({"type": typ, "params": params, "inputs": inputs, "words": w})
    f.close()
    return layers


def conv_np(inp, Wt, bias, kh, kw, sh, sw, ph, pw, group, slope=None):
    """Shared conv core. inp: [C,H,W]; Wt: [out_c, Cg, kh, kw] (ONNX layout).
    General grouped conv: output channel o is in group g = o // out_g and
    reads input channels [g*in_c_g, (g+1)*in_c_g)."""
    in_c, H, W = inp.shape
    out_c, in_c_g = Wt.shape[0], Wt.shape[1]
    out_g = out_c // group
    if slope is not None:
        slope = slope.reshape(-1)
    out_h = (H + 2 * ph - kh) // sh + 1
    out_w = (W + 2 * pw - kw) // sw + 1
    # im2col over the full input: [in_c*kh*kw, out_h*out_w]
    cols = np.zeros((in_c * kh * kw, out_h * out_w), np.float32)
    pad = np.zeros((in_c, H + 2 * ph, W + 2 * pw), np.float32)
    pad[:, ph:ph + H, pw:pw + W] = inp
    for ky in range(kh):
        for kx in range(kw):
            cols[ky * kw + kx::kh * kw] = \
                pad[:, ky:ky + out_h * sh:sh, kx:kx + out_w * sw:sw].reshape(in_c, -1)
    out = np.zeros((out_c, out_h, out_w), np.float32)
    for g in range(group):
        wg = Wt[g * out_g:(g + 1) * out_g].reshape(out_g, in_c_g * kh * kw)
        # cols row = l*(kh*kw) + ky*kw + kx, so channel block l is contiguous
        outc = cols[g * in_c_g * kh * kw:(g + 1) * in_c_g * kh * kw].reshape(in_c_g * kh * kw, -1)
        out[g * out_g:(g + 1) * out_g] = (wg @ outc).reshape(out_g, out_h, out_w)
    if bias is not None:
        out += bias[:, None, None]
    if slope is not None:
        out = np.where(out > 0, out, out * slope[:, None, None])
    return out


def run_fvp(layers, x, verbose=False):
    tensors = [x]
    for li, L in enumerate(layers):
        tin = L["inputs"][0]
        tin = 0 if tin == 0xFFFFFFFF else int(tin)
        inp = tensors[tin]
        w = L["words"].view("<f4")
        if L["type"] == LAYER_CONV:
            in_c, out_c = L["params"][0], L["params"][1]
            kh, kw = L["params"][2], L["params"][3]
            sh, sw = L["params"][4], L["params"][5]
            ph, pw = L["params"][6], L["params"][7]
            group = L["params"][8]
            has_bias, prelu = L["params"][9], L["params"][10]
            nw = out_c * (in_c // group) * kh * kw
            Wt = w[:nw].reshape(out_c, in_c // group, kh, kw)
            bias = w[nw:nw + out_c] if has_bias else None
            slope = w[nw + out_c:nw + 2 * out_c] if prelu else None
            out = conv_np(inp, Wt, bias, kh, kw, sh, sw, ph, pw, group, slope)
            tensors.append(out)
        elif L["type"] == LAYER_GEMM:
            out_c, k = L["params"][0], L["params"][1]
            nw = out_c * k
            # exporter stores the weight transposed [k][out_c]; restore [out_c][k]
            Wt = w[:nw].reshape(k, out_c).T
            bias = w[nw:nw + out_c]
            tensors.append(Wt @ inp.reshape(-1)[:k] + bias)
        else:
            tin1 = L["inputs"][1]
            tin1 = 0 if tin1 == 0xFFFFFFFF else int(tin1)
            tensors.append(inp + tensors[tin1])
    return tensors


# ---------------------------------------------------------------------------
# ONNX graph execution (same conv core)
# ---------------------------------------------------------------------------
def build_onnx_plan(path: str):
    """Return list of ('conv'|'add'|'gemm', inputs:list[str], blob_shapes) and
    a blob->array map filled by run_onnx. PRelu/BN handled by folding like the
    exporter does, so node order matches the .fvp layers exactly."""
    m = onnx.load(path)
    g = m.graph
    init = {i.name: numpy_helper.to_array(i) for i in g.initializer}
    nodes = list(g.node)

    blob_shapes = {g.input[0].name: [1, 3, 112, 112]}
    plan = []
    i = 0
    while i < len(nodes):
        n = nodes[i]
        ot = n.op_type
        if ot == "Conv":
            w = init[n.input[1]]
            b = init[n.input[2]] if len(n.input) > 2 else None
            a = {x.name: x for x in n.attribute}
            strides = list(a["strides"].ints) if "strides" in a else [1, 1]
            pads = list(a["pads"].ints) if "pads" in a else [0, 0, 0, 0]
            group = a["group"].i if "group" in a else 1
            kh, kw = w.shape[2], w.shape[3]
            out_c, in_c_g = w.shape[0], w.shape[1]
            in_h, in_w = blob_shapes[n.input[0]][2], blob_shapes[n.input[0]][3]
            out_h = (in_h + pads[0] + pads[2] - kh) // strides[0] + 1
            out_w = (in_w + pads[1] + pads[3] - kw) // strides[1] + 1
            prelu = None
            out_aliases = [n.output[0]]
            if i + 1 < len(nodes) and nodes[i + 1].op_type == "PRelu":
                prelu = init[nodes[i + 1].input[1]]
                out_aliases.append(nodes[i + 1].output[0])
                blob_shapes[nodes[i + 1].output[0]] = [1, out_c, out_h, out_w]
                i += 1
            plan.append({
                "op": "conv", "in": n.input[0], "out": out_aliases,
                "w": w, "b": b, "slope": prelu,
                "strides": strides, "pads": pads, "group": group,
            })
            blob_shapes[n.output[0]] = [1, out_c, out_h, out_w]
        elif ot == "Add":
            plan.append({"op": "add", "in": list(n.input[:2]), "out": [n.output[0]]})
            blob_shapes[n.output[0]] = blob_shapes[n.input[0]]
        elif ot == "Flatten":
            # identity view: alias the flatten output to its input blob
            plan.append({"op": "alias", "in": n.input[0], "out": [n.output[0]]})
        elif ot == "Gemm":
            w = init[n.input[1]]
            b = init[n.input[2]]
            scale, shift, mean, var = None, None, None, None
            if i + 1 < len(nodes) and nodes[i + 1].op_type == "BatchNormalization":
                bn = nodes[i + 1]
                scale, shift = init[bn.input[1]], init[bn.input[2]]
                mean, var = init[bn.input[3]], init[bn.input[4]]
                eps = next((x.f for x in bn.attribute if x.name == "epsilon"), 1e-5)
                i += 1
            w2, b2 = w.copy(), b.copy()
            if scale is not None:
                inv = scale / np.sqrt(var + eps)
                w2 = w2 * inv[:, None]
                b2 = (b2 - mean) * inv + shift
            plan.append({"op": "gemm", "in": n.input[0], "out": [n.output[0]], "w": w2, "b": b2})
            blob_shapes[n.output[0]] = [1, w2.shape[0]]
        elif ot in ("BatchNormalization", "PRelu"):
            raise SystemExit(f"unhandled {ot} at node {i}")
        else:
            raise SystemExit(f"unhandled {ot}")
        i += 1
    return plan


def run_onnx(plan, x):
    """Run the ONNX plan; tensor id = index in `tensors`, same numbering as the
    .fvp (tensor 0 = model input, layer j writes tensor j+1)."""
    blob_id = {plan[0]["in"]: 0}  # first conv input = model input blob
    tensors = [x]
    for pi, p in enumerate(plan):
        if p["op"] == "conv":
            inp = tensors[blob_id[p["in"]]]
            if len(inp.shape) != 3:
                print(f"plan {pi}: conv input {p['in']} has shape {inp.shape}")
            out = conv_np(inp, p["w"], p["b"], p["w"].shape[2], p["w"].shape[3],
                          p["strides"][0], p["strides"][1], p["pads"][0], p["pads"][1],
                          p["group"], p["slope"])
            tid = len(tensors)
            for name in p["out"]:
                blob_id[name] = tid
            tensors.append(out)
        elif p["op"] == "add":
            out = tensors[blob_id[p["in"][0]]] + tensors[blob_id[p["in"][1]]]
            tid = len(tensors)
            for name in p["out"]:
                blob_id[name] = tid
            tensors.append(out)
        elif p["op"] == "alias":
            tid = blob_id[p["in"]]
            for name in p["out"]:
                blob_id[name] = tid
        else:
            out = p["w"] @ tensors[blob_id[p["in"]]].reshape(-1) + p["b"]
            tid = len(tensors)
            for name in p["out"]:
                blob_id[name] = tid
            tensors.append(out)
    return tensors


def main() -> None:
    fvp = sys.argv[1] if len(sys.argv) > 1 else "models/w600k_mbf.fvp"
    onnx_path = sys.argv[2] if len(sys.argv) > 2 else "models/w600k_mbf.onnx"
    ref_json = sys.argv[3] if len(sys.argv) > 3 else "../face_cpp/testdata/references.json"

    layers = load_fvp(fvp)
    plan = build_onnx_plan(onnx_path)
    print(f".fvp layers={len(layers)}  onnx plan nodes={len(plan)}")

    refs = json.load(open(ref_json))
    r = refs[0]
    im = Image.open(r["crop"]).convert("RGB").resize((112, 112))
    x = (np.transpose(np.asarray(im, np.float32), (2, 0, 1)) - 127.5) / 127.5

    t_fvp = run_fvp(layers, x)
    t_onnx = run_onnx(plan, x)

    worst = 0.0
    for li in range(min(len(t_fvp), len(t_onnx)) - 1):
        a, b = t_fvp[li + 1], t_onnx[li + 1]
        d = float(np.max(np.abs(a - b)))
        worst = max(worst, d)
        if d > 1e-4:
            print(f"layer {li:2d} fvp[{a.shape}] vs onnx[{b.shape}] maxdiff={d:.4e}")
    print(f"worst layer diff = {worst:.4e}")

    emb = t_fvp[-1]
    emb = emb / np.linalg.norm(emb)
    ref = np.asarray(r["embedding"], np.float32)
    ref = ref / np.linalg.norm(ref)
    print(f"fvp cosine vs reference = {float(np.dot(emb, ref)):.6f}")


if __name__ == "__main__":
    main()