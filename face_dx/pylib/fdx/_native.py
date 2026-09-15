"""fdx._native — ctypes core over face_dx.dll (FDX C ABI v1).

Every public method maps 1:1 onto an fdx_* export; the DLL is resolved
from the package's own _native/ directory so installation location and
process CWD are irrelevant (the DLL self-locates its shaders from its
module path — see fdx_capi.cpp DirGuard).
"""
from __future__ import annotations

import ctypes as C
import os
from typing import Optional

import numpy as np

FDX_ABI_VERSION = 1
FDX_INPUT_FLOATS = 37632   # 3*112*112 NCHW RGB, (x-127.5)/127.5
FDX_EMBED_DIM = 512

ERROR_NAMES = {
    0: "FDX_OK", -1: "FDX_ERR_INVALID_ARG", -2: "FDX_ERR_MODEL",
    -3: "FDX_ERR_GPU_INIT", -4: "FDX_ERR_RUN", -5: "FDX_ERR_DEVICE_LOST",
}

_HERE = os.path.dirname(os.path.abspath(__file__))
_DLL_PATH = os.path.join(_HERE, "_native", "face_dx.dll")
_ERR_CAP = 256


def _load_lib() -> C.CDLL:
    if not os.path.exists(_DLL_PATH):
        raise RuntimeError(
            f"face_dx.dll not found at {_DLL_PATH!r} — the package payload "
            "is incomplete (rebuild with build_face_dx_package.sh)")
    lib = C.CDLL(_DLL_PATH)
    lib.fdx_abi_version.restype = C.c_int32
    lib.fdx_abi_version.argtypes = []
    lib.fdx_gpu_count.restype = C.c_int32
    lib.fdx_gpu_count.argtypes = []
    lib.fdx_model_load.restype = C.c_int32
    lib.fdx_model_load.argtypes = [
        C.c_char_p, C.POINTER(C.c_void_p), C.c_char_p, C.c_int32]
    lib.fdx_model_free.restype = None
    lib.fdx_model_free.argtypes = [C.c_void_p]
    lib.fdx_engine_create.restype = C.c_int32
    lib.fdx_engine_create.argtypes = [
        C.c_int32, C.c_int32, C.POINTER(C.c_void_p), C.c_char_p, C.c_int32]
    lib.fdx_engine_free.restype = None
    lib.fdx_engine_free.argtypes = [C.c_void_p]
    lib.fdx_engine_run.restype = C.c_int32
    lib.fdx_engine_run.argtypes = [
        C.c_void_p, C.c_void_p, C.POINTER(C.c_float),
        C.POINTER(C.c_float), C.POINTER(C.c_double)]
    lib.fdx_engine_describe.restype = C.c_int32
    lib.fdx_engine_describe.argtypes = [
        C.c_void_p, C.c_char_p, C.c_int32,
        C.POINTER(C.c_int32), C.POINTER(C.c_ulonglong)]
    lib.fdx_engine_reinit.restype = C.c_int32
    lib.fdx_engine_reinit.argtypes = [
        C.c_void_p, C.c_int32, C.c_int32, C.c_char_p, C.c_int32]
    v = int(lib.fdx_abi_version())
    if v != FDX_ABI_VERSION:
        raise RuntimeError(
            f"FDX ABI version mismatch: dll={v} host={FDX_ABI_VERSION}")
    return lib


def _err(buf: C.create_string_buffer) -> str:
    return buf.value.decode("utf-8", "replace")


def rgb8_to_input(rgb: bytes) -> np.ndarray:
    """112*112*3 row-major RGB8 bytes -> flat NCHW float32 ((x-127.5)/127.5)."""
    a = np.frombuffer(rgb, dtype=np.uint8)
    if a.size != 112 * 112 * 3:
        raise ValueError(f"expected {112 * 112 * 3} RGB bytes, got {a.size}")
    hwc = a.reshape(112, 112, 3).astype(np.float32)
    chw = np.ascontiguousarray(hwc.transpose(2, 0, 1))
    return ((chw - 127.5) / 127.5).astype(np.float32).ravel()


class NativeEngine:
    """One face_dx.dll engine handle + one model handle, lifetime-owned."""

    def __init__(self, model_path: str, fp16: bool = True,
                 gpu_index: int = -1) -> None:
        self.lib = _load_lib()
        self._model: Optional[C.c_void_p] = None
        self._engine: Optional[C.c_void_p] = None

        buf = C.create_string_buffer(_ERR_CAP)
        h = C.c_void_p(0)
        rc = self.lib.fdx_model_load(
            os.path.abspath(model_path).encode("utf-8"), C.byref(h), buf, _ERR_CAP)
        if rc != 0:
            raise GpuInitError(rc, _err(buf)) if rc == -3 else ModelError(rc, _err(buf))
        self._model = h

        buf = C.create_string_buffer(_ERR_CAP)
        h = C.c_void_p(0)
        rc = self.lib.fdx_engine_create(
            1 if fp16 else 0, int(gpu_index), C.byref(h), buf, _ERR_CAP)
        if rc != 0:
            self.free()
            raise _map_error(rc, _err(buf))
        self._engine = h

    # -- lifecycle ---------------------------------------------------------

    def free(self) -> None:
        if self._engine is not None:
            self.lib.fdx_engine_free(self._engine)
            self._engine = None
        if self._model is not None:
            self.lib.fdx_model_free(self._model)
            self._model = None

    def __del__(self) -> None:
        try:
            self.free()
        except Exception:
            pass

    # -- info --------------------------------------------------------------

    def gpu_count(self) -> int:
        return int(self.lib.fdx_gpu_count())

    def describe(self) -> dict:
        name = C.create_string_buffer(_ERR_CAP)
        warp = C.c_int32(0)
        luid = C.c_ulonglong(0)
        rc = self.lib.fdx_engine_describe(
            self._engine, name, _ERR_CAP, C.byref(warp), C.byref(luid))
        if rc != 0:
            raise _map_error(rc, "fdx_engine_describe failed")
        return {"name": name.value.decode("utf-8", "replace"),
                "is_warp": bool(warp.value), "luid": int(luid.value)}

    # -- inference ---------------------------------------------------------

    def run(self, input_f32: np.ndarray) -> tuple[np.ndarray, float]:
        if self._engine is None or self._model is None:
            raise GpuInitError(-3, "engine has no device (reinit required)")
        out = np.zeros(FDX_EMBED_DIM, dtype=np.float32)
        ms = C.c_double(0.0)
        rc = self.lib.fdx_engine_run(
            self._engine, self._model,
            input_f32.ctypes.data_as(C.POINTER(C.c_float)),
            out.ctypes.data_as(C.POINTER(C.c_float)), C.byref(ms))
        if rc != 0:
            raise _map_error(rc, "fdx_engine_run failed")
        return out, float(ms.value)

    def reinit(self, fp16: bool = True, gpu_index: int = -1) -> None:
        """In-place recovery / adapter switch; model handle stays valid."""
        buf = C.create_string_buffer(_ERR_CAP)
        rc = self.lib.fdx_engine_reinit(
            self._engine, 1 if fp16 else 0, int(gpu_index), buf, _ERR_CAP)
        if rc != 0:
            raise _map_error(rc, _err(buf))

    # -- contract probes -----------------------------------------------------

    def probe_errors(self) -> dict:
        res = {}
        buf = C.create_string_buffer(_ERR_CAP)
        h = C.c_void_p(0)
        res["bad_model"] = int(self.lib.fdx_model_load(
            b"definitely_missing.fvp", C.byref(h), buf, _ERR_CAP))
        out = np.zeros(FDX_EMBED_DIM, dtype=np.float32)
        res["null_input"] = int(self.lib.fdx_engine_run(
            None, None, None,
            out.ctypes.data_as(C.POINTER(C.c_float)), None))
        return res


def _map_error(rc: int, detail: str) -> Exception:
    if rc == -2:
        return ModelError(rc, detail)
    if rc == -3:
        return GpuInitError(rc, detail)
    if rc == -5:
        return DeviceLostError(rc, detail)
    if rc == -1:
        return InvalidArgError(rc, detail)
    return FdxError(rc, detail)


class FdxError(RuntimeError):
    """Base for any non-FDX_OK status; .code holds the int32 status."""

    def __init__(self, code: int, detail: str) -> None:
        name = ERROR_NAMES.get(code, f"FDX_ERR_{code}")
        super().__init__(f"{name} ({code}): {detail}")
        self.code = code


class InvalidArgError(FdxError):
    pass


class ModelError(FdxError):
    pass


class GpuInitError(FdxError):
    pass


class RunError(FdxError):
    pass


class DeviceLostError(FdxError):
    """fdx_engine_run returned FDX_ERR_DEVICE_LOST — call reinit()."""
