#!/data/data/com.termux/files/usr/bin/bash
# ================================================================
# P1 (Phone 1 / Termux) — MobileFaceNet (NCNN + Vulkan) build setup
# Installs deps, tops up swap, downloads buffalo_l, builds ncnn.
# Run with: nohup bash p1_setup_build.sh > ~/face_cpp_setup.log 2>&1 &
# ================================================================
set -e
export LANG=C
export LD_LIBRARY_PATH=$PREFIX/lib

echo "=== [$(date)] step 1: packages ==="
pkg install -y ninja git cmake make clang glslang vulkan-headers vulkan-loader-android openssl 2>&1 | tail -5 || true

echo "=== [$(date)] step 2: swap top-up ==="
if [ ! -f ~/face_swap.bin ]; then
    fallocate -l 1536M ~/face_swap.bin || dd if=/dev/zero of=~/face_swap.bin bs=1M count=1536
    chmod 600 ~/face_swap.bin
    mkswap ~/face_swap.bin 2>&1 | tail -1
fi
swapon ~/face_swap.bin 2>&1 | tail -1 || true
free -m | head -2

echo "=== [$(date)] step 3: download buffalo_l (MobileFaceNet w600k_mbf) ==="
mkdir -p ~/face_cpp/models
cd ~/face_cpp/models
if [ ! -f buffalo_l.zip ]; then
    curl -sL -o buffalo_l.zip https://github.com/deepinsight/insightface/releases/download/v0.7/buffalo_l.zip
fi
ls -la buffalo_l.zip
python - <<'EOF'
import zipfile
z = zipfile.ZipFile('/data/data/com.termux/files/home/face_cpp/models/buffalo_l.zip')
z.extractall('/data/data/com.termux/files/home/face_cpp/models/')
print("extracted:", z.namelist())
EOF

echo "=== [$(date)] step 4: clone ncnn ==="
cd ~
if [ ! -d ncnn ]; then
    git clone --depth 1 https://github.com/Tencent/ncnn.git
fi
cd ncnn
echo "ncnn commit: $(git rev-parse --short HEAD)"

echo "=== [$(date)] step 5: cmake configure ==="
cmake -B build -DCMAKE_BUILD_TYPE=Release \
  -DNCNN_VULKAN=ON \
  -DNCNN_BUILD_TOOLS=ON \
  -DNCNN_BUILD_EXAMPLES=OFF \
  -DNCNN_BUILD_TESTS=OFF \
  -DNCNN_BUILD_BENCHMARK=OFF \
  -DNCNN_BUILD_PYTHON=OFF \
  -DNCNN_SYSTEM_GLSLANG=ON \
  -DNCNN_SHARED_LIB=OFF \
  -DNCNN_BUILD_CPLUSPLUS=ON \
  -DCMAKE_CXX_FLAGS="-O2" 2>&1 | tail -20

echo "=== [$(date)] step 6: build (this takes a while) ==="
cmake --build build -j2 2>&1 | tail -30

echo "=== [$(date)] step 7: verify tools ==="
ls -la ~/ncnn/build/tools/onnx/onnx2ncnn ~/ncnn/build/tools/ncnnoptimize 2>&1

echo "=== [$(date)] DONE ==="