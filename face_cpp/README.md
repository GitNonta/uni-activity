# 🎭 MobileFaceNet 512-d Extractor — C++ / NCNN / Vulkan (P1)

C++ facial-embedding extractor that replicates the InsightFace **buffalo_s** pipeline
(MobileFaceNet `w600k_mbf`, ArcFace, 512-d) using **ncnn** inference with an optional
**Vulkan GPU** backend, tested on **P1** (Phone 1, `192.168.1.222`, Termux, Adreno 506).

## What it does

```
aligned 112x112 face crop (RGB)
        │  (pixel - 127.5) / 127.5          ← exactly cv2.dnn.blobFromImage(1/127.5, mean 127.5)
        ▼
ncnn MobileFaceNet (w600k_mbf)             ← CPU  (OpenMP) or Vulkan GPU (fp32 / fp16)
        ▼
512-d logits → L2 normalize                ← == insightface normed_embedding
        ▼
JSON line: {id, backend, fp16, ms, embedding[512]}
```

Preprocessing and normalization replicate `insightface/model_zoo/arcface_onnx.py` +
`insightface/utils/face_align.py` exactly (verified empirically for
`blobFromImage`: output = `(x - 127.5) / 127.5`).

## Files

| File | Purpose |
|---|---|
| `face_extract.cpp` | The extractor (`--gpu/--cpu/--fp16/--bench N/--model DIR/--out BLOB`) |
| `android_log_stub.c` | extern-"C" stubs for `liblog`/`libandroid` symbols referenced by the NDK-built `libncnn.a` (Termux lacks them) |
| `CMakeLists.txt` | cmake build (note: ncnn's `-fno-rtti` interface flag breaks OpenCV — use the direct clang++ command below) |
| `gen_references.py` | Windows: generate aligned crops + Python reference embeddings (SCRFD + ArcFaceONNX, local .onnx) |
| `compare_embeddings.py` | cosine-similarity comparison C++ vs Python reference |
| `p1_run_tests.sh` | end-to-end test script (conversion → build → CPU/GPU runs) |
| `models/` | `w600k_mbf.onnx`, `det_500m.onnx` (gitignored) + ncnn conversions |
| `testdata/` | sample photos pulled from P1 DB + crops + `references.json` (gitignored) |
| `results/` | run outputs (gitignored) |

## Build on P1 (Termux)

Prebuilt ncnn (Android arm64 + Vulkan) from the official release — no source build needed:

```bash
pkg install -y clang opencv vulkan-loader-android glslang libomp
cd ~
curl -sL -o ncnn-android-vulkan.zip \
  https://github.com/Tencent/ncnn/releases/download/20260526/ncnn-20260526-android-vulkan.zip
python -c "import zipfile; zipfile.ZipFile('ncnn-android-vulkan.zip').extractall('ncnn-prebuilt')"

cd ~/face_cpp
NCNN=$HOME/ncnn-prebuilt/ncnn-20260526-android-vulkan/arm64-v8a
clang -O2 -c android_log_stub.c -o android_log_stub.o
clang++ -O2 -std=c++17 -frtti -fexceptions -fopenmp face_extract.cpp android_log_stub.o -o face_extract \
  -I$NCNN/include/ncnn -I$PREFIX/include/opencv4 \
  -L$NCNN/lib -lncnn -lglslang -lMachineIndependent -lSPIRV -lGenericCodeGen -lOSDependent -lglslang-default-resource-limits \
  -L$PREFIX/lib -lopencv_core -lopencv_imgproc -lopencv_imgcodecs -lvulkan -lpthread
```

Why the stubs: the NDK-built `libncnn.a` references `__android_log_*` (liblog),
`AAssetManager_*` (libandroid) and LLVM OpenMP — Termux ships none of these except
`libomp`, so `-fopenmp` + the small stub file resolve them.

## Model conversion (Windows, pnnx)

```bash
# pnnx = ncnn's ONNX→ncnn converter (prebuilt windows binary from pnnx/pnnx releases)
pnnx w600k_mbf.onnx                                   # → w600k_mbf.ncnn.param/.bin (fp32)
pnnx w600k_mbf.onnx fp16=1 ncnnparam=... ncnnbin=...  # → fp16-storage variant
```

`w600k_mbf.onnx` (13 MB, MobileFaceNet, 512-d) + `det_500m.onnx` (SCRFD-500MF) are from
the InsightFace **buffalo_s** model pack (HF mirror `deepghs/insightface`). 98/98 ONNX
ops convert to native ncnn layers (Conv/PRelu/Add/Flatten/Gemm/BN), 892 MFLOPs.
Blob names after conversion: input `in0`, output `out0` (override with `--out`).

## Test data & reference

```bash
# Windows: 4 face photos pulled from P1's DB (selfies + profile photos)
python face_cpp/gen_references.py   # → testdata/crops/*.png + testdata/references.json
```

## Run + validate on P1

```bash
./face_extract --cpu --model models --bench 5  testdata/crops/*.png | tee results_cpu.jsonl
./face_extract --gpu --model models --bench 20 testdata/crops/*.png | tee results_gpu.jsonl
./face_extract --gpu --fp16 --model models/mbf_fp16 --bench 20 testdata/crops/*.png | tee results_gpu_fp16.jsonl
```

```bash
# Windows:
python face_cpp/compare_embeddings.py face_cpp/testdata/references.json \
  face_cpp/results/results_cpu.jsonl face_cpp/results/results_gpu.jsonl face_cpp/results/results_gpu_fp16.jsonl
```

## Results (P1: DUB-LX3, Adreno 506, Vulkan 1.0.61, 3.5 GB RAM) — 2026-09-10

Correctness vs Python InsightFace reference (cosine similarity of L2-normed 512-d):

| Backend | n | cosine (mean) | cosine (min) |
|---|---|---|---|
| CPU fp32  (OpenMP x4) | 4 | **0.999991** | 0.999989 |
| GPU fp32  (Vulkan)    | 4 | **0.999991** | 0.999989 |
| GPU fp16 storage      | 4 | **0.999948** | 0.999941 |

Latency per 112x112 inference (steady state, n=20):

| Backend | avg | min | max |
|---|---|---|---|
| CPU fp32  | **84.9 ms** | 81.7 | 95.8 |
| GPU fp32  | 456.7 ms | 451.5 | 496.1 |
| GPU fp16  | 436.6 ms | 433.3 | 451.7 |

### Interpretation

- **Correctness**: C++ ncnn ≈ Python onnxruntime (≥ 0.9999 cosine). Well above the
  0.65 match threshold used by the Laravel face-verification flow; even the fp16
  weights (0.99994) are far from any decision boundary.
- **Speed**: on this particular GPU (Adreno 506, 2016-era, fp16 storage but *no* fp16
  arithmetic: `fp16-p/s/u/a=1/0/0/0`), CPU wins at ~85 ms. The Vulkan backend shines on
  newer GPUs with fp16/int8 arithmetic — on P1, GPU is ~5.4× slower for fp32.
- **First-call overhead** includes model load + shader compile (~0.7–1.0 s GPU); steady
  state is what's tabulated.

## Next steps

- C++ SCRFD (detection + 5-point landmarks) + `norm_crop` alignment in ncnn, so full
  images (not just aligned crops) can be scanned end-to-end on-device.
- Swap the Python `/extract` + `/verify` backend on P1 for the C++ binary behind the
  FastAPI-compatible interface (512-d JSON is drop-in compatible).
- Re-benchmark on a device with fp16 arithmetic GPU (e.g. Mali G-series / Adreno 6xx).