#!/usr/bin/env python3
"""
export_model.py — MobileFaceNet ONNX -> custom face_vk model format.

My own compact binary format (.fvp):
  magic "FVK1", u32 version, u32 n_layers, then per layer:
    u32 type (1=CONV 2=ADD 3=GEMM)
    i32 params[12]
    u32 n_inputs, u32 producer_layer_idx[n_inputs] (0xFFFFFFFF = model input)
    u32 weight_bytes + weight blob

Design choices:
  * PRelu is folded into the preceding Conv epilogue (prelu_flag + slope).
  * BatchNorm after Gemm is folded into the Gemm epilogue (scale+shift).
  * Flatten is a no-op (NCHW layout is already contiguous).
  * fp16 variant packs every 2 floats into one u32 via packHalf2x16
    (activations AND weights), for the packed-fp16 GPU path.
"""
from __future__ import annotations

import argparse
import json
import os
import struct

import numpy as np
import onnx
from onnx import numpy_helper

MAGIC = b"FVK1"
VERSION = 2
LAYER_CONV = 1
LAYER_ADD = 2
LAYER_GEMM = 3


def to_f32(a: np.ndarray) -> np.ndarray:
    return np.asarray(a, dtype=np.float32).reshape(-1)


def pack_fp16(a: np.ndarray) -> np.ndarray:
    """Pack pairs of floats into u32 (little-endian: even idx = low half)."""
    f = np.asarray(a, dtype=np.float32).reshape(-1)
    if f.size % 2 == 1:
        f = np.concatenate([f, np.zeros(1, np.float32)])
    h = f.astype(np.float16).view(np.uint16)
    lo = h[0::2].astype(np.uint32)
    hi = h[1::2].astype(np.uint32)
    return lo | (hi << 16)


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--onnx", default=os.path.join(os.path.dirname(__file__), "models", "w600k_mbf.onnx"))
    ap.add_argument("--out-fp32", default=os.path.join(os.path.dirname(__file__), "models", "w600k_mbf.fvp"))
    ap.add_argument("--out-fp16", default=os.path.join(os.path.dirname(__file__), "models", "w600k_mbf_f16.fvp"))
    ap.add_argument("--manifest", default=os.path.join(os.path.dirname(__file__), "models", "layers.json"))
    args = ap.parse_args()

    m = onnx.load(args.onnx)
    g = m.graph
    init = {i.name: numpy_helper.to_array(i) for i in g.initializer}
    nodes = list(g.node)

    # sanity: exactly one input [1,3,112,112], one output
    inp = g.input[0]
    print(f"input {inp.name} {[d.dim_value for d in inp.type.tensor_type.shape.dim]}")

    # blob -> ONNX node index that produced it (for the input-graph)
    producer = {g.input[0].name: -1}
    for idx, nd in enumerate(nodes):
        producer[nd.output[0]] = idx
    # ONNX node index -> exported layer index (PRelu folding shifts indices)
    onnx_to_layer = {}

    layers = []  # list of dicts for the manifest + serialization
    blob_shapes = {inp.name: [1, 3, 112, 112]}

    # producer exported-layer index -> tensor id. Tensor 0 is the model
    # input; layer j writes tensor j+1, so an input referencing producer
    # layer j must carry tensor id j+1. -1 stays -1 (model input).
    def tensor_id(producer_layer: int) -> int:
        return -1 if producer_layer < 0 else producer_layer + 1

    def shape_of(name: str) -> list:
        return blob_shapes.get(name, [])

    i = 0
    while i < len(nodes):
        n = nodes[i]
        ot = n.op_type
        out_name = n.output[0]

        if ot == "Conv":
            w = init[n.input[1]]
            b = init[n.input[2]] if len(n.input) > 2 else None
            a = {x.name: x for x in n.attribute}
            strides = list(a["strides"].ints) if "strides" in a else [1, 1]
            pads = list(a["pads"].ints) if "pads" in a else [0, 0, 0, 0]
            group = a["group"].i if "group" in a else 1
            kh, kw = w.shape[2], w.shape[3]
            out_c, in_c_g = w.shape[0], w.shape[1]
            in_c = in_c_g * group
            out_h = (shape_of(n.input[0])[2] + pads[0] + pads[2] - kh) // strides[0] + 1
            out_w = (shape_of(n.input[0])[3] + pads[1] + pads[3] - kw) // strides[1] + 1

            # fold a following PRelu into this conv epilogue
            prelu = None
            if i + 1 < len(nodes) and nodes[i + 1].op_type == "PRelu":
                prelu = init[nodes[i + 1].input[1]]
                assert prelu.shape[0] == out_c, (prelu.shape, out_c)
                i += 1
                blob_shapes[nodes[i].output[0]] = [1, out_c, out_h, out_w]

            layers.append({
                "type": LAYER_CONV, "name": n.name,
                "inputs": [tensor_id(onnx_to_layer.get(producer[x], -1)) for x in n.input[:1]],
                "in_c": in_c, "out_c": out_c, "kh": kh, "kw": kw,
                "onnx_idx": i,
                "stride_h": strides[0], "stride_w": strides[1],
                "pad_h": pads[0], "pad_w": pads[1], "group": group,
                "has_bias": int(b is not None),
                "prelu": (to_f32(prelu) if prelu is not None else None),
                "weights": to_f32(w),
                "bias": (to_f32(b) if b is not None else None),
                "out_shape": [1, out_c, out_h, out_w],
            })
            blob_shapes[out_name] = [1, out_c, out_h, out_w]

        elif ot == "Add":
            layers.append({"type": LAYER_ADD, "name": n.name,
                           "inputs": [tensor_id(onnx_to_layer.get(producer[x], -1)) for x in n.input[:2]],
                           "onnx_idx": i,
                           "out_shape": shape_of(n.input[0])})
            blob_shapes[out_name] = shape_of(n.input[0])

        elif ot == "Flatten":
            blob_shapes[out_name] = shape_of(n.input[0])  # identity view

        elif ot == "Gemm":
            w = init[n.input[1]]  # (512, 3136)
            b = init[n.input[2]]
            # fold the trailing BatchNormalization into the Gemm epilogue
            scale, shift, mean, var = None, None, None, None
            if i + 1 < len(nodes) and nodes[i + 1].op_type == "BatchNormalization":
                bn = nodes[i + 1]
                scale = init[bn.input[1]]
                shift = init[bn.input[2]]
                mean = init[bn.input[3]]
                var = init[bn.input[4]]
                eps = next((x.f for x in bn.attribute if x.name == "epsilon"), 1e-5)
                i += 1

            out_c = w.shape[0]
            k = w.shape[1]
            w2 = w.copy()
            b2 = b.copy()
            if scale is not None:
                inv = scale / np.sqrt(var + eps)
                w2 = w2 * inv[:, None]
                b2 = (b2 - mean) * inv + shift

            layers.append({
                "type": LAYER_GEMM, "name": n.name,
                "inputs": [tensor_id(onnx_to_layer.get(producer[x], -1)) for x in n.input[:1]],
                "onnx_idx": i,
                "out_c": out_c, "k": k,
                "weights": to_f32(w2), "bias": to_f32(b2),
                "out_shape": [1, out_c],
            })
            blob_shapes[out_name] = [1, out_c]

        elif ot == "BatchNormalization":
            raise SystemExit("unhandled BatchNormalization (not right after Gemm)")

        elif ot == "PRelu":
            raise SystemExit(f"unhandled PRelu at {n.name} (not after Conv)")

        else:
            raise SystemExit(f"unhandled op {ot} at {n.name}")

        onnx_to_layer[i] = len(layers) - 1
        if ot == "Conv" and prelu is not None:
            onnx_to_layer[i - 1] = len(layers) - 1  # folded PRelu maps to same layer
        i += 1

    # ---- serialize fp32 ----
    def ser(layer, fp16: bool):
        w = layer["weights"] if "weights" in layer else None
        b = layer["bias"] if "bias" in layer else None
        p = layer["prelu"] if "prelu" in layer else None
        blob = bytearray()
        blob += struct.pack("<i", layer["type"])
        if layer["type"] == LAYER_CONV:
            params = [layer["in_c"], layer["out_c"], layer["kh"], layer["kw"],
                      layer["stride_h"], layer["stride_w"], layer["pad_h"], layer["pad_w"],
                      layer["group"], layer["has_bias"], 0, 0]
            if p is not None:
                params[10] = 1
        elif layer["type"] == LAYER_GEMM:
            params = [layer["out_c"], layer["k"], 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
        else:  # ADD
            params = [0] * 12
        blob += struct.pack("<12i", *params)
        blob += struct.pack("<I", len(layer["inputs"]))
        blob += struct.pack("<%dI" % len(layer["inputs"]), *[u & 0xFFFFFFFF for u in layer["inputs"]])

        wbytes = bytearray()
        if layer["type"] == LAYER_CONV:
            if fp16:
                wbytes += pack_fp16(w).tobytes()
                if b is not None:
                    wbytes += pack_fp16(b).tobytes()
                if p is not None:
                    wbytes += pack_fp16(p).tobytes()
            else:
                wbytes += w.astype("<f4").tobytes()
                if b is not None:
                    wbytes += b.astype("<f4").tobytes()
                if p is not None:
                    wbytes += p.astype("<f4").tobytes()
        elif layer["type"] == LAYER_GEMM:
            # store the gemm weight transposed [k][out_c] so the GPU kernel's
            # float4 loads (one per output-channel quad) are contiguous in
            # memory; the engine uploads this layout verbatim (no CPU transpose)
            wt = w.reshape(layer["out_c"], layer["k"]).T.copy().reshape(-1)
            if fp16:
                wbytes += pack_fp16(wt).tobytes()
                wbytes += pack_fp16(b).tobytes()
            else:
                wbytes += wt.astype("<f4").tobytes()
                wbytes += b.astype("<f4").tobytes()
        blob += struct.pack("<i", len(wbytes))
        blob += wbytes
        return bytes(blob)

    for fname, fp16 in ((args.out_fp32, False), (args.out_fp16, True)):
        body = b"".join(ser(L, fp16) for L in layers)
        with open(fname, "wb") as f:
            f.write(MAGIC + struct.pack("<II", VERSION, len(layers)) + body)
        print(f"wrote {fname} ({len(body)} bytes, {len(layers)} layers)")

    with open(args.manifest, "w", encoding="utf-8") as f:
        json.dump([{k: (v.tolist() if isinstance(v, np.ndarray) else v) for k, v in L.items() if k != "weights"}
                   for L in layers], f, indent=1)
    print(f"wrote {args.manifest}")


if __name__ == "__main__":
    main()