#!/usr/bin/env bash
# build_dll.sh — build face_dx.dll from the stable C ABI (fdx_capi.h).
#
# Produces a self-contained deployment unit in build/:
#   face_dx.dll          the engine (exports fdx_* — see fdx_capi.h)
#   shaders/             HLSL kernels the DLL compiles at runtime from its
#                        OWN directory (it self-locates via its module path,
#                        so the host process CWD is irrelevant)
#   face_dx.def          explicit export list (kept in sync with fdx_capi.h)
#   face_dx.lib + fdx.lib  import libraries for link-time consumers
#   fdx_capi.h           the ABI header hosts compile against
#
# Depends only on the Windows OS (d3d11/dxgi/d3dcompiler_47) — same as the exe.
set -e
cd "$(dirname "$0")"

TC="$HOME/scoop/apps/mingw-mstorsjo-llvm-ucrt/current"
CXX="$TC/bin/x86_64-w64-mingw32-g++"
DLLTOOL="$TC/bin/x86_64-w64-mingw32-dlltool"
[ -x "$CXX" ] || { echo "toolchain not found: $CXX (run: scoop install mingw-mstorsjo-llvm-ucrt)"; exit 1; }

mkdir -p build/shaders
cp shaders/*.hlsl build/shaders/

"$CXX" -O2 -std=c++17 -static -shared \
    -Ivendor \
    fdx_capi.cpp dx_engine.cpp \
    -o build/face_dx.dll \
    -Wl,--out-implib,build/fdx.lib \
    -ld3d11 -ldxgi -ld3dcompiler_47

# explicit export list — must match the FDX_API functions in fdx_capi.h
cat > build/face_dx.def <<'EOF'
LIBRARY face_dx
EXPORTS
    fdx_abi_version
    fdx_gpu_count
    fdx_model_load
    fdx_model_free
    fdx_engine_create
    fdx_engine_free
    fdx_engine_run
    fdx_engine_reinit
    fdx_engine_describe
EOF

# import lib for MSVC-style link-time consumers (the -Wl,--out-implib copy
# above serves GNU ld consumers)
"$DLLTOOL" -d build/face_dx.def -l build/face_dx.lib -D face_dx.dll

cp fdx_capi.h build/fdx_capi.h

# zero-dependency C runner (LoadLibrary/GetProcAddress only — no import lib,
# no fdx_capi.h). Doubles as the canonical example of dynamic consumption.
CC="$TC/bin/x86_64-w64-mingw32-gcc"
"$CC" -O2 -Wall -Wextra fdx_runner.c -o build/face_dx_runner.exe

echo "BUILD-OK: build/face_dx.dll"
echo "Deploy as: face_dx.dll + shaders/ + fdx_capi.h (one folder, nothing else)"
echo "Link:  build/fdx.lib (GNU) or build/face_dx.lib (dlltool, MSVC-style)"
echo "Smoke: build/face_dx_runner.exe --dll build/face_dx.dll --model models/w600k_mbf.fvp \\\n         --list <raws.txt> --out out.jsonl [--fp16]"
