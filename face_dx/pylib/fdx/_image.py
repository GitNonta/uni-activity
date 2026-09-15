"""fdx._image — image decoding / resizing / normalization for the engine.

The engine contract (fdx_capi.h) takes NCHW RGB floats normalized
(x-127.5)/127.5 at 112x112; the caller owns decoding and resizing. This
module does exactly that with Pillow (any format Pillow decodes).
"""
from __future__ import annotations

import io
import os
from typing import Union

import numpy as np
from PIL import Image

INPUT_SIZE = 112
INPUT_FLOATS = 3 * INPUT_SIZE * INPUT_SIZE

PathLike = Union[str, "os.PathLike[str]"]


def decode_image(data: bytes) -> Image.Image:
    """Bytes of any Pillow-decodable image -> RGB PIL Image."""
    img = Image.open(io.BytesIO(data))
    img.load()
    if img.mode != "RGB":
        img = img.convert("RGB")
    return img


def decode_file(path: PathLike) -> Image.Image:
    with open(path, "rb") as f:
        return decode_image(f.read())


def to_rgb_bytes(img: Image.Image) -> bytes:
    """Exactly 112*112*3 RGB8 bytes, resized to the engine input size."""
    if img.size != (INPUT_SIZE, INPUT_SIZE):
        img = img.resize((INPUT_SIZE, INPUT_SIZE), Image.BILINEAR)
    return img.tobytes()


def preprocess(img: Image.Image) -> np.ndarray:
    """PIL RGB image -> flat NCHW float32 for fdx_engine_run."""
    return _normalize(to_rgb_bytes(img))


def raw_bytes_to_input(rgb: bytes) -> np.ndarray:
    """Exactly 112*112*3 row-major RGB8 bytes -> flat NCHW float32.

    No decode, no resize — this is the engine's own staging contract
    (fdx_capi.h: "input ... already resized to 112x112").
    """
    return _normalize(rgb)


def _normalize(rgb: bytes) -> np.ndarray:
    a = np.frombuffer(rgb, dtype=np.uint8)
    if a.size != INPUT_FLOATS:
        raise ValueError(f"expected {INPUT_FLOATS} RGB bytes, got {a.size}")
    hwc = a.reshape(INPUT_SIZE, INPUT_SIZE, 3).astype(np.float32)
    chw = np.ascontiguousarray(hwc.transpose(2, 0, 1))
    return ((chw - 127.5) / 127.5).astype(np.float32).ravel()
