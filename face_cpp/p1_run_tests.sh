#!/data/data/com.termux/files/usr/bin/bash
# ================================================================
# P1 — convert w600k_mbf to ncnn, build face_extract, run tests
# Run: bash p1_run_tests.sh
# ================================================================
set -e
export LANG=C
export LD_LIBRARY_PATH=$PREFIX/lib

NCNN=~/ncnn
FACE=~/face_cpp
MODELS=$FACE/models

echo "=== [$(date)] 1/5 onnx -> ncnn ==="
cd $MODELS
$NCNN/build/tools/onnx/onnx2ncnn buffalo_s/w600k_mbf.onnx w600k_mbf.param w600k_mbf.bin 2>&1 | tail -5

echo "--- output blobs in param (should include 516) ---"
grep -o '516' w600k_mbf.param | head -3 || true
tail -1 w600k_mbf.param | head -c 400; echo

echo "=== [$(date)] 2/5 ncnnoptimize fp32 + fp16 ==="
$NCNN/build/tools/ncnnoptimize w600k_mbf.param w600k_mbf.bin w600k_mbf_opt.param w600k_mbf_opt.bin 0 2>&1 | tail -3
$NCNN/build/tools/ncnnoptimize w600k_mbf.param w600k_mbf.bin w600k_mbf_fp16.param w600k_mbf_fp16.bin 65536 2>&1 | tail -3
mkdir -p $FACE/models_fp16
cp w600k_mbf_fp16.param w600k_mbf_fp16.bin $FACE/models_fp16/
ls -la w600k_mbf*.param w600k_mbf*.bin

echo "=== [$(date)] 3/5 build face_extract ==="
cd $FACE
rm -rf build
cmake -B build -DCMAKE_BUILD_TYPE=Release \
  -Dncnn_DIR=$NCNN/build \
  -DCMAKE_CXX_FLAGS="-O2" 2>&1 | tail -8
cmake --build build -j2 2>&1 | tail -8
ls -la build/face_extract

echo "=== [$(date)] 4/5 extract embeddings (CPU fp32) ==="
cd $FACE
./build/face_extract --cpu --model models --bench 5 testdata/crops/*.png 2>cpu_run.err | tee results_cpu.jsonl
tail -3 cpu_run.err

echo "=== [$(date)] 5/5 extract embeddings (GPU fp32) ==="
./build/face_extract --gpu --model models --bench 20 testdata/crops/*.png 2>gpu_run.err | tee results_gpu.jsonl
tail -3 gpu_run.err

echo "=== [$(date)] 6/6 extract embeddings (GPU fp16 storage) ==="
./build/face_extract --gpu --fp16 --model models_fp16 --bench 20 testdata/crops/*.png 2>gpu_fp16_run.err | tee results_gpu_fp16.jsonl
tail -3 gpu_fp16_run.err

echo "=== DONE $(date) ==="