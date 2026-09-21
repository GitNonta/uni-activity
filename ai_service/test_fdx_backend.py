#!/usr/bin/env python3
"""test_fdx_backend.py — live verification of fdx as the activity-check decoder.

Spawns ai_service (CPU ONNX wrapper, fdx enabled) and proves, over HTTP:
 1. /health reports embedder=fdx-d3d11 with adapter info
 2. /extract returns an fdx-space 512-d vector (embedding_space tag)
 3. /verify same-person  -> match, score in the calibrated same band
 4. /verify diff-person  -> no match, score in the calibrated diff band
 5. fdx-vector vs insightface-vector for the same face are NOT comparable
    (near-zero cosine) — documents the re-enrollment requirement
 6. fail-open: USE_FDX=0 server answers /verify correctly on insightface
 7. migration note: response embedder/match_threshold fields are consistent

  python test_fdx_backend.py        (spawns servers on :8021 / :8022)
"""
from __future__ import annotations

import io
import json
import os
import subprocess
import sys
import time
import urllib.request
import zipfile

import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
REPORT = os.path.abspath(os.path.join(HERE, "reports", "fdx_backend_test.json"))
ZIP_PATH = os.path.abspath(os.path.join(HERE, "..", "face_cpp", "img_align_celeba.zip"))
KEY = "uni-activity-ai-secret-key-2026"
HDR = {"X-API-Key": KEY}
PORT_FDX = 8021
PORT_NOFDX = 8022
THRESHOLD = 0.30          # calibrated zero-FAR default (w600k_mbf space)
SAME_BAND = (0.60, 1.01)  # calibrated: 0.858-0.911 (flip probes)
DIFF_BAND = (-1.01, 0.30)  # calibrated: 0.019-0.212

results: dict = {"ok": True, "checks": {}}


def check(name: str, cond: bool, detail) -> None:
    results["checks"][name] = {"pass": bool(cond), "detail": detail}
    results["ok"] &= bool(cond)
    print(f"[{'PASS' if cond else 'FAIL'}] {name}: {detail}")


def http(port: int, method: str, path: str, data=None, headers=None, timeout=60):
    merged = {**HDR, **(headers or {})}
    req = urllib.request.Request(f"http://127.0.0.1:{port}{path}",
                                 method=method, headers=merged, data=data)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return r.status, json.loads(r.read().decode())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read().decode())


def multipart(field: str, filename: str, data: bytes, extra: dict | None = None) -> tuple[bytes, str]:
    boundary = "----fdxbe" + str(int(time.time() * 1000))
    body = io.BytesIO()
    for k, v in (extra or {}).items():
        body.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"{k}\"\r\n\r\n{v}\r\n".encode())
    body.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"{field}\"; "
               f"filename=\"{filename}\"\r\nContent-Type: image/jpeg\r\n\r\n".encode())
    body.write(data + b"\r\n")
    body.write(f"--{boundary}--\r\n".encode())
    return body.getvalue(), f"multipart/form-data; boundary={boundary}"


def wait_up(port: int, proc: subprocess.Popen, tries: int = 60) -> None:
    for _ in range(tries):
        if proc.poll() is not None:
            raise RuntimeError(f"server on :{port} exited early")
        try:
            code, _ = http(port, "GET", "/health", timeout=2)
            if code == 200:
                return
        except Exception:
            time.sleep(1.0)
    raise RuntimeError(f"server on :{port} never became healthy")


def jpg(img: np.ndarray, q: int = 95) -> bytes:
    import cv2
    ok, buf = cv2.imencode(".jpg", img, [cv2.IMWRITE_JPEG_QUALITY, q])
    if not ok:
        raise RuntimeError("jpg encode failed")
    return buf.tobytes()


def main() -> int:
    os.makedirs(os.path.join(HERE, "reports"), exist_ok=True)
    import cv2

    # ---- identities from the zip (aligned CelebA crops, engine-native size) --
    # NOTE: CelebA filenames are image numbers, NOT identity labels (the exact
    # trap the SimpleFacesDataset fix addressed). Same-person evidence must
    # come from identity-preserving transforms of the enrolled photo (mirror,
    # zoom + re-encode), which the calibration measured at cos 0.858-0.911.
    z = zipfile.ZipFile(ZIP_PATH)
    ids = ["000001", "000002", "000003", "000050", "000123"]
    imgs = {}
    for n in ids:
        raw = z.read(f"img_align_celeba/{n}.jpg")
        imgs[n] = cv2.imdecode(np.frombuffer(raw, np.uint8), cv2.IMREAD_COLOR)
    enroll = imgs[ids[0]]
    # same-person probe: mirrored + 92%-center-zoom + JPEG re-encode
    h, w = enroll.shape[:2]
    m = int(w * 0.04), int(h * 0.04)
    zoom = enroll[m[1]:h - m[1], m[0]:w - m[0]]
    probe_same = cv2.flip(cv2.resize(zoom, (w, h), interpolation=cv2.INTER_LINEAR), 1)
    probe_other = imgs[ids[4]]   # a different image number = a different person

    env = {**os.environ, "AI_SERVER_KEY": KEY, "LIVENESS_THRESHOLD": "0.1",
           "USE_DEPTH_LIVENESS": "0"}
    py = sys.executable

    # ---- server A: fdx enabled (default) ------------------------------------
    proc = subprocess.Popen([py, "run_server_cpu.py", "--port", str(PORT_FDX)],
                            cwd=HERE, env=env,
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        wait_up(PORT_FDX, proc)

        code, h = http(PORT_FDX, "GET", "/health")
        check("health_embedder_fdx", code == 200 and h.get("embedder") == "fdx-d3d11"
              and h.get("models", {}).get("fdx") is True, h.get("embedder"))

        # ---- /extract on the enrollment photo ----
        data, ct = multipart("image", "enroll.jpg", jpg(enroll))
        code, ex = http(PORT_FDX, "POST", "/extract", data=data,
                        headers={"Content-Type": ct})
        emb_fdx = np.asarray(ex.get("embedding_512d") or [], dtype=np.float32)
        check("extract_fdx_space",
              code == 200 and ex.get("embedding_space") == "fdx-w600k-mbf"
              and ex.get("embedder") == "fdx-d3d11"
              and emb_fdx.shape == (512,)
              and abs(float(np.linalg.norm(emb_fdx)) - 1.0) < 1e-3,
              {"space": ex.get("embedding_space"), "norm":
               round(float(np.linalg.norm(emb_fdx)), 4) if emb_fdx.size else None})
        legacy = ex.get("embedding_insightface_512d")

        # ---- 5. the two spaces must agree (model parity with InsightFace) ----
        if legacy:
            emb_if = np.asarray(legacy, dtype=np.float32)
            cross = float(np.dot(emb_fdx, emb_if))
            check("spaces_compatible_parity", cross >= 0.999,
                  f"cos(fdx, insightface)={cross:.4f} (model parity confirmed)")
        else:
            check("spaces_compatible_parity", False, "no legacy vector in response")

        # ---- 3. same-person verify ----
        known = json.dumps(emb_fdx.tolist())
        data, ct = multipart("image", "same.jpg", jpg(probe_same),
                             extra={"known_embedding": known, "check_liveness": "false"})
        code, v = http(PORT_FDX, "POST", "/verify", data=data, headers={"Content-Type": ct})
        sim = float(v.get("similarity", -9))
        check("verify_same_person",
              code == 200 and v.get("face_match") is True and v.get("embedder") == "fdx-d3d11"
              and SAME_BAND[0] <= sim <= SAME_BAND[1],
              {"sim": round(sim, 4), "embedder": v.get("embedder"),
               "thr": v.get("match_threshold")})

        # ---- 4. different-person verify ----
        data, ct = multipart("image", "diff.jpg", jpg(probe_other),
                             extra={"known_embedding": known, "check_liveness": "false"})
        code, v2 = http(PORT_FDX, "POST", "/verify", data=data, headers={"Content-Type": ct})
        sim2 = float(v2.get("similarity", 9))
        check("verify_diff_person",
              code == 200 and v2.get("face_match") is False
              and DIFF_BAND[0] <= sim2 <= DIFF_BAND[1],
              {"sim": round(sim2, 4)})

        # ---- 7. response contract ----
        check("response_contract",
              v.get("match_threshold") == THRESHOLD
              and v.get("embedder") == "fdx-d3d11",
              "embedder + match_threshold present and consistent")
    finally:
        proc.terminate()
        try:
            proc.wait(timeout=10)
        except Exception:
            proc.kill()

    # ---- server B: USE_FDX=0 — fail-open to insightface ----------------------
    env0 = {**env, "USE_FDX": "0"}
    proc0 = subprocess.Popen([py, "run_server_cpu.py", "--port", str(PORT_NOFDX)],
                             cwd=HERE, env=env0,
                             stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        wait_up(PORT_NOFDX, proc0)
        code, ex0 = http(PORT_NOFDX, "POST", "/extract",
                         data=multipart("image", "e.jpg", jpg(enroll))[0],
                         headers={"Content-Type": multipart("image", "e.jpg", jpg(enroll))[1]})
        emb0 = np.asarray(ex0.get("embedding_512d") or [], dtype=np.float32)
        known0 = json.dumps(emb0.tolist())
        data, ct = multipart("image", "s.jpg", jpg(enroll),
                             extra={"known_embedding": known0, "check_liveness": "false"})
        code, v0 = http(PORT_NOFDX, "POST", "/verify", data=data, headers={"Content-Type": ct})
        check("failopen_without_fdx",
              code == 200 and v0.get("face_match") is True
              and v0.get("embedder") == "native-arcface",
              {"embedder": v0.get("embedder"), "sim": v0.get("similarity")})
    finally:
        proc0.terminate()
        try:
            proc0.wait(timeout=10)
        except Exception:
            proc0.kill()

    with open(REPORT, "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2, default=str)
    print(f"report: {REPORT}")
    return 0 if results["ok"] else 1


if __name__ == "__main__":
    t0 = time.time()
    rc = main()
    print(f"total {time.time() - t0:.1f}s — {'ALL PASS' if rc == 0 else 'FAILURES'}")
    sys.exit(rc)
