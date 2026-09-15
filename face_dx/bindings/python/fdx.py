#!/usr/bin/env python3
"""fdx.py — zero-build ctypes binding for face_dx.dll (FDX C ABI v1).

Library use:
    from fdx import Fdx
    fdx = Fdx(r"..\..\build\face_dx.dll")            # ABI-gates the DLL
    model  = fdx.load_model(r"..\..\models\w600k_mbf.fvp")
    engine = fdx.create_engine(fp16=True)
    emb, ms = fdx.run(engine, model, rgb_bytes)       # 112*112*3 RGB8 bytes
    fdx.free_engine(engine); fdx.free_model(model)

Runner CLI (used by the cross-language test; mirrors fdx_runner.c):
    python fdx.py --dll PATH --model FVP --list RAWS.TXT --out OUT.JSONL
                  [--fp16] [--probe-errors]
"""
from __future__ import annotations

import argparse
import ctypes as C
import json
import os
import sys
import time

import numpy as np

FDX_ABI_VERSION = 1
FDX_INPUT_FLOATS = 37632   # 3*112*112 NCHW RGB, (x-127.5)/127.5
FDX_EMBED_DIM = 512
ERROR_NAMES = {
    0: "FDX_OK", -1: "FDX_ERR_INVALID_ARG", -2: "FDX_ERR_MODEL",
    -3: "FDX_ERR_GPU_INIT", -4: "FDX_ERR_RUN", -5: "FDX_ERR_DEVICE_LOST",
}


class FdxError(RuntimeError):
    """Raised for any non-FDX_OK status; .code holds the int32 status."""

    def __init__(self, code: int, detail: str) -> None:
        name = ERROR_NAMES.get(code, f"FDX_ERR_{code}")
        super().__init__(f"{name} ({code}): {detail}")
        self.code = code


def rgb8_to_input(rgb: bytes) -> np.ndarray:
    """112*112*3 row-major RGB8 bytes -> flat NCHW float32 ((x-127.5)/127.5)."""
    a = np.frombuffer(rgb, dtype=np.uint8)
    if a.size != 112 * 112 * 3:
        raise ValueError(f"expected {112 * 112 * 3} RGB bytes, got {a.size}")
    hwc = a.reshape(112, 112, 3).astype(np.float32)
    chw = np.ascontiguousarray(hwc.transpose(2, 0, 1))
    return ((chw - 127.5) / 127.5).astype(np.float32).ravel()


class Fdx:
    """Thin object wrapper: every call maps 1:1 onto an fdx_* export."""

    def __init__(self, dll_path: str) -> None:
        self.lib = C.CDLL(os.path.abspath(dll_path))
        self.lib.fdx_abi_version.restype = C.c_int32
        self.lib.fdx_abi_version.argtypes = []
        self.lib.fdx_gpu_count.restype = C.c_int32
        self.lib.fdx_gpu_count.argtypes = []
        self.lib.fdx_model_load.restype = C.c_int32
        self.lib.fdx_model_load.argtypes = [
            C.c_char_p, C.POINTER(C.c_void_p), C.c_char_p, C.c_int32]
        self.lib.fdx_model_free.restype = None
        self.lib.fdx_model_free.argtypes = [C.c_void_p]
        self.lib.fdx_engine_create.restype = C.c_int32
        self.lib.fdx_engine_create.argtypes = [
            C.c_int32, C.c_int32, C.POINTER(C.c_void_p), C.c_char_p, C.c_int32]
        self.lib.fdx_engine_free.restype = None
        self.lib.fdx_engine_free.argtypes = [C.c_void_p]
        self.lib.fdx_engine_run.restype = C.c_int32
        self.lib.fdx_engine_run.argtypes = [
            C.c_void_p, C.c_void_p, C.POINTER(C.c_float),
            C.POINTER(C.c_float), C.POINTER(C.c_double)]
        self.lib.fdx_engine_describe.restype = C.c_int32
        self.lib.fdx_engine_describe.argtypes = [
            C.c_void_p, C.c_char_p, C.c_int32,
            C.POINTER(C.c_int32), C.POINTER(C.c_ulonglong)]
        self.lib.fdx_engine_reinit.restype = C.c_int32
        self.lib.fdx_engine_reinit.argtypes = [
            C.c_void_p, C.c_int32, C.c_int32, C.c_char_p, C.c_int32]
        v = int(self.lib.fdx_abi_version())
        if v != FDX_ABI_VERSION:
            raise FdxError(-1, f"ABI version mismatch: dll={v} host={FDX_ABI_VERSION}")

    @staticmethod
    def _err(buf: C.create_string_buffer) -> str:
        return buf.value.decode("utf-8", "replace")

    def gpu_count(self) -> int:
        return int(self.lib.fdx_gpu_count())

    def load_model(self, fvp_path: str) -> C.c_void_p:
        h = C.c_void_p(0)
        buf = C.create_string_buffer(256)
        rc = self.lib.fdx_model_load(
            os.path.abspath(fvp_path).encode(), C.byref(h), buf, 256)
        if rc != 0:
            raise FdxError(rc, self._err(buf))
        return h

    def free_model(self, h: C.c_void_p) -> None:
        self.lib.fdx_model_free(h)

    def create_engine(self, fp16: bool = True, gpu_index: int = -1) -> C.c_void_p:
        h = C.c_void_p(0)
        buf = C.create_string_buffer(256)
        rc = self.lib.fdx_engine_create(
            1 if fp16 else 0, gpu_index, C.byref(h), buf, 256)
        if rc != 0:
            raise FdxError(rc, self._err(buf))
        return h

    def free_engine(self, h: C.c_void_p) -> None:
        self.lib.fdx_engine_free(h)

    def describe(self, h: C.c_void_p) -> dict:
        """Adapter an engine actually runs on: name, is_warp, luid."""
        name = C.create_string_buffer(256)
        warp = C.c_int32(0)
        luid = C.c_ulonglong(0)
        rc = self.lib.fdx_engine_describe(h, name, 256, C.byref(warp), C.byref(luid))
        if rc != 0:
            raise FdxError(rc, "fdx_engine_describe failed")
        return {"name": name.value.decode("utf-8", "replace"),
                "is_warp": bool(warp.value), "luid": int(luid.value)}

    def reinit(self, h: C.c_void_p, fp16: bool = True,
               gpu_index: int = -1) -> None:
        """In-place recovery / adapter switch (FDX_ERR_DEVICE_LOST path)."""
        buf = C.create_string_buffer(256)
        rc = self.lib.fdx_engine_reinit(h, 1 if fp16 else 0, gpu_index, buf, 256)
        if rc != 0:
            raise FdxError(rc, self._err(buf))

    def run(self, engine: C.c_void_p, model: C.c_void_p,
            rgb_bytes: bytes) -> tuple[list[float], float]:
        inp = rgb8_to_input(rgb_bytes)
        out = (C.c_float * FDX_EMBED_DIM)()
        ms = C.c_double(0.0)
        rc = self.lib.fdx_engine_run(
            engine, model, inp.ctypes.data_as(C.POINTER(C.c_float)),
            out, C.byref(ms))
        if rc != 0:
            raise FdxError(rc, "fdx_engine_run failed")
        return list(out), float(ms.value)

    def probe_errors(self) -> dict[str, int]:
        """Contract checks: bad model -> -2, NULL input -> -1."""
        res: dict[str, int] = {}
        h = C.c_void_p(0)
        buf = C.create_string_buffer(256)
        res["bad_model"] = int(self.lib.fdx_model_load(
            b"definitely_missing.fvp", C.byref(h), buf, 256))
        out = (C.c_float * FDX_EMBED_DIM)()
        res["null_input"] = int(self.lib.fdx_engine_run(
            None, None, None, out, None))
        return res


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("--dll", required=True)
    ap.add_argument("--model", default=None)
    ap.add_argument("--list", default=None, help="file with raw-RGB paths")
    ap.add_argument("--out", default=None, help="JSONL output path")
    ap.add_argument("--fp16", action="store_true")
    ap.add_argument("--probe-errors", action="store_true")
    a = ap.parse_args()

    fdx = Fdx(a.dll)
    if a.probe_errors:
        print(json.dumps(fdx.probe_errors()))
        return 0
    if not (a.model and a.list and a.out):
        ap.error("--model, --list and --out are required without --probe-errors")

    model = fdx.load_model(a.model)
    engine = fdx.create_engine(fp16=a.fp16)
    backend = "dll-dx16" if a.fp16 else "dll-dx"
    ok = failed = 0
    ms_total = 0.0
    t_wall0 = time.perf_counter()
    try:
        with open(a.list, "r", encoding="utf-8") as lf, \
                open(a.out, "w", encoding="utf-8") as out:
            for line in lf:
                p = line.strip()
                if not p:
                    continue
                try:
                    with open(p, "rb") as f:
                        rgb = f.read()
                    emb, ms = fdx.run(engine, model, rgb)
                except (OSError, ValueError, FdxError) as e:
                    print(f"[py] {p}: {e}", file=sys.stderr)
                    failed += 1
                    continue
                ok += 1
                ms_total += ms
                out.write(json.dumps({
                    "id": p.replace("\\", "/"),
                    "backend": backend,
                    "fp16": bool(a.fp16),
                    "ms": round(ms, 2),
                    "embedding": [float(f"{v:.8g}") for v in emb],
                }, separators=(",", ":")) + "\n")
                out.flush()
    finally:
        fdx.free_engine(engine)
        fdx.free_model(model)
    wall = time.perf_counter() - t_wall0
    print(f"[summary] ok={ok} failed={failed} ms_total={ms_total:.1f} "
          f"ms_avg={ms_total / ok if ok else 0:.2f} wall_s={wall:.2f}",
          file=sys.stderr)
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
