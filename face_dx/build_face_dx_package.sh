#!/usr/bin/env bash
# build_face_dx_package.sh — assemble the fdx wheel payload and build it.
#
# The fdx Python library (pylib/) expects the native engine INSIDE the
# package: fdx/_native/{face_dx.dll, fdx_capi.h, shaders/, models/}.
# This script copies the current build artifacts + bundled model into
# place (regenerating _native/, which is gitignored), then builds a wheel
# tagged py3-win_amd64.
#
# Prerequisites: bash build_dll.sh (DLL + shaders + header), and
# models/w600k_mbf.fvp (the fp32-storage model every suite verifies; the
# engine packs weights to fp16 internally when fp16=1).
set -e
cd "$(dirname "$0")"

DLL_DIR=build
PYLIB=pylib
NATIVE="$PYLIB/fdx/_native"

[ -f "$DLL_DIR/face_dx.dll" ] || { echo "ERROR: build/face_dx.dll missing — run: bash build_dll.sh"; exit 1; }
[ -f models/w600k_mbf.fvp ] || { echo "ERROR: models/w600k_mbf.fvp missing"; exit 1; }

mkdir -p "$NATIVE/shaders" "$NATIVE/models"
cp "$DLL_DIR/face_dx.dll" "$NATIVE/"
cp "$DLL_DIR/fdx_capi.h"  "$NATIVE/"
cp shaders/*.hlsl         "$NATIVE/shaders/"
cp models/w600k_mbf.fvp "$NATIVE/models/w600k_mbf.fvp"

echo "payload:"
find "$NATIVE" -type f -printf "  %p  (%s bytes)\n"

# keep the assembled payload + build outputs out of git
cat > "$PYLIB/.gitignore" <<'EOF'
fdx/_native/
dist/
build/
*.egg-info/
.venv*/
__pycache__/
EOF

cd "$PYLIB"
rm -rf build dist *.egg-info
python setup.py bdist_wheel --python-tag py3 --plat-name win_amd64 >/dev/null
echo "wheel:"
ls -la dist/
echo "install:  pip install pylib/dist/fdx-1.0.0-py3-win_amd64.whl"
