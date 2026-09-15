"""fdx — 512-D face embedding via the zero-dependency Direct3D 11 engine.

    import fdx
    with fdx.FaceEmbedder() as fx:
        emb = fx.embed_file("photo.jpg")     # float32 (512,), L2-normalized

See README.md in the package source for engine selection, batch use and
device-loss recovery.
"""
from __future__ import annotations

import os

import numpy as np

from ._embedder import (DeviceLostError, FdxError, FaceEmbedder, GpuInitError,
                        InvalidArgError, ModelError, RunError)
from ._image import INPUT_FLOATS, INPUT_SIZE
from ._native import FDX_ABI_VERSION, FDX_EMBED_DIM, FDX_INPUT_FLOATS

__version__ = "1.0.0"

__all__ = [
    "FaceEmbedder",
    "FdxError", "InvalidArgError", "ModelError", "GpuInitError",
    "RunError", "DeviceLostError",
    "INPUT_SIZE", "INPUT_FLOATS", "FDX_ABI_VERSION", "FDX_EMBED_DIM",
    "FDX_INPUT_FLOATS", "abi_version", "gpu_count", "self_check",
    "__version__",
]


def abi_version() -> int:
    """FDX C ABI version of the bundled DLL (call cheaply; caches the lib)."""
    from ._native import _load_lib
    return int(_load_lib().fdx_abi_version())


def gpu_count() -> int:
    """Number of usable DXGI adapters (for FaceEmbedder(gpu_index=i))."""
    from ._native import _load_lib
    return int(_load_lib().fdx_gpu_count())


def self_check(sample_image: "os.PathLike[str] | str | None" = None,
               gpu_index: int = -1) -> dict:
    """End-to-end sanity of the installed native payload.

    Checks: ABI gate, contract probes (bad model -> -2, NULL input -> -1),
    adapter describe, one real embed with finite L2-normalized output, and
    the production fp16-vs-fp32 cosine gate (>= 0.9998) when a sample
    image is supplied. Never raises for expected conditions — read
    report["ok"] / report["checks"].
    """
    report: dict = {"ok": True, "checks": {}, "gpu_index": int(gpu_index)}
    ok = report["checks"]

    ok["abi"] = (abi_version() == FDX_ABI_VERSION)
    report["ok"] &= ok["abi"]

    probe = NativeEngineProbe(gpu_index)
    ok["error_contract"] = probe
    report["ok"] &= (probe["bad_model"] == -2 and probe["null_input"] == -1)

    def _run(fp16: bool) -> tuple[np.ndarray, dict, float]:
        e = FaceEmbedder(fp16=fp16, gpu_index=gpu_index, env_defaults=False)
        d = e.describe()
        if sample_image is not None:
            emb, ms = e.embed_file(sample_image, return_ms=True)
        else:
            import time as _t
            rgb = np.random.default_rng(0).integers(
                0, 255, (112, 112, 3), dtype=np.uint8)
            emb, ms = e.embed_ms(rgb)
        e.close()
        return emb, d, ms

    try:
        emb16, desc, ms16 = _run(True)
        ok["engine_fp16"] = True
        ok["finite"] = bool(np.isfinite(emb16).all())
        ok["norm_close"] = bool(abs(float(np.linalg.norm(emb16)) - 1.0) < 1e-3)
        ok["describe"] = desc
        report["ms_fp16"] = round(ms16, 2)
        report["ok"] &= ok["finite"] and ok["norm_close"]
    except Exception as e:                       # noqa: BLE001 — report, not raise
        ok["engine_fp16"] = f"FAILED: {e}"
        report["ok"] = False

    if sample_image is not None and ok.get("engine_fp16") is True:
        try:
            emb32, _, ms32 = _run(False)
            cos = float(np.dot(emb16, emb32))
            ok["fp16_vs_fp32"] = {"cosine": round(cos, 6),
                                  "gate": 0.9998,
                                  "pass": bool(cos >= 0.9998)}
            report["ms_fp32"] = round(ms32, 2)
            report["ok"] &= ok["fp16_vs_fp32"]["pass"]
        except Exception as e:                   # noqa: BLE001
            ok["fp16_vs_fp32"] = f"FAILED: {e}"
            report["ok"] = False

    report["ok"] = bool(report["ok"])
    return report


def NativeEngineProbe(gpu_index: int) -> dict:
    """Contract probes without loading the model (native-level)."""
    from ._native import NativeEngine
    e = NativeEngine.__new__(NativeEngine)   # bypass __init__ (no model load)
    from ._native import _load_lib
    e.lib = _load_lib()
    e._model = None
    e._engine = None
    return e.probe_errors()
