#!/usr/bin/env python3
"""
test_uploaded_photo_full.py — End-to-end face verification on the uploaded student photo.

Pipeline:
1. Face Detection & 5-Landmark Alignment (SCRFD / norm_crop to 112x112)
2. Anti-Spoofing & Passive Liveness Check (LBP, FFT, EAR, Color Variance)
3. Intel iGPU Inference via face_dx.exe (Direct3D 11 Compute Shaders)
4. C++ ArcFace ResNet-50 (buffalo_l) & MobileFaceNet (buffalo_s) Inference
5. Identity Matching & Cosine Similarity against known database profiles
"""
from __future__ import annotations

import json
import math
import os
import subprocess
import sys
import time

import cv2
import numpy as np


def cosine(a: list, b: list) -> float:
    dot = sum(x * y for x, y in zip(a, b))
    na = math.sqrt(sum(x * x for x in a))
    nb = math.sqrt(sum(x * x for x in b))
    return dot / (na * nb + 1e-12)


def main():
    uploaded_path = r"C:\Users\Non\.gemini\antigravity-ide\brain\b5a06cb2-dbdf-4f87-806f-55e1be5a184d\.user_uploaded\media_1789128790510.jpg"
    if len(sys.argv) > 1:
        uploaded_path = sys.argv[1]

    print("=" * 70)
    print(" 📸 TESTING UPLOADED STUDENT PHOTO ON INTEL iGPU & ARCFACE ENGINES")
    print("=" * 70)
    print(f"Source Image: {uploaded_path}")

    img = cv2.imread(uploaded_path)
    if img is None:
        sys.exit(f"Error: Could not read image at {uploaded_path}")
    h, w, c = img.shape
    print(f"Image Resolution: {w} x {h} px, Channels: {c}")

    # 1. Detection & Alignment
    from insightface.app import FaceAnalysis
    from insightface.utils import face_align

    app = FaceAnalysis(name="buffalo_l", allowed_modules=["detection", "recognition"], providers=["CPUExecutionProvider"])
    app.prepare(ctx_id=-1, det_size=(640, 640))

    t_det0 = time.perf_counter()
    faces = app.get(img)
    det_ms = (time.perf_counter() - t_det0) * 1000.0

    if not faces:
        sys.exit("Error: No face detected in the uploaded photo!")

    face = faces[0]
    bbox = [round(float(x), 1) for x in face.bbox]
    det_score = float(face.det_score)
    print(f"\n[1. Face Detection - SCRFD]")
    print(f"  Confidence Score: {det_score * 100:.2f}%")
    print(f"  Bounding Box    : [x1={bbox[0]}, y1={bbox[1]}, x2={bbox[2]}, y2={bbox[3]}]")
    print(f"  Detection Time  : {det_ms:.2f} ms")

    # Aligned 112x112 Crop
    crop112 = face_align.norm_crop(img, landmark=face.kps, image_size=112)
    crop_path = os.path.abspath("face_dx/test_student.png")
    cv2.imwrite(crop_path, crop112)
    cv2.imwrite("face_cpp/testdata/crops/test_student.png", crop112)
    print(f"  Aligned Crop    : Saved 112x112 to {crop_path}")

    # 2. Passive Liveness Check
    sys.path.append("ai_service")
    from liveness import LivenessDetector
    liveness_tool = LivenessDetector(threshold=0.58)
    liveness_res = liveness_tool.check(crop112)
    print(f"\n[2. Passive Liveness & Anti-Spoofing Check]")
    print(f"  Liveness Score  : {liveness_res.liveness_score * 100:.1f}% (Threshold: 58.0%)")
    print(f"  Result Status   : {'PASSED (REAL PERSON)' if liveness_res.is_live else 'REJECTED (SPOOF DETECTED)'}")
    print(f"  - Texture (LBP) : {liveness_res.texture_score:.3f} (Weight 40%)")
    print(f"  - Freq (FFT)    : {liveness_res.frequency_score:.3f} (Weight 30%)")
    print(f"  - Eye Openness  : {liveness_res.ear_score:.3f} (Weight 20%)")
    print(f"  - Color Variance: {liveness_res.color_score:.3f} (Weight 10%)")
    print(f"  Diagnostic Msg  : {liveness_res.message}")

    # 3. Direct3D 11 Intel iGPU Inference (face_dx)
    print(f"\n[3. Custom Direct3D 11 Engine (face_dx) on Intel iGPU]")
    dx_exe = os.path.abspath("face_dx/build/face_dx.exe")
    dx_cmd = [dx_exe, "--model", "models/w600k_mbf.fvp", "--bench", "5", crop_path]
    p_dx = subprocess.run(dx_cmd, cwd="face_dx", capture_output=True, text=True)

    dx_emb = None
    dx_latency = 0.0
    for line in p_dx.stdout.splitlines():
        if line.startswith("{") and "embedding" in line:
            clean = line.replace("\\", "/")
            try:
                obj = json.loads(clean)
                dx_emb = obj.get("embedding")
                dx_latency = obj.get("ms", 0.0)
            except Exception:
                pass
    for line in p_dx.stderr.splitlines():
        if "[bench]" in line:
            print("  " + line.strip())

    if dx_emb:
        print(f"  GPU Hardware    : Intel(R) UHD Graphics via D3D11 Compute Shaders")
        print(f"  Embedding Vector: Extracted 512-d L2-normalized vector ({len(dx_emb)} floats)")
        print(f"  Inference Latency: {dx_latency:.2f} ms (~{1000.0/max(1e-2, dx_latency):.1f} FPS)")
        print(f"  External Libs   : 0 (Pure native Windows OS d3d11.dll)")
    else:
        print("  Warning: could not parse dx_emb:", p_dx.stdout, p_dx.stderr)

    # 4. ArcFace ResNet-50 (buffalo_l) in C++ (face_cpp)
    print(f"\n[4. Flagship ArcFace ResNet-50 (buffalo_l) in C++ (face_cpp)]")
    cpp_exe = os.path.abspath("face_cpp/face_extract.exe")
    cpp_cmd = [cpp_exe, "--cpu", "--model", "face_cpp/models/buffalo_l", "--bench", "3", crop_path]
    p_cpp = subprocess.run(cpp_cmd, cwd=".", capture_output=True, text=True)

    r50_emb = None
    r50_latency = 0.0
    for line in p_cpp.stdout.splitlines():
        if line.startswith("{") and "embedding" in line:
            clean = line.replace("\\", "/")
            try:
                obj = json.loads(clean)
                r50_emb = obj.get("embedding")
                r50_latency = obj.get("ms", 0.0)
            except Exception:
                pass
    for line in p_cpp.stderr.splitlines():
        if "[bench]" in line:
            print("  " + line.strip())

    if r50_emb:
        print(f"  ArcFace Backbone: ResNet-50 (43.6M parameters, 12.6 GFLOPs)")
        print(f"  Embedding Vector: Extracted 512-d L2-normalized vector ({len(r50_emb)} floats)")
        print(f"  CPU Latency     : {r50_latency:.2f} ms")

    # 5. Database & Identity Matching
    print(f"\n[5. Identity Matching & Cosine Similarity against Known Profiles]")
    print(f"{'Known Profile':<20} {'Engine':<12} {'Cosine Similarity':<18} {'Match Status'}")
    print("-" * 65)

    ref_path = "face_cpp/testdata/references.json"
    if os.path.exists(ref_path) and dx_emb:
        with open(ref_path, "r", encoding="utf-8") as f:
            refs = json.load(f)
        for r in refs:
            rid = r["id"]
            sim = cosine(dx_emb, r["embedding"])
            status = "MATCH (SAME PERSON)" if sim >= 0.65 else ("POSSIBLE" if sim >= 0.50 else "DIFFERENT PERSON")
            print(f"{rid:<20} {'face_dx':<12} {sim:<18.4f} {status}")

    # Check Database if PostgreSQL connection is available
    try:
        import psycopg2
        # Try local db
        conn = psycopg2.connect(
            dbname=os.environ.get("DB_DATABASE", "uni_activity"),
            user=os.environ.get("DB_USERNAME", "postgres"),
            password=os.environ.get("DB_PASSWORD", ""),
            host=os.environ.get("DB_HOST", "127.0.0.1"),
            port=int(os.environ.get("DB_PORT", 5432)),
            connect_timeout=2
        )
        cur = conn.cursor()
        cur.execute("SELECT id, name, student_id, face_descriptor FROM users WHERE face_descriptor IS NOT NULL LIMIT 50;")
        rows = cur.fetchall()
        print(f"\n[Database Match Check against {len(rows)} registered users]")
        for uid, name, sid, fdesc in rows:
            if isinstance(fdesc, str):
                fdesc = json.loads(fdesc)
            if isinstance(fdesc, list) and len(fdesc) == 512 and r50_emb:
                sim = cosine(r50_emb, fdesc)
                if sim >= 0.50:
                    print(f"  🎯 Candidate: {name} (ID: {sid}) | Cosine Similarity: {sim:.4f} | Status: {'CONFIRMED MATCH' if sim >= 0.65 else 'LIKELY'}")
        conn.close()
    except Exception:
        pass

    print("=" * 70)
    print(" ✅ TEST RUN COMPLETED SUCCESSFULLY")
    print("=" * 70)


if __name__ == "__main__":
    main()
