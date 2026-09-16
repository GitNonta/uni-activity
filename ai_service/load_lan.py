#!/usr/bin/env python3
"""load_lan.py — sustained /verify load against the local AI server.

One /extract to enroll, then N worker threads loop /verify with
identity-preserving transforms until the time budget expires. Prints one
line per request so the GPU sampler output can be correlated with load.

Usage: python load_lan.py [host:port] [seconds] [workers]
"""
import io
import json
import sys
import threading
import time
import urllib.request
import uuid
import zipfile

import cv2
import numpy as np

HOST = sys.argv[1] if len(sys.argv) > 1 else "127.0.0.1:8001"
BUDGET = float(sys.argv[2]) if len(sys.argv) > 2 else 20.0
WORKERS = int(sys.argv[3]) if len(sys.argv) > 3 else 3
BASE = f"http://{HOST}"
KEY = "uni-activity-ai-secret-key-2026"
ZIP_PATH = r"D:\projects\uni-activity\face_cpp\img_align_celeba.zip"

_lock = threading.Lock()
_done = 0


def jpg(img: np.ndarray, q: int = 95) -> bytes:
    ok, buf = cv2.imencode(".jpg", img, [cv2.IMWRITE_JPEG_QUALITY, q])
    return buf.tobytes()


def post(path: str, data: bytes, ct: str) -> dict:
    req = urllib.request.Request(BASE + path, data=data, method="POST")
    req.add_header("Content-Type", ct)
    req.add_header("X-API-Key", KEY)
    with urllib.request.urlopen(req, timeout=60) as r:
        return json.loads(r.read().decode())


def multipart_image(field: str, fname: str, payload: bytes, extra: dict) -> tuple[bytes, str]:
    b = uuid.uuid4().hex
    out = io.BytesIO()
    for k, v in extra.items():
        out.write(f"--{b}\r\nContent-Disposition: form-data; name=\"{k}\"\r\n\r\n{v}\r\n".encode())
    out.write(f"--{b}\r\nContent-Disposition: form-data; name=\"{field}\"; filename=\"{fname}\"\r\n"
              f"Content-Type: image/jpeg\r\n\r\n".encode())
    out.write(payload)
    out.write(f"\r\n--{b}--\r\n".encode())
    return out.getvalue(), f"multipart/form-data; boundary={b}"


def worker(stored: str, base_img: np.ndarray, deadline: float, wid: int) -> None:
    global _done
    i = 0
    while time.monotonic() < deadline:
        i += 1
        zoom = 1.0 + 0.02 * ((wid * 7 + i) % 6)
        img = cv2.resize(cv2.flip(base_img, 1) if (i + wid) % 2 else base_img,
                         None, fx=zoom, fy=zoom)
        d, ct = multipart_image("image", "s.jpg", jpg(img, 88 + (i % 10)),
                                {"known_embedding": stored})
        t = time.monotonic()
        try:
            r = post("/verify", d, ct)
            ms = (time.monotonic() - t) * 1000
            with _lock:
                _done += 1
                print(f"[w{wid}#{i:03d}] sim={r.get('similarity')} "
                      f"match={r.get('face_match')} wall={ms:.0f}ms", flush=True)
        except Exception as e:
            with _lock:
                print(f"[w{wid}#{i:03d}] ERROR {e}", flush=True)


def main() -> int:
    z = zipfile.ZipFile(ZIP_PATH)
    names = [n for n in z.namelist() if n.startswith("img_align_celeba/") and n.endswith(".jpg")]
    raw = z.read(names[11])
    print(f"enrolling {names[11].split('/')[-1]} against {BASE} ...", flush=True)
    d, ct = multipart_image("image", "enroll.jpg", raw, {})
    ext = post("/extract", d, ct)
    emb = ext.get("embedding_512d")
    if not emb:
        print(f"FAIL enroll: {ext}", flush=True)
        return 1
    stored = json.dumps(emb)
    print("READY - starting verify loop", flush=True)

    base_img = cv2.imdecode(np.frombuffer(raw, np.uint8), cv2.IMREAD_COLOR)
    deadline = time.monotonic() + BUDGET
    threads = [threading.Thread(target=worker, args=(stored, base_img, deadline, w), daemon=True)
               for w in range(WORKERS)]
    t0 = time.monotonic()
    for t in threads:
        t.start()
    for t in threads:
        t.join()
    print(f"DONE requests={_done} in {time.monotonic() - t0:.0f}s", flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
