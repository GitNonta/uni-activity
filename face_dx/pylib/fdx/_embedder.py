"""fdx._embedder — high-level FaceEmbedder (image in, 512-d out)."""
from __future__ import annotations

import os
from typing import Iterator, Optional, Union

import numpy as np
from PIL import Image

from ._image import (INPUT_FLOATS, decode_file, decode_image, preprocess,
                     raw_bytes_to_input, to_rgb_bytes)
from ._native import (DeviceLostError, FdxError, GpuInitError, InvalidArgError,
                      ModelError, NativeEngine, RunError)

__all__ = [
    "FaceEmbedder",
    "FdxError", "InvalidArgError", "ModelError", "GpuInitError",
    "RunError", "DeviceLostError",
]

PathLike = Union[str, "os.PathLike[str]"]


def _pick_model(explicit: Optional[str]) -> str:
    if explicit:
        return explicit
    bundled = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                           "_native", "models", "w600k_mbf.fvp")
    if os.path.exists(bundled):
        return bundled
    raise ModelError(-2, "no model given and bundled w600k_mbf.fvp missing")


class FaceEmbedder:
    """512-D face embedding through the native D3D11 engine.

    Parameters
    ----------
    fp16      : packed-fp16 weight storage with fp32 accumulation
                (production, default) — False selects the fp32 reference.
    gpu_index : -1 default hardware adapter, -2 force WARP (pure-CPU
                software rasterizer), >=0 explicit DXGI adapter index.
    model     : path to a .fvp model; defaults to the bundled w600k_mbf.fvp.
    env_defaults : read FDX_GPU_INDEX / FDX_FP16 when the corresponding
                argument is None (deployment pinning without code changes).
    """

    def __init__(self, fp16: Optional[bool] = None, gpu_index: Optional[int] = None,
                 model: Optional[PathLike] = None,
                 env_defaults: bool = True) -> None:
        if fp16 is None:
            fp16 = (os.environ.get("FDX_FP16", "1") != "0") if env_defaults else True
        if gpu_index is None:
            gi = os.environ.get("FDX_GPU_INDEX") if env_defaults else None
            gpu_index = int(gi) if gi not in (None, "") else -1
        self._engine = NativeEngine(_pick_model(model), fp16=fp16,
                                    gpu_index=gpu_index)
        self.fp16 = bool(fp16)
        self.gpu_index = int(gpu_index)

    # -- core ------------------------------------------------------------

    def embed(self, image: Union[bytes, Image.Image, np.ndarray],
              *, raw: Optional[bool] = None) -> np.ndarray:
        """512-d L2-normalized float32 embedding.

        image may be:
          - bytes of any encoded image (JPEG/PNG/WebP/...), auto-detected
          - raw RGB8 bytes of exactly 112*112*3 (engine contract), or pass
            raw=True to force that interpretation for any 37632-byte buffer
          - PIL.Image.Image (RGB or convertible)
          - np.ndarray HWC or CHW uint8/float (resized to 112x112)
        """
        emb, _ = self.embed_ms(image, raw=raw)
        return emb

    def embed_ms(self, image: Union[bytes, Image.Image, np.ndarray],
                 *, raw: Optional[bool] = None) -> tuple[np.ndarray, float]:
        """(embedding, engine_ms) — engine wall ms including submit+wait."""
        inp = self._to_input(image, raw=raw)
        return self._engine.run(inp)

    def embed_file(self, path: PathLike, return_ms: bool = False
                   ) -> Union[np.ndarray, tuple[np.ndarray, float]]:
        img = decode_file(path)
        emb, ms = self.embed_ms(img)
        return (emb, ms) if return_ms else emb

    # -- batch -------------------------------------------------------------

    def embed_batch(self, images: list) -> np.ndarray:
        """(N, 512) float32 for a list of images (bytes / PIL / ndarray)."""
        out = np.empty((len(images), 512), dtype=np.float32)
        for i, im in enumerate(images):
            out[i] = self.embed(im)
        return out

    def embed_files(self, paths: list, on_error: str = "raise"
                    ) -> Iterator[tuple[str, Union[np.ndarray, Exception]]]:
        """Generator over paths; on_error='skip' yields the exception instead."""
        for p in paths:
            try:
                yield p, self.embed_file(p)
            except (FdxError, ValueError, OSError) as e:
                if on_error == "raise":
                    raise
                if on_error == "skip":
                    yield p, e
                else:
                    raise ValueError(f"on_error must be 'raise' or 'skip', "
                                     f"got {on_error!r}")

    # -- engine management ---------------------------------------------------

    def describe(self) -> dict:
        """Adapter actually in use: {name, is_warp, luid}."""
        return self._engine.describe()

    def reinit(self, fp16: Optional[bool] = None,
               gpu_index: Optional[int] = None) -> None:
        """In-place recovery (DeviceLostError) or adapter switch."""
        self._engine.reinit(
            fp16=self.fp16 if fp16 is None else bool(fp16),
            gpu_index=self.gpu_index if gpu_index is None else int(gpu_index))

    def gpu_count(self) -> int:
        return self._engine.gpu_count()

    def close(self) -> None:
        self._engine.free()

    def __enter__(self) -> "FaceEmbedder":
        return self

    def __exit__(self, *exc) -> None:
        self.close()

    # -- helpers ------------------------------------------------------------

    @staticmethod
    def _to_input(image: Union[bytes, Image.Image, np.ndarray],
                  *, raw: Optional[bool] = None) -> np.ndarray:
        """bytes -> decode-or-raw (auto, or `raw=` override); PIL/ndarray ->
        resize+normalize. Engine-contract raw input bypasses Pillow."""
        if raw is True:
            if not isinstance(image, (bytes, bytearray)):
                raise TypeError("raw=True requires bytes")
            return raw_bytes_to_input(bytes(image))
        if isinstance(image, Image.Image):
            return preprocess(image)
        if isinstance(image, (bytes, bytearray)):
            b = bytes(image)
            if raw is False:
                return preprocess(decode_image(b))
            # auto: the engine contract is exactly 37632 bytes of raw RGB;
            # a same-sized encoded image is vanishingly rare, and `raw=`
            # exists as the explicit escape hatch.
            if len(b) == INPUT_FLOATS:
                return raw_bytes_to_input(b)
            return preprocess(decode_image(b))
        if isinstance(image, np.ndarray):
            a = image
            if a.dtype != np.uint8:
                a = a.astype(np.uint8)
            if a.ndim == 3 and a.shape[0] == 3 and a.shape[-1] != 3:
                a = a.transpose(1, 2, 0)          # CHW -> HWC
            if a.ndim != 3 or a.shape[-1] != 3:
                raise ValueError(
                    f"ndarray must be HWC or CHW RGB, got shape {image.shape}")
            img = Image.fromarray(np.ascontiguousarray(a), "RGB")
            return preprocess(img)
        raise TypeError(f"unsupported image type: {type(image)!r}")

    def __repr__(self) -> str:
        try:
            d = self._engine.describe()
            where = f"adapter={d['name']!r} warp={d['is_warp']}"
        except Exception:
            where = "uninitialized"
        return f"<FaceEmbedder fp16={self.fp16} {where}>"
