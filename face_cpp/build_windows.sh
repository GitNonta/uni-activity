#!/bin/bash
# ============================================================================
# build_windows.sh — build face_extract.exe on Windows (Git Bash)
# ----------------------------------------------------------------------------
# No MSVC/Windows-SDK or OpenCV needed. Uses:
#   - MinGW-w64 (LLVM) toolchain via scoop:  scoop install mingw-mstorsjo-llvm-ucrt cmake ninja
#   - ncnn built from source with Vulkan (NCNN_SIMPLEVK dlopens vulkan-1.dll)
#   - stb_image for image loading (-DFACE_USE_STB)
#
# Usage: bash build_windows.sh   (run from face_cpp/)
# ============================================================================
set -e
cd "$(dirname "$0")"

export PATH="$HOME/scoop/apps/mingw-mstorsjo-llvm-ucrt/current/bin:$PATH"

NCNN_SRC=pnnx/ncnn-src
VK_INC=pnnx/vulkan-headers/Vulkan-Headers-1.4.304/include

# --- 1. ncnn (only if not built yet) -----------------------------------------
if [ ! -f "$NCNN_SRC/build/src/libncnn.a" ]; then
    if [ ! -d "$NCNN_SRC" ]; then
        git clone --depth 1 --recursive https://github.com/Tencent/ncnn.git "$NCNN_SRC"
    fi
    cmake -B "$NCNN_SRC/build" -G Ninja -DCMAKE_BUILD_TYPE=Release \
        -DCMAKE_C_COMPILER=clang -DCMAKE_CXX_COMPILER=clang++ \
        -DNCNN_VULKAN=ON -DNCNN_SIMPLEVK=ON \
        -DNCNN_BUILD_TOOLS=OFF -DNCNN_BUILD_EXAMPLES=OFF \
        -DNCNN_BUILD_TESTS=OFF -DNCNN_BUILD_BENCHMARK=OFF \
        -DNCNN_OPENMP=OFF
    ninja -C "$NCNN_SRC/build"
fi

# --- 2. Vulkan headers (if not present) --------------------------------------
if [ ! -f "$VK_INC/vulkan/vulkan.h" ]; then
    mkdir -p pnnx/vulkan-headers
    curl -sL -o /tmp/vk-h.zip https://github.com/KhronosGroup/Vulkan-Headers/archive/refs/tags/v1.4.304.zip
    python -c "import zipfile; zipfile.ZipFile('/tmp/vk-h.zip').extractall('pnnx/vulkan-headers')"
fi

# --- 3. face_extract.exe ------------------------------------------------------
clang++ -O2 -std=c++17 -DFACE_USE_STB \
  -I "$NCNN_SRC/src" -I "$NCNN_SRC/build/src" -I "$VK_INC" -I . \
  face_extract.cpp -o face_extract.exe \
  "$NCNN_SRC/build/src/libncnn.a" \
  "$NCNN_SRC/build/glslang/glslang/libglslang.a" \
  "$NCNN_SRC/build/glslang/glslang/libMachineIndependent.a" \
  "$NCNN_SRC/build/glslang/SPIRV/libSPIRV.a" \
  "$NCNN_SRC/build/glslang/glslang/libGenericCodeGen.a" \
  "$NCNN_SRC/build/glslang/glslang/OSDependent/Windows/libOSDependent.a" \
  "$NCNN_SRC/build/glslang/glslang/libglslang-default-resource-limits.a"

echo "BUILD-OK: face_extract.exe"
echo "Run:  export PATH=\"\$HOME/scoop/apps/mingw-mstorsjo-llvm-ucrt/current/bin:\$PATH\""
echo "      ./face_extract.exe --gpu --model models/win_fp32 --bench 20 testdata/crops/*.png"