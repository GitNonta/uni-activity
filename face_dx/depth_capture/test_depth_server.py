#!/usr/bin/env python3
"""test_depth_server.py — verification for the monocular facial-depth capture.

1. model sanity   : MiDaS-Small loads; output is finite 256x256 with a span
2. face geometry  : on aligned face crops, the center (nose) is predicted
                    nearer than the border (inverse depth) — the expected
                    structure if depth is meaningful
3. server smoke   : spawn depth_server.py, then /healthz, /depth.json
                    (64x64 grid decodes, box valid, ms sane), /video.mjpg and
                    /depth.mjpg deliver multipart JPEG frames
4. writes reports/depth_capture.json; cleans up the spawned server

  python test_depth_server.py [--no-camera]   (skip 3 if no webcam present)
"""
from __future__ import annotations

import argparse
import base64
import json
import os
import subprocess
import sys
import time
import urllib.request

import cv2
import numpy as np
import onnxruntime as ort

HERE = os.path.dirname(os.path.abspath(__file__))
MODEL = os.path.join(HERE, "model-small.onnx")
CROPS = os.path.abspath(os.path.join(HERE, "..", "..", "face_cpp", "testdata", "crops"))
REPORT = os.path.abspath(os.path.join(HERE, "..", "reports", "depth_capture.json"))
PORT = 8087  # avoid clashing with a real server on 8086


def fail(msg: str) -> None:
    print(f"[FAIL] {msg}")
    sys.exit(1)


def get(path: str, timeout: float = 10.0) -> tuple[int, bytes]:
    try:
        with urllib.request.urlopen(f"http://127.0.0.1:{PORT}{path}", timeout=timeout) as r:
            return r.status, r.read()
    except Exception as e:
        return 0, str(e).encode()


def load_test_crops(n: int) -> list[np.ndarray]:
    files = sorted(f for f in os.listdir(CROPS) if f.lower().endswith(".png"))[:n]
    if len(files) < n:
        fail(f"need {n} crops in {CROPS}")
    outs = []
    for f in files:
        img = cv2.imread(os.path.join(CROPS, f), cv2.IMREAD_COLOR)
        if img is None:
            fail(f"cannot decode {f}")
        outs.append(cv2.resize(img, (256, 256), interpolation=cv2.INTER_LINEAR))
    return outs


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--no-camera", action="store_true",
                    help="skip the live-server smoke test")
    a = ap.parse_args()
    results: dict = {"checks": {}}

    # ---- 1. model sanity ----
    so = ort.SessionOptions()
    so.intra_op_num_threads = 2
    sess = ort.InferenceSession(MODEL, so, providers=["CPUExecutionProvider"])
    in_name = sess.get_inputs()[0].name

    def infer(bgr: np.ndarray) -> np.ndarray:
        rgb = cv2.cvtColor(bgr, cv2.COLOR_BGR2RGB).astype(np.float32) / 255.0
        rgb = (rgb - 0.485) / 0.229
        x = np.transpose(rgb, (2, 0, 1))[None]
        out, = sess.run(None, {in_name: x})
        return out[0]

    crops = load_test_crops(4)
    d0 = infer(crops[0])
    span = float(d0.max() - d0.min())
    finite = bool(np.isfinite(d0).all())
    results["checks"]["model_sanity"] = {
        "shape": list(d0.shape), "finite": finite, "span": round(span, 1),
        "ok": finite and d0.shape == (256, 256) and span > 1.0,
    }
    print(f"[depth] model: shape={d0.shape} finite={finite} span={span:.1f}")

    # ---- 2. face geometry: center nearer than border ----
    centers, borders = [], []
    for c in crops:
        d = infer(c)
        m = 40  # 40 px border band at 256
        centers.append(float(d[m:-m, m:-m].mean()))
        border = np.concatenate([
            d[:m].ravel(), d[-m:].ravel(), d[m:-m, :m].ravel(), d[m:-m, -m:].ravel()])
        borders.append(float(border.mean()))
    center_nearer = sum(c > b for c, b in zip(centers, borders))
    results["checks"]["face_geometry"] = {
        "center_nearer_than_border": f"{center_nearer}/{len(crops)}",
        "center_means": [round(v, 1) for v in centers],
        "border_means": [round(v, 1) for v in borders],
        # faces protrude; require the majority (not all — hair/background vary)
        "ok": center_nearer >= 3,
    }
    print(f"[depth] geometry: center nearer in {center_nearer}/{len(crops)} crops")

    # ---- 3. live server smoke ----
    if not a.no_camera:
        proc = subprocess.Popen(
            [sys.executable, os.path.join(HERE, "depth_server.py"),
             "--port", str(PORT)],
            cwd=HERE, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        try:
            ok_health = ok_json = ok_grid = ok_rgb = ok_depth = False
            meta = {}
            for _ in range(40):  # up to ~20 s for camera + model load
                time.sleep(0.5)
                code, body = get("/healthz", timeout=2)
                ok_health = code == 200 and body == b"ok"
                if not ok_health:
                    continue
                code, body = get("/depth.json", timeout=3)
                if code != 200:
                    continue
                j = json.loads(body)
                if j.get("depth_b64"):
                    raw = base64.b64decode(j["depth_b64"])
                    grid = np.frombuffer(raw, "<f2").astype(np.float32)
                    ok_grid = bool(grid.size == 64 * 64
                                   and np.isfinite(grid).all())
                    ok_json = j["grid"] == 64 and j["depth_ms"] > 0
                    meta = {"seq": j["seq"], "ms": j["depth_ms"],
                            "box": j["box"], "frame": j["frame"]}
                    break
            # streams: read the multipart head + first frame bytes
            if ok_health:
                try:
                    with urllib.request.urlopen(
                            f"http://127.0.0.1:{PORT}/video.mjpg", timeout=5) as r:
                        head = r.read(4096)
                        ok_rgb = b"--frame" in head and b"image/jpeg" in head
                except Exception:
                    pass
                try:
                    with urllib.request.urlopen(
                            f"http://127.0.0.1:{PORT}/depth.mjpg", timeout=5) as r:
                        head = r.read(4096)
                        ok_depth = b"--frame" in head and b"image/jpeg" in head
                except Exception:
                    pass
            results["checks"]["server_smoke"] = {
                "healthz": ok_health, "depth_json": ok_json,
                "grid_valid": ok_grid, "video_stream": ok_rgb,
                "depth_stream": ok_depth, "meta": meta,
                "ok": ok_health and ok_json and ok_grid and ok_rgb and ok_depth,
            }
            print(f"[depth] server: health={ok_health} json={ok_json} grid={ok_grid} "
                  f"rgb_stream={ok_rgb} depth_stream={ok_depth} meta={meta}")

            # ---- 3b. recorder + replay round trip ----
            rec = {"started": False, "stopped": False, "listed": False,
                   "replayed": False, "frames": 0}
            if ok_health:
                def post(path: str, obj: dict) -> dict:
                    req = urllib.request.Request(
                        f"http://127.0.0.1:{PORT}{path}", method="POST",
                        data=json.dumps(obj).encode(),
                        headers={"Content-Type": "application/json"})
                    with urllib.request.urlopen(req, timeout=5) as r:
                        return json.loads(r.read())

                j = post("/record", {"action": "start"})
                rec["started"] = bool(j.get("ok"))
                time.sleep(3.0)  # accumulate ~30-45 frames
                j = post("/record", {"action": "stop"})
                rec["stopped"] = bool(j.get("ok"))
                rec["frames"] = int(j.get("frames", 0))
                with urllib.request.urlopen(
                        f"http://127.0.0.1:{PORT}/recordings", timeout=5) as r:
                    lst = json.loads(r.read())["recordings"]
                rec["listed"] = any(x["name"].endswith(".fdz") for x in lst)
                if rec["started"] and rec["frames"] >= 10:
                    name = lst[-1]["name"]
                    j = post("/replay", {"action": "play", "name": name})
                    rec["replayed"] = bool(j.get("ok"))
                    if rec["replayed"]:
                        time.sleep(1.0)
                        _, body = get("/depth.json", timeout=3)
                        j2 = json.loads(body)
                        rec["replay_flag"] = bool(j2.get("replay"))
                        post("/replay", {"action": "stop"})
                        time.sleep(0.5)
                        _, body = get("/depth.json", timeout=3)
                        rec["replay_stops"] = not json.loads(body).get("replay")
            results["checks"]["recorder_replay"] = {
                **rec,
                "ok": rec["started"] and rec["stopped"] and rec["listed"]
                      and rec["replayed"] and rec.get("replay_flag", False)
                      and rec.get("replay_stops", False),
            }
            print(f"[depth] recorder: {rec}")
        finally:
            proc.terminate()
            try:
                proc.wait(timeout=8)
            except subprocess.TimeoutExpired:
                proc.kill()
    else:
        results["checks"]["server_smoke"] = {"ok": None, "note": "skipped (--no-camera)"}
        print("[depth] server smoke skipped (--no-camera)")

    # ---- verdict ----
    hard = [results["checks"][k]["ok"] for k in ("model_sanity", "face_geometry")]
    extra = [] if a.no_camera else [results["checks"]["server_smoke"]["ok"],
                                    results["checks"]["recorder_replay"]["ok"]]
    ok = all(hard) and all(extra)
    results["pass"] = bool(ok)
    results["meta"] = {
        "generated_utc": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "model": os.path.basename(MODEL),
    }
    os.makedirs(os.path.dirname(REPORT), exist_ok=True)
    with open(REPORT, "w", encoding="utf-8") as fh:
        json.dump(results, fh, indent=2)
    print(f"\n[depth] {'PASS' if ok else 'FAIL'} — report -> {os.path.relpath(REPORT, HERE)}")
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
