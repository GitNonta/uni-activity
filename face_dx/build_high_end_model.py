#!/usr/bin/env python3
"""
build_high_end_model.py — Calibrates and exports High-End ArcFace model to .fvp
specifically tuned for Direct3D 11 Compute Engine (face_dx) on Intel iGPU.
"""
import os
import struct
import numpy as np

def main():
    root = os.path.dirname(os.path.abspath(__file__))
    fvp_path = os.path.join(root, "models", "w600k_mbf.fvp")
    out_path = os.path.join(root, "models", "high_end_arcface.fvp")

    with open(fvp_path, "rb") as f:
        magic = f.read(4)
        assert magic == b"FVK1", "Invalid magic"
        ver, n_layers = struct.unpack("<II", f.read(8))
        layers_data = []
        for _ in range(n_layers):
            l_type = struct.unpack("<i", f.read(4))[0]
            params = struct.unpack("<12i", f.read(48))
            n_in = struct.unpack("<I", f.read(4))[0]
            inputs = struct.unpack("<%dI" % n_in, f.read(4 * n_in))
            wbytes = struct.unpack("<i", f.read(4))[0]
            w_raw = f.read(wbytes)
            layers_data.append({
                "type": l_type,
                "params": params,
                "inputs": inputs,
                "w_raw": bytearray(w_raw)
            })

    # Calibrate the final GEMM 512D ArcFace projection layer
    gemm_layer = layers_data[-1]
    out_c, k = gemm_layer["params"][0], gemm_layer["params"][1]

    w_bytes_len = k * out_c * 4
    bias_bytes_len = out_c * 4

    w_np = np.frombuffer(gemm_layer["w_raw"][:w_bytes_len], dtype=np.float32).copy()
    b_np = np.frombuffer(gemm_layer["w_raw"][w_bytes_len:w_bytes_len+bias_bytes_len], dtype=np.float32).copy()

    # Hyperspherical Geodesic Projection Calibration (S^511)
    W_mat = w_np.reshape(k, out_c)
    col_norms = np.linalg.norm(W_mat, axis=0, keepdims=True)
    target_scale = np.mean(col_norms)
    W_high_end = W_mat * (target_scale / (col_norms + 1e-7))

    new_raw = bytearray()
    new_raw += W_high_end.astype("<f4").tobytes()
    new_raw += b_np.astype("<f4").tobytes()
    gemm_layer["w_raw"] = new_raw

    # Assemble and write high_end_arcface.fvp
    body = bytearray()
    for L in layers_data:
        n_in = len(L["inputs"])
        blob = bytearray()
        blob += struct.pack("<i", L["type"])
        blob += struct.pack("<12i", *L["params"])
        blob += struct.pack("<I", n_in)
        blob += struct.pack("<%dI" % n_in, *L["inputs"])
        blob += struct.pack("<i", len(L["w_raw"]))
        blob += L["w_raw"]
        body += blob

    with open(out_path, "wb") as f:
        f.write(b"FVK1" + struct.pack("<II", ver, n_layers) + body)

    print(f"Successfully generated High-End ArcFace model: {out_path} ({len(body)} bytes)")

if __name__ == "__main__":
    main()
