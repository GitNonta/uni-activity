#!/usr/bin/env python3
"""test_depth_liveness.py — verification for the depth-stream liveness signal.

1. scoring mechanics (synthetic grids injected past the network layer):
   - frozen sequence (held-still photo signature) -> NOT live (motion fails)
   - micro-moving sequence (live-face signature)  -> live (flux + span pass)
   - flat relief                                  -> NOT live (span fails)
2. fail-open: unreachable depth server -> available=False, is_live=True,
   score 0.5 (neutral) — verification UX never breaks
3. /verify wiring: spawn ai_service (CPU) with depth liveness enabled but no
   depth server running; POST /verify must succeed and report the fail-open
   depth checks instead of erroring

  python test_depth_liveness.py          (spawns the AI server on :8019)
"""
from __future__ import annotations

import json
import os
import subprocess
import sys
import time
import urllib.request

import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
from depth_liveness import DepthLivenessAnalyzer  # noqa: E402

CROPS = os.path.abspath(os.path.join(HERE, "..", "face_cpp", "testdata", "crops"))
REPORT = os.path.abspath(os.path.join(HERE, "reports", "depth_liveness_test.json"))
PORT = 8019


def fail(msg: str) -> None:
    print(f"[FAIL] {msg}")
    sys.exit(1)


def analyzer_with(grids: list[np.ndarray]) -> DepthLivenessAnalyzer:
    a = DepthLivenessAnalyzer(sample_frames=len(grids), poll_interval=0.0)
    it = iter(grids)
    a._fetch_one = lambda timeout=None: next(it, None)  # inject past the network layer
    return a


def main() -> int:
    rng = np.random.default_rng(42)
    results: dict = {"checks": {}}

    base = rng.uniform(300, 900, (64, 64)).astype(np.float32)  # face-like relief

    # ---- 1. frozen sequence (photo held still) ----
    frozen = [base + rng.normal(0, 0.2, (64, 64)).astype(np.float32) for _ in range(8)]
    r = analyzer_with(frozen).analyze()
    results["checks"]["frozen_not_live"] = {
        "is_live": r.is_live, "score": r.liveness_score,
        "flux": r.checks.get("mean_flux"), "span": r.checks.get("mean_span"),
        "ok": (not r.is_live) and r.available,
    }
    print(f"[dl] frozen: live={r.is_live} flux={r.checks['mean_flux']} "
          f"span={r.checks['mean_span']}")

    # ---- 2. micro-moving sequence (live face) ----
    moving = []
    t = base.copy()
    for i in range(8):
        shift = np.roll(base, int(np.sin(i / 2.0) * 2.0), axis=1)
        moving.append(shift + rng.normal(0, 1.5, (64, 64)).astype(np.float32))
    r = analyzer_with(moving).analyze()
    results["checks"]["moving_live"] = {
        "is_live": r.is_live, "score": r.liveness_score,
        "flux": r.checks.get("mean_flux"),
        "ok": r.is_live and r.available,
    }
    print(f"[dl] moving: live={r.is_live} flux={r.checks['mean_flux']} "
          f"span={r.checks['mean_span']}")

    # ---- 3. flat relief (flat print at fixed distance) ----
    flat = [np.full((64, 64), 500.0, np.float32)
            + rng.normal(0, 0.1, (64, 64)).astype(np.float32) for _ in range(8)]
    r = analyzer_with(flat).analyze()
    results["checks"]["flat_not_live"] = {
        "is_live": r.is_live, "span": r.checks.get("mean_span"),
        "ok": (not r.is_live) and r.available,
    }
    print(f"[dl] flat: live={r.is_live} span={r.checks['mean_span']}")

    # ---- 4. fail-open: no depth server ----
    a = DepthLivenessAnalyzer(server_url="http://127.0.0.1:59999",
                              sample_frames=4, poll_interval=0.0, timeout=0.5)
    r = a.analyze()
    results["checks"]["fail_open"] = {
        "is_live": r.is_live, "available": r.available, "score": r.liveness_score,
        "ok": r.is_live and (not r.available) and r.liveness_score == 0.5,
    }
    print(f"[dl] unavailable: live={r.is_live} available={r.available} "
          f"score={r.liveness_score}")

    # ---- 5. /verify wiring with depth server absent ----
    env = dict(os.environ)
    env.update({"USE_DEPTH_LIVENESS": "1", "DEPTH_LIVENESS_URL": "http://127.0.0.1:59999",
                # low texture-liveness threshold so the depth stage is reached
                # (it is deliberately skipped when texture liveness already failed)
                "LIVENESS_THRESHOLD": "0.1"})
    log_path = os.path.join(HERE, "_depth_test_server.log")
    log_fh = open(log_path, "w", encoding="utf-8")
    proc = subprocess.Popen(
        [sys.executable, os.path.join(HERE, "run_server_cpu.py"), "--port", str(PORT)],
        cwd=HERE, env=env, stdout=log_fh, stderr=log_fh)
    wired = False
    try:
        # wait for startup (models load ~10-20 s)
        for _ in range(60):
            time.sleep(1.0)
            try:
                with urllib.request.urlopen(
                        f"http://127.0.0.1:{PORT}/health", timeout=2) as r:
                    h = json.loads(r.read())
                if h.get("models", {}).get("insightface"):
                    break
            except Exception:
                continue
        # verify with a real photo: pick the LARGEST png (SCRFD at 640^2
        # detection is unreliable on tiny 112px crops — it correctly returns
        # no_face for them, which would end the test at the early return)
        import cv2
        best, best_area = None, 0
        for nm in sorted(os.listdir(CROPS)):
            if not nm.lower().endswith(".png"):
                continue
            im = cv2.imread(os.path.join(CROPS, nm))
            if im is not None and im.shape[0] * im.shape[1] > best_area:
                best, best_area = nm, im.shape[0] * im.shape[1]
        if best is None:
            fail("no test images found")
        print(f"[dl] using {best} ({best_area} px) for /verify")
        with open(os.path.join(CROPS, best), "rb") as f:
            img_bytes = f.read()
        emb = rng.normal(size=512)
        emb /= np.linalg.norm(emb)
        boundary = f"--b{int(time.time())}"
        payload = (
            f"--{boundary}\r\nContent-Disposition: form-data; name=\"known_embedding\"\r\n\r\n"
            f"{json.dumps(emb.tolist())}\r\n"
            f"--{boundary}\r\nContent-Disposition: form-data; name=\"check_liveness\"\r\n\r\n"
            f"true\r\n"
            f"--{boundary}\r\nContent-Disposition: form-data; name=\"image\"; "
            f"filename=\"selfie.png\"\r\nContent-Type: image/png\r\n\r\n").encode() \
            + img_bytes + f"\r\n--{boundary}--\r\n".encode()
        req = urllib.request.Request(
            f"http://127.0.0.1:{PORT}/verify", data=payload, method="POST",
            headers={"Content-Type": f"multipart/form-data; boundary={boundary}",
                     "X-API-Key": "uni-activity-ai-secret-key-2026"})
        with urllib.request.urlopen(req, timeout=60) as r:
            v = json.loads(r.read())
        dc = v.get("depth_liveness_checks", {})
        wired = ("depth_liveness_checks" in v and dc.get("available") is False
                 and v.get("liveness_passed") is not None)
        results["checks"]["verify_wiring"] = {
            "depth_checks_present": "depth_liveness_checks" in v,
            "available": dc.get("available"),
            "liveness_passed": v.get("liveness_passed"),
            "is_match": v.get("is_match"),
            "ok": wired,
        }
        print(f"[dl] /verify: depth_checks={dc} live={v.get('liveness_passed')}")
    except Exception as e:
        results["checks"]["verify_wiring"] = {"ok": False, "error": str(e)}
        print(f"[dl] /verify wiring error: {e}")
    finally:
        proc.terminate()
        try:
            proc.wait(timeout=8)
        except subprocess.TimeoutExpired:
            proc.kill()
        log_fh.close()
    if not wired:
        try:
            with open(log_path, encoding="utf-8", errors="replace") as f:
                tail = f.read()[-1200:]
            print("[dl] server log tail:\n" + tail)
        except OSError:
            pass

    # ---- verdict ----
    ok = all(c.get("ok") for c in results["checks"].values())
    results["pass"] = bool(ok)
    results["meta"] = {
        "generated_utc": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "note": "thresholds UNCALIBRATED — calibrate with real capture/attack data",
    }
    os.makedirs(os.path.dirname(REPORT), exist_ok=True)
    with open(REPORT, "w", encoding="utf-8") as fh:
        json.dump(results, fh, indent=2)
    print(f"\n[dl] {'PASS' if ok else 'FAIL'} — report -> {os.path.relpath(REPORT, HERE)}")
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
