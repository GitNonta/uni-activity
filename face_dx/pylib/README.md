# fdx — FaceDx 512-D face embedding library

Python packaging of the **FDX engine** (the custom MobileFaceNet-512D
Direct3D 11 inference runtime in this repo) as an **installable wheel**.
No ONNX Runtime, no OpenCV, no PyTorch at runtime — the native engine is
a zero-dependency DLL (only OS-shipped `d3d11.dll` / `dxgi.dll` /
`d3dcompiler_47.dll`), and the Python side uses `ctypes` + `numpy` +
`pillow`.

The wheel ships the native engine **inside** the package:

```
fdx/
  _native/face_dx.dll   self-contained engine (exports fdx_*, ABI 1)
  _native/shaders/      HLSL kernels the DLL compiles at load
  _native/models/       w600k_mbf.fvp  (fp16 weights, ~6.8 MB)
  _native/fdx_capi.h    the C ABI the DLL implements
fdx/
  __init__.py           public API: FaceEmbedder, errors, constants
  _embedder.py          high-level embedder (image in, 512-d out)
  _image.py             preprocessing: decode / resize / normalize
  _native.py            ctypes core over fdx_capi.h
```

## Install

```bash
pip install dist/fdx-1.0.0-py3-none-win_amd64.whl
```

(the wheel is tagged `win_amd64` because the engine is a Direct3D 11
Windows DLL; rebuild on the target machine with `build_face_dx_package.sh`
if the engine is recompiled.)

## Quickstart

```python
import fdx

# auto mode: default GPU adapter, fp16 weights, bundled model
fx = fdx.FaceEmbedder()

emb = fx.embed_file("photo.jpg")     # numpy float32 (512,), L2-normalized
emb = fx.embed(rgb_bytes)            # 112*112*3 raw RGB8 bytes (engine contract)
emb = fx.embed(jpeg_bytes)           # or any encoded image (JPEG/PNG/WebP/...)
emb = fx.embed(pil_image)            # or a PIL Image / RGB ndarray (auto-resized)
emb, ms = fx.embed_file("photo.jpg", return_ms=True)

print(fx.describe())                 # which adapter actually runs
fx.close()                           # or use `with fdx.FaceEmbedder() as fx:`
```

Any image format Pillow decodes (JPEG, PNG, WebP, BMP, TIFF, ...) works;
it is resized to 112×112 and normalized `(x-127.5)/127.5` exactly like the
CLI and the training pipeline.

## Engine selection

```python
fx = fdx.FaceEmbedder(fp16=True,   gpu_index=-1)  # default GPU (auto)
fx = fdx.FaceEmbedder(fp16=True,   gpu_index=0)   # explicit adapter 0
fx = fdx.FaceEmbedder(fp16=True,   gpu_index=-2)  # force WARP (pure-CPU software rasterizer)
fx = fdx.FaceEmbedder(fp16=False)                 # fp32 reference path
```

`FaceEmbedder` picks the machine's defaults from the environment
(`FDX_GPU_INDEX`, `FDX_FP16`) so deployments can pin behavior without code
changes.

## Batch

```python
for emb, ms in fx.embed_files(paths):        # generator, constant memory
    ...
embs = fx.embed_batch(list_of_images)        # convenience list form
```

One engine per thread (the engine has a single immediate D3D11 context;
concurrent `run` on one engine is not supported — the C ABI documents
this). Create one `FaceEmbedder` per worker thread for parallel hosts.

## Device-loss recovery

If the GPU driver resets mid-run the API raises `fdx.DeviceLostError`.
Recover in place and keep going:

```python
try:
    emb = fx.embed(rgb)
except fdx.DeviceLostError:
    fx.reinit(gpu_index=-2)   # e.g. continue on WARP (CPU)
```

## Verification / parity

`fdx.self_check()` runs the bundled contract probes (bad model → `-2`,
NULL input → `-1`, fp16-vs-fp32 production gate) against the loaded DLL
and returns a report dict. The suite in this folder
(`test_package.py`) proves wheel-installed output is **bit-identical**
to the C runner / exe / other language bindings.

## Status codes

Identical to `fdx_capi.h`:

| code | name |
|---|---|
| 0 | FDX_OK |
| -1 | FDX_ERR_INVALID_ARG |
| -2 | FDX_ERR_MODEL |
| -3 | FDX_ERR_GPU_INIT |
| -4 | FDX_ERR_RUN |
| -5 | FDX_ERR_DEVICE_LOST |
