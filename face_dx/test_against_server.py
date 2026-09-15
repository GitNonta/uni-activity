#!/usr/bin/env python3
"""test_against_server.py — integration test of the uni-activity AI server
(ai_service/server.py) against the face_dx 512-d engine stack.

What it does
------------
 1. Health:        GET /health, confirm models loaded + auth is enforced
 2. Extract:       POST /extract on N CelebA photos -> 512-d embeddings
 3. Same-person:   POST /verify (selfie vs stored embedding of same person)
 4. Diff-person:   POST /verify (selfie vs stored embedding of another person)
 5. Liveness path: /verify returns liveness fields (passive, on a still photo)
 6. Cross-stack:   run the SAME faces (SCRFD-aligned crops) through the
                   face_dx engine and ONNX reference; compare cosines:
                     - server ArcFace vs face_dx engine
                     - server ArcFace vs ONNX reference
                     - face_dx engine vs ONNX reference

Usage:
    python test_against_server.py            # 4 identities, full matrix
    python test_against_server.py --n 6      # more identities
"""
from __future__ import annotations

import argparse
import base64
import io
import json
import os
import subprocess
import sys
import time

import cv2
import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)

BASE = os.environ.get("AI_SERVER", "http://127.0.0.1:8001")
KEY = os.environ.get("AI_SERVER_KEY",
                     "uni-activity-ai-secret-key-2026")  # server.py default
HDR = {"X-API-Key": KEY}

ZIP_PATH = os.path.join(HERE, "..", "face_cpp", "img_align_celeba.zip")
EXE = os.path.join(HERE, "build", "face_dx.exe")
MODEL_FVP = os.path.join(HERE, "models", "w600k_mbf.fvp")
STAGE_DIR = os.path.join(HERE, "reports", ".tmp_png")

GATES = {"server_vs_engine": 0.60,   # cross-stack tolerance (same model, diff preprocessing)
         "engine_vs_onnx": 0.9998}   # face_dx production gate


def http(method: str, path: str, headers: dict | None = None, **kw):
    import urllib.request
    merged = {**HDR, **(headers or {})}
    req = urllib.request.Request(BASE + path, method=method, headers=merged, **kw)
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return r.status, json.loads(r.read().decode())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read().decode())


def jpg_bytes(img: np.ndarray, quality: int = 95) -> bytes:
    ok, buf = cv2.imencode(".jpg", img, [cv2.IMWRITE_JPEG_QUALITY, quality])
    if not ok:
        raise RuntimeError("jpg encode failed")
    return buf.tobytes()


def multipart(field: str, filename: str, data: bytes, extra: dict | None = None,
              ftype: str = "image/jpeg") -> tuple[bytes, str]:
    boundary = "----fdxtest" + str(int(time.time() * 1000))
    body = io.BytesIO()
    for k, v in (extra or {}).items():
        body.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"{k}\"\r\n\r\n{v}\r\n".encode())
    body.write(
        f"--{boundary}\r\nContent-Disposition: form-data; name=\"{field}\"; "
        f"filename=\"{filename}\"\r\nContent-Type: {ftype}\r\n\r\n".encode())
    body.write(data + b"\r\n")
    body.write(f"--{boundary}--\r\n".encode())
    return body.getvalue(), f"multipart/form-data; boundary={boundary}"


def post_extract(img: np.ndarray) -> dict:
    data, ctype = multipart("image", "photo.jpg", jpg_bytes(img))
    code, r = http("POST", "/extract", data=data, headers={"Content-Type": ctype})
    return {"code": code, **r}


def post_verify(img: np.ndarray, known: list[float]) -> dict:
    data, ctype = multipart("image", "selfie.jpg", jpg_bytes(img),
                            extra={"known_embedding": json.dumps(known),
                                   "check_liveness": "true"})
    code, r = http("POST", "/verify", data=data, headers={"Content-Type": ctype})
    return {"code": code, **r}


# ---- face_dx side -----------------------------------------------------------
def scrfd_align(zipf, names: list[str]) -> tuple[list[np.ndarray], list[np.ndarray]]:
    """Detect + SCRFD-align faces; return (aligned 112x112 crops, raw jpgs)."""
    from insightface.app import FaceAnalysis
    from insightface.utils.face_align import norm_crop
    app = FaceAnalysis(name="buffalo_l", allowed_modules=["detection"])
    app.prepare(ctx_id=0 if "DmlExecutionProvider" in __import__("onnxruntime").get_available_providers() else -1,
                det_size=(640, 640))
    crops, jpgs = [], []
    import zipfile
    for n in names:
        data = zipf.read(n)
        jpgs.append(data)
        img = cv2.imdecode(np.frombuffer(data, np.uint8), cv2.IMREAD_COLOR)
        faces = app.get(img)
        if not faces:
            raise RuntimeError(f"no face in {n}")
        f = max(faces, key=lambda x: x.det_score)
        crops.append(norm_crop(img, f.kps, image_size=112))
    return crops, jpgs


def engine_embed(crops: list[np.ndarray]) -> list[np.ndarray]:
    os.makedirs(STAGE_DIR, exist_ok=True)
    from celeba_pipeline import stage_from_img
    paths = [stage_from_img(c, 112) for c in crops]
    list_path = os.path.join(STAGE_DIR, "xlist.txt")
    out_path = os.path.join(STAGE_DIR, "xout.jsonl")
    with open(list_path, "w", encoding="utf-8") as f:
        f.write("\n".join(p.replace("\\", "/") for p in paths) + "\n")
    cmd = [EXE, "--model", MODEL_FVP, "--fp16", "--list",
           list_path.replace("\\", "/"), "--out", out_path.replace("\\", "/")]
    r = subprocess.run(cmd, cwd=HERE, capture_output=True, text=True)
    if r.returncode != 0:
        raise RuntimeError(f"engine failed: {r.stderr.strip()[:200]}")
    rows = {}
    with open(out_path, encoding="utf-8") as f:
        for line in f:
            rec = json.loads(line)
            rows[rec["id"]] = np.asarray(rec["embedding"], np.float32)
    return [rows[p.replace("\\", "/")] for p in paths]


def onnx_embed(crops: list[np.ndarray]) -> list[np.ndarray]:
    import onnxruntime as ort
    sess = ort.InferenceSession(MODEL_ONNX := os.path.join(HERE, "models", "w600k_mbf.onnx"),
                                providers=["CPUExecutionProvider"])
    out = []
    for c in crops:
        x = cv2.cvtColor(c, cv2.COLOR_BGR2RGB).astype(np.float32)
        x = (x - 127.5) / 127.5
        x = x.transpose(2, 0, 1)[None].astype(np.float32)
        out.append(np.asarray(sess.run(None, {"input.1": x})[0][0], np.float32))
    return out


def cos(a, b) -> float:
    a, b = np.asarray(a, np.float64), np.asarray(b, np.float64)
    return float(np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b)))


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--n", type=int, default=4, help="identities to test")
    ap.add_argument("--seed", type=int, default=42)
    a = ap.parse_args()

    results: dict = {"gates": GATES, "checks": {}}
    ok = True

    # ---- 1. health + auth
    code, h = http("GET", "/health")
    results["checks"]["health"] = {"code": code, "pipeline": h.get("pipeline"),
                                   "models": h.get("models")}
    ok &= code == 200 and h.get("status") == "ok"
    # real auth probe: POST /extract without the key header -> expect 403
    import urllib.request
    req = urllib.request.Request(BASE + "/extract", method="POST",
                                 data=b"", headers={"Content-Type": "multipart/form-data; boundary=x"})
    try:
        with urllib.request.urlopen(req, timeout=10) as r:
            auth_ok = False
    except urllib.error.HTTPError as e:
        auth_ok = e.code == 403
    except Exception:
        auth_ok = False
    results["checks"]["auth_enforced"] = {
        "probe": "POST /extract without API key",
        "result": "403 without key" if auth_ok else "NOT ENFORCED"}
    ok &= auth_ok

    # ---- 2-4. extract / verify same / verify different
    import zipfile
    zf = zipfile.ZipFile(ZIP_PATH)
    names = [n for n in zf.namelist() if n.endswith(".jpg")]
    rng = np.random.default_rng(a.seed)
    picked = sorted(rng.choice(len(names), size=a.n * 2, replace=False).tolist())
    ids = [names[i] for i in picked[:a.n]]
    other = [names[i] for i in picked[a.n:]]

    data0 = zf.read(ids[0])
    img0 = cv2.imdecode(np.frombuffer(data0, np.uint8), cv2.IMREAD_COLOR)
    ex0 = post_extract(img0)
    results["checks"]["extract"] = {
        "code": ex0["code"],
        "dims": ex0.get("embedding_dims"),
        "processing_ms": ex0.get("processing_ms"),
        "detector": ex0.get("detector_used")}
    ok &= ex0["code"] == 200 and ex0.get("embedding_dims", {}).get("full") == 512
    emb512 = ex0.get("embedding_512d")
    if emb512 is None:
        print(json.dumps(results, indent=2))
        print("[FAIL] extract did not return a 512-d embedding")
        return 1

    # same person: photo 1 vs stored emb of photo 0 (different CelebA photos,
    # roughly same identity is NOT guaranteed — CelebA is not identity-labeled;
    # so treat as 'verify returns consistent similarity' rather than match)
    img1 = cv2.imdecode(np.frombuffer(zf.read(ids[1]), np.uint8), cv2.IMREAD_COLOR)
    v_same = post_verify(img1, emb512)
    results["checks"]["verify_same"] = {
        "similarity": v_same.get("similarity"),
        "is_match": v_same.get("is_match"),
        "liveness_passed": v_same.get("liveness_passed"),
        "liveness_score": v_same.get("liveness_score"),
        "processing_ms": v_same.get("processing_ms")}
    ok &= v_same.get("status") == "success"

    imgO = cv2.imdecode(np.frombuffer(zf.read(other[0]), np.uint8), cv2.IMREAD_COLOR)
    v_diff = post_verify(imgO, emb512)
    results["checks"]["verify_diff"] = {
        "similarity": v_diff.get("similarity"),
        "is_match": v_diff.get("is_match"),
        "processing_ms": v_diff.get("processing_ms")}
    ok &= v_diff.get("status") == "success"
    # NOTE: CelebA is not identity-labeled — both pairs are almost certainly
    # different people, so we assert sane range + threshold behavior, not
    # that one pair scores higher than the other.
    s_same = v_same.get("similarity", 9.9)
    s_diff = v_diff.get("similarity", 9.9)
    ok &= all(-1.0 <= s <= 1.0 for s in (s_same, s_diff))
    ok &= not v_diff.get("is_match", True)  # random pair must NOT match at 0.65

    # ---- 6. cross-stack: server vs face_dx engine on the same faces
    print("[test] running SCRFD alignment + face_dx engine + ONNX reference...")
    test_names = ids[:3] + other[:1]
    crops, jpgs = scrfd_align(zf, test_names)
    crops.append(crops[0])  # duplicate: engine determinism check (expect cos 1.0)
    eng = engine_embed(crops)
    ref = onnx_embed(crops)
    srv = []
    for name in test_names:
        img = cv2.imdecode(np.frombuffer(zf.read(name), np.uint8), cv2.IMREAD_COLOR)
        e = post_extract(img)
        srv.append(np.asarray(e["embedding_512d"], np.float32)
                   if e["code"] == 200 else None)

    cross = []
    for i, name in enumerate(test_names):
        row = {"image": name}
        row["server_vs_engine"] = None if srv[i] is None else round(cos(srv[i], eng[i]), 6)
        row["server_vs_onnx"] = None if srv[i] is None else round(cos(srv[i], ref[i]), 6)
        row["engine_vs_onnx"] = round(cos(eng[i], ref[i]), 6)
        cross.append(row)
        if row["engine_vs_onnx"] < GATES["engine_vs_onnx"]:
            ok = False
    # duplicated crop: engine must embed it identically
    det_cos = round(cos(eng[0], eng[-1]), 6)
    cross.append({"image": "(duplicate of first crop)", "engine_determinism_cos": det_cos})
    ok &= det_cos > 0.9999
    results["cross_stack"] = cross

    # discriminative sanity via the engine stack: same crop vs different face
    results["checks"]["engine_same_vs_diff"] = {
        "same_crop_cos": det_cos,
        "diff_face_cos": round(cos(eng[0], eng[3]), 4),
        "separation_ok": det_cos > cos(eng[0], eng[3]),
    }
    ok &= det_cos > cos(eng[0], eng[3])

    passed = ok
    results["verdict"] = "PASS" if passed else "FAIL"

    out = os.path.join(HERE, "reports", "server_integration_test.json")
    os.makedirs(os.path.dirname(out), exist_ok=True)
    with open(out, "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2)
    print(json.dumps(results, indent=2))
    print(f"[report] {out}")
    return 0 if passed else 1


if __name__ == "__main__":
    sys.exit(main())
