#!/usr/bin/env python3
"""_calib_ensemble_check.py — live check of the 5-image centroid ensemble /extract.

  1. single-image /extract still works (backward compat, enrollment=single)
  2. 5-image /extract (image + repeated `images`) -> enrollment=centroid, n=5
  3. /verify same-person against the centroid -> match at FDX_MATCH_THRESHOLD=0.30
  4. /verify different-person against the centroid -> no match
"""
from __future__ import annotations

import io
import json
import os
import subprocess
import sys
import time
import urllib.request

import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
KEY = "uni-activity-ai-secret-key-2026"
HDR = {"X-API-Key": KEY}
PORT = 8031
FIXDIR = os.path.join(HERE, "_calib_fixtures")


def http(port, method, path, data=None, headers=None, timeout=120):
    merged = {**HDR, **(headers or {})}
    req = urllib.request.Request(f"http://127.0.0.1:{port}{path}", method=method,
                                 headers=merged, data=data)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return r.status, json.loads(r.read().decode())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read().decode())


def multipart(extra: dict, fields: list) -> tuple:
    boundary = "----calib" + str(int(time.time() * 1000))
    body = io.BytesIO()
    for k, v in (extra or {}).items():
        body.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"{k}\"\r\n\r\n{v}\r\n".encode())
    for name, fn, data in fields:
        body.write(f"--{boundary}\r\nContent-Disposition: form-data; name=\"{name}\"; "
                   f"filename=\"{fn}\"\r\nContent-Type: image/jpeg\r\n\r\n".encode())
        body.write(data + b"\r\n")
    body.write(f"--{boundary}--\r\n".encode())
    return body.getvalue(), f"multipart/form-data; boundary={boundary}"


def wait_up(port, proc, tries=60):
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


def main() -> int:
    files = sorted(os.listdir(FIXDIR))
    blobs = {n: open(os.path.join(FIXDIR, n), "rb").read() for n in files}
    # NOTE: do not rely on sorted order — pick explicit identity groups.
    # 003029/003206/008838/016811/016933/021233 = identity 3, 008268 = identity 4
    enroll = ["003029.jpg", "003206.jpg", "008838.jpg", "016811.jpg", "016933.jpg"]
    probe_same, probe_other = "021233.jpg", "008268.jpg"

    env = {**os.environ, "AI_SERVER_KEY": KEY, "LIVENESS_THRESHOLD": "0.1",
           "USE_DEPTH_LIVENESS": "0", "FDX_MATCH_THRESHOLD": "0.30"}
    py = sys.executable
    proc = subprocess.Popen([py, "run_server_cpu.py", "--port", str(PORT)],
                            cwd=HERE, env=env,
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    ok = True
    try:
        wait_up(PORT, proc)

        # 1. backward-compat single-image /extract
        data, ct = multipart({}, [("image", "e.jpg", blobs[enroll[0]])])
        code, d1 = http(PORT, "POST", "/extract", data, {"Content-Type": ct})
        ok &= code == 200 and d1.get("enrollment") == "single" and d1.get("enrolled_images") == 1
        print(f"[{'PASS' if ok else 'FAIL'}] single /extract: enrollment={d1.get('enrollment')} "
              f"n={d1.get('enrolled_images')} space={d1.get('embedding_space')}")
        v_single = np.asarray(d1["embedding_512d"], dtype=np.float64)

        # 2. 5-image ensemble /extract (image + repeated images field)
        fields = ([("image", "e.jpg", blobs[enroll[0]])]
                  + [("images", n, blobs[n]) for n in enroll[1:]])
        data, ct = multipart({}, fields)
        code, d5 = http(PORT, "POST", "/extract", data, {"Content-Type": ct})
        ens_ok = code == 200 and d5.get("enrollment") == "centroid" and d5.get("enrolled_images") == 5
        ok &= ens_ok
        print(f"[{'PASS' if ens_ok else 'FAIL'}] ensemble /extract: enrollment={d5.get('enrollment')} "
              f"n={d5.get('enrolled_images')} (code={code})")
        v_cent = np.asarray(d5["embedding_512d"], dtype=np.float64)

        cos_sc = float(v_single @ v_cent)
        keep = 0.7 < cos_sc < 0.99999
        ok &= keep
        print(f"[{'PASS' if keep else 'FAIL'}] cos(single, centroid)={cos_sc:.4f} (0.7..1.0)")

        # 3. /verify same-person with centroid enrollment (raw JSON list)
        stored_json = json.dumps(d5["embedding_512d"])
        for label, fname, expect in (("same", probe_same, True), ("other", probe_other, False)):
            data, ct = multipart({"known_embedding": stored_json, "check_liveness": "false"},
                                 [("image", "s.jpg", blobs[fname])])
            code, dv = http(PORT, "POST", "/verify", data, {"Content-Type": ct})
            got = dv.get("face_match")
            good = code == 200 and got is expect
            ok &= good
            print(f"[{'PASS' if good else 'FAIL'}] /verify {label}: match={got} "
                  f"similarity={dv.get('similarity')} (expect match={expect})")

        print("OVERALL:", "PASS" if ok else "FAIL")
        return 0 if ok else 1
    finally:
        proc.kill()


if __name__ == "__main__":
    sys.exit(main())
