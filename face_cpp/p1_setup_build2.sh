#!/data/data/com.termux/files/usr/bin/bash
# ================================================================
# P1 — ncnn build follow-up: fetch glslang submodule, rebuild
# ================================================================
set -e
export LANG=C

cd ~/ncnn
echo "=== [$(date)] submodule init ==="
git submodule update --init --depth 1 2>&1 | tail -3

echo "=== [$(date)] clean + configure ==="
rm -rf build
cmake -B build -DCMAKE_BUILD_TYPE=Release \
  -DNCNN_VULKAN=ON \
  -DNCNN_BUILD_TOOLS=ON \
  -DNCNN_BUILD_EXAMPLES=OFF \
  -DNCNN_BUILD_TESTS=OFF \
  -DNCNN_BUILD_BENCHMARK=OFF \
  -DNCNN_BUILD_PYTHON=OFF \
  -DNCNN_SHARED_LIB=OFF \
  -DNCNN_BUILD_CPLUSPLUS=ON \
  -DCMAKE_CXX_FLAGS="-O2" 2>&1 | tail -12

echo "=== [$(date)] build -j2 ==="
cmake --build build -j2 2>&1 | tail -25

echo "=== [$(date)] verify tools ==="
ls -la ~/ncnn/build/tools/onnx/onnx2ncnn ~/ncnn/build/tools/ncnnoptimize 2>&1

echo "=== [$(date)] DONE ==="