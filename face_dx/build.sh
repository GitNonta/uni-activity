#!/usr/bin/env bash
# build.sh — build face_dx.exe (my own D3D11 compute MobileFaceNet extractor).
#
# Requires the LLVM MinGW toolchain (installed via scoop):
#   scoop install mingw-mstorsjo-llvm-ucrt
# Then: bash face_dx/build.sh
#
# Links only Windows OS components (d3d11, dxgi, d3dcompiler_47) — no
# third-party libraries. The D3D headers in vendor/ are mingw-w64 OS-API
# declarations (like <windows.h>), vendored because the toolchain lacks them.
set -e
cd "$(dirname "$0")"

TC="$HOME/scoop/apps/mingw-mstorsjo-llvm-ucrt/current"
CXX="$TC/bin/x86_64-w64-mingw32-g++"
[ -x "$CXX" ] || { echo "toolchain not found: $CXX (run: scoop install mingw-mstorsjo-llvm-ucrt)"; exit 1; }

mkdir -p build
"$CXX" -O2 -std=c++17 -static \
    -Ivendor \
    main.cpp dx_engine.cpp \
    -o build/face_dx.exe \
    -ld3d11 -ldxgi -ld3dcompiler_47

echo "BUILD-OK: build/face_dx.exe"
echo "Run:  ./build/face_dx.exe --model models/w600k_mbf.fvp --bench 20 ../face_cpp/testdata/crops/*.png"