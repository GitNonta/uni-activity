"""fdx_backend.py — the fdx (face_dx D3D11 engine) embedder for server.py.

This is the activity-check face decoder the project chose in testing: the
custom MobileFaceNet-512D engine (w600k_mbf.fvp) running on the
zero-dependency Direct3D 11 runtime, replacing the ONNX ArcFace inside
/extract and /verify.

Embedding-space contract
------------------------
fdx embeddings are NOT comparable with InsightFace/ArcFace embeddings
(cosine between the two spaces clusters near zero). Switching the decoder
is an embedding-space migration: every stored 512-d vector must be
re-extracted with fdx from an enrolled photo. The PCA 512->128 reducer is
also fitted per-space; it must be re-fit on fdx embeddings before the
128-d output means anything (until then it stays disabled).

Calibration (CelebA, 5 identities, flipped-image same-person probes):
same-person cosine 0.858-0.911, different-person 0.019-0.212, separation
margin 0.646. Default threshold 0.60 (env FDX_MATCH_THRESHOLD).

Alignment contract
------------------
The engine was validated with plain 112x112 bilinear resize of the face
ROI (preprocessing parity gate: cosine 1.000000000 vs ground truth on the
full CelebA run). Landmark-aligned (norm_crop) faces are INSIDE that
distribution, so the caller may pass either; this module embeds whatever
112x112 BGR crop it receives without second-guessing.

Failure policy
--------------
Engine creation or inference failure degrades to insightface (fail-open)
so an iGPU/driver problem never takes verification offline; the reason is
surfaced via describe() and /health.
"""
from __future__ import annotations

import os
import sys
import threading
from typing import Optional

import numpy as np

# ── fdx import: installed wheel first, repo pylib fallback ──────────────────
_HERE = os.path.dirname(os.path.abspath(__file__))
_PYLIB = os.path.abspath(os.path.join(_HERE, "..", "face_dx", "pylib"))


def _import_fdx():
    try:
        import fdx  # installed wheel
        return fdx, None
    except ImportError:
        if os.path.isdir(_PYLIB) and _PYLIB not in sys.path:
            sys.path.insert(0, _PYLIB)
        try:
            import fdx  # repo source (needs face_dx/build/face_dx.dll)
            return fdx, None
        except Exception as e:  # noqa: BLE001
            return None, f"{type(e).__name__}: {e}"
        finally:
            # keep the repo path only while the import succeeded
            if "fdx" not in sys.modules:
                sys.path.remove(_PYLIB)


class FdxBackend:
    """Thread-safe fdx embedder with fail-open semantics for server.py."""

    def __init__(self, model: Optional[str] = None,
                 fp16: Optional[bool] = None,
                 gpu_index: Optional[int] = None) -> None:
        self._lock = threading.Lock()
        self._fdx = None
        self._engine = None
        self._device = threading.local()
        self._primary_thread = threading.get_ident()
        self.available = False
        self.last_error = "not initialized"
        self.threshold = float(os.environ.get("FDX_MATCH_THRESHOLD", "0.60"))

        fdx, err = _import_fdx()
        if fdx is None:
            self.last_error = f"fdx import failed: {err}"
            return
        self._fdx = fdx
        try:
            eng = self._fdx.FaceEmbedder(
                model=model, fp16=fp16, gpu_index=gpu_index)
            self._engine = eng
            self._device.engine = eng
            self.available = True
            self.last_error = ""
        except Exception as e:  # noqa: BLE001
            self.last_error = f"{type(e).__name__}: {e}"

    # -- info ---------------------------------------------------------------

    def describe(self) -> dict:
        d: dict = {
            "available": self.available,
            "backend": "fdx-d3d11" if self.available else None,
            "threshold": self.threshold,
        }
        if self.available:
            try:
                d["adapter"] = self._engine.describe()
            except Exception as e:  # noqa: BLE001
                d["adapter"] = f"describe failed: {e}"
        else:
            d["error"] = self.last_error
        return d

    # -- embedding ----------------------------------------------------------

    def embed_bgr(self, crop_bgr: np.ndarray) -> Optional[np.ndarray]:
        """112x112 (or larger) BGR crop -> 512-d L2-normalized float32.

        Returns None on any engine failure (caller falls back); the
        failure reason lands in describe()["error"].
        """
        eng = getattr(self._device, "engine", None)
        if eng is None:
            if not self.available:
                return None
            return self._embed_on_primary(crop_bgr)
        return self._run(eng, crop_bgr)

    def _embed_on_primary(self, crop_bgr: np.ndarray) -> Optional[np.ndarray]:
        # The engine is single-context (fdx_capi.h: no concurrent runs on
        # one engine). Requests may arrive on uvicorn worker threads, so
        # those calls marshal onto the owning thread's engine serially
        # through the lock.
        with self._lock:
            if not self.available:
                return None
            return self._run(self._engine, crop_bgr)

    def _run(self, eng, crop_bgr: np.ndarray) -> Optional[np.ndarray]:
        try:
            rgb = np.ascontiguousarray(crop_bgr[:, :, ::-1])  # BGR -> RGB
            return eng.embed(rgb)  # ndarray input: auto-resize to 112x112
        except self._fdx.DeviceLostError as e:
            self.last_error = f"device lost: {e}"
            self.available = False
            return None
        except Exception as e:  # noqa: BLE001
            self.last_error = f"{type(e).__name__}: {e}"
            return None

    # -- recovery -----------------------------------------------------------

    def reinit(self, fp16: Optional[bool] = None,
               gpu_index: Optional[int] = None) -> bool:
        """Re-create the engine in place (device-loss / adapter switch)."""
        with self._lock:
            if self._engine is None:
                return False
            try:
                self._engine.reinit(fp16=fp16, gpu_index=gpu_index)
                self.available = True
                self.last_error = ""
                return True
            except Exception as e:  # noqa: BLE001
                self.last_error = f"reinit: {type(e).__name__}: {e}"
                self.available = False
                return False

    def close(self) -> None:
        with self._lock:
            if self._engine is not None:
                try:
                    self._engine.close()
                except Exception:  # noqa: BLE001
                    pass
                self._engine = None
            self.available = False
