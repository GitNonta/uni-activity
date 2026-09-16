#!/usr/bin/env python3
"""probe_lan.py — end-to-end smoke of the AI face server over the LAN.

Mimics exactly what the Laravel backend does against the model host:
  /extract (enroll a profile photo)  -> stored 512-d fdx vector
  /verify  (check-in selfie)         -> same person must MATCH
  /verify  (different person)        -> must NOT match

Target: the machine hosting the fdx model (default 192.168.1.33:8001).
Identities come from the aligned CelebA zip (proven detectable crops).
"""
import io
import json
import os
import sys
import urllib.request
import uuid
import zipfile

import cv2
import numpy as np

HOST = sys.argv[1] if len(sys.argv) > 1 else "192.168.1.33:8001"
BASE = f"http://{HOST}"
KEY = "uni-activity-ai-secret-key-2026"
ZIP_PATH = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "face_cpp", "img_align_celeba.zip"))


def jpg(img: np.ndarray, q: int = 95) -> bytes:
    ok, buf = cv2.imencode(".jpg", img, [cv2.IMWRITE_JPEG_QUALITY, q])
    if not ok:
        raise RuntimeError("jpg encode failed")
    return buf.tobytes()


def post(path: str, data: bytes, content_type: str) -> dict:
    req = urllib.request.Request(BASE + path, data=data, method="POST")
    req.add_header("Content-Type", content_type)
    req.add_header("X-API-Key", KEY)
    with urllib.request.urlopen(req, timeout=60) as resp:
        return json.loads(resp.read().decode())


def multipart_image(field: str, filename: str, payload: bytes,
                    extra: dict | None = None) -> tuple[bytes, str]:
    boundary = uuid.uuid4().hex
    body = io.BytesIO()
    for k, v in (extra or {}).items():
        body.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"{k}\"\r\n\r\n{v}\r\n".encode())
    body.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"{field}\"; filename=\"{filename}\"\r\n"
               f"Content-Type: image/jpeg\r\n\r\n".encode())
    body.write(payload)
    body.write(f"\r\n--{boundary}--\r\n".encode())
    return body.getvalue(), f"multipart/form-data; boundary={boundary}"


def main() -> int:
    print(f"target: {BASE}")
    # health first
    with urllib.request.urlopen(BASE + "/health", timeout=10) as r:
        h = json.loads(r.read().decode())
    print(f"health  : embedder={h.get('embedder')} fdx={h.get('models', {}).get('fdx')}")

    z = zipfile.ZipFile(ZIP_PATH)
    names = [n for n in z.namelist() if n.startswith("img_align_celeba/") and n.endswith(".jpg")]
    a, b = z.read(names[11]), z.read(names[12])   # two different identities
    print(f"images  : {names[11].split('/')[-1]} (enroll), {names[12].split('/')[-1]} (other)")

    # enroll identity A
    d, ct = multipart_image("image", "enroll.jpg", a)
    ext = post("/extract", d, ct)
    emb = ext.get("embedding_512d")
    if not emb:
        print(f"FAIL /extract: {ext}")
        return 1
    print(f"extract : space={ext.get('embedding_space')} dims={len(emb)} "
          f"norm={np.linalg.norm(np.asarray(emb)):.4f} ms={ext.get('processing_ms')}")
    stored = json.dumps(emb)

    # same-person probe: identity-preserving transform (mirror + slight zoom)
    img = cv2.imdecode(np.frombuffer(a, np.uint8), cv2.IMREAD_COLOR)
    probe = cv2.resize(cv2.flip(img, 1), None, fx=1.08, fy=1.08)
    d, ct = multipart_image("image", "selfie.jpg", jpg(probe), {"known_embedding": stored})
    v1 = post("/verify", d, ct)
    print(f"verify A: sim={v1.get('similarity')} face_match={v1.get('face_match')} "
          f"final={v1.get('is_match')} live={v1.get('liveness_passed')}")

    # different-person probe
    d, ct = multipart_image("image", "selfie.jpg", b, {"known_embedding": stored})
    v2 = post("/verify", d, ct)
    print(f"verify B: sim={v2.get('similarity')} face_match={v2.get('face_match')}")

    ok = (v1.get("face_match") is True) and (v2.get("face_match") is False)
    print("RESULT  :", "PASS" if ok else "FAIL")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
