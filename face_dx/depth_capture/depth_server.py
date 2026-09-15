#!/usr/bin/env python3
"""depth_server.py — live facial depth capture for the 3D depth viewer.

No depth camera exists on this machine (RGB webcam only), so depth comes from
monocular depth estimation: MiDaS-Small (ONNX, onnxruntime) runs on the face
crop of each webcam frame and outputs relative inverse depth (larger =
closer). OpenCV's Haar cascade provides the face box (dependency-free; if no
cascade is present the full frame is used).

Endpoints (stdlib http.server, zero new dependencies):
  GET /            the 3D viewer (index.html)
  GET /video.mjpg  raw webcam MJPEG stream
  GET /depth.mjpg  depth map MJPEG stream (turbo colormap, face crop region)
  GET /depth.json  latest {"seq","ts","w","h","box","depth_b64"(64x64 f16 grid),
                    "depth_min","depth_max"}
  GET /healthz     liveness for start scripts

Design mirrors monitor_server.py: one capture thread (keeps only the latest
frame), one depth thread (~13 fps), HTTP handlers only read shared state —
no locks held across blocking work, no writes to shared files.

Usage:
    python depth_server.py [--port 8086] [--camera 0] [--cpu-threads 2]
"""

from __future__ import annotations

import argparse
import base64
import json
import os
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

import cv2
import numpy as np
import onnxruntime as ort

HERE = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.path.join(HERE, "model-small.onnx")
VIEWER_PATH = os.path.join(HERE, "index.html")

GRID = 64          # depth-grid resolution served over JSON
DEPTH_IN = 256     # MiDaS-Small input size

# shared state (latest-only; writers replace, readers copy references)
_lock = threading.Lock()
_state = {
    "jpeg_rgb": None,      # latest full-frame JPEG bytes
    "jpeg_depth": None,    # latest depth-colormap JPEG bytes (face region)
    "depth": None,         # latest inverse depth (float32, DEPTH_IN x DEPTH_IN)
    "box": None,           # face box in frame coords [x, y, w, h]
    "frame_shape": None,   # (h, w)
    "seq": 0,
    "ts": 0.0,
    "depth_ms": 0.0,
}


# ---------------------------------------------------------------------------
# capture + face box
# ---------------------------------------------------------------------------
def open_camera(index: int) -> cv2.VideoCapture:
    cap = cv2.VideoCapture(index, cv2.CAP_DSHOW)  # fast open on Windows
    if not cap.isOpened():
        cap = cv2.VideoCapture(index)
    if not cap.isOpened():
        raise RuntimeError(f"cannot open camera {index}")
    cap.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
    cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
    cap.set(cv2.CAP_PROP_FPS, 30)
    return cap


def load_face_cascade():
    p = os.path.join(cv2.data.haarcascades, "haarcascade_frontalface_default.xml")
    if os.path.isfile(p):
        return cv2.CascadeClassifier(p)
    print("[depth] no Haar cascade found — full-frame depth mode")
    return None


def capture_loop(cap, cascade) -> None:
    """Read frames as fast as the camera gives them; keep only the latest."""
    last_box_check = 0.0
    box = None
    frame = None

    def detect():
        nonlocal box
        if cascade is None or frame is None:
            return
        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        faces = cascade.detectMultiScale(gray, 1.15, 5, minSize=(120, 120))
        if len(faces):
            x, y, w, h = max(faces, key=lambda f: f[2] * f[3])
            # 15% margin so depth covers the full face, not just the detector box
            mx, my = int(w * 0.15), int(h * 0.15)
            box = [max(0, x - mx), max(0, y - my),
                   min(frame.shape[1], x + w + mx) - max(0, x - mx),
                   min(frame.shape[0], y + h + my) - max(0, y - my)]

    while True:
        ok, f = cap.read()
        if not ok:
            time.sleep(0.05)
            continue
        frame = f
        now = time.time()
        if now - last_box_check > 0.5:  # face refresh ~2 Hz (Haar is cheap but not free)
            detect()
            last_box_check = now
        ok2, jpg = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, 80])
        if ok2:
            with _lock:
                _state["jpeg_rgb"] = jpg.tobytes()
                _state["box"] = box
                _state["frame_shape"] = frame.shape[:2]


# ---------------------------------------------------------------------------
# depth inference
# ---------------------------------------------------------------------------
class DepthModel:
    def __init__(self, cpu_threads: int) -> None:
        so = ort.SessionOptions()
        so.intra_op_num_threads = cpu_threads
        self.sess = ort.InferenceSession(
            MODEL_PATH, so, providers=["CPUExecutionProvider"])
        self.in_name = self.sess.get_inputs()[0].name

    def infer(self, bgr: np.ndarray) -> np.ndarray:
        rgb = cv2.cvtColor(bgr, cv2.COLOR_BGR2RGB).astype(np.float32) / 255.0
        rgb = (rgb - 0.485) / 0.229  # standard MiDaS normalization
        x = np.transpose(rgb, (2, 0, 1))[None]
        out, = self.sess.run(None, {self.in_name: x})
        return out[0]  # 256x256 inverse depth


def depth_loop(cpu_threads: int) -> None:
    model = DepthModel(cpu_threads)
    while True:
        # the capture thread publishes JPEG frames; decode the latest
        # (~2-3 ms at 640x480) and run depth on the face crop
        with _lock:
            jpg = _state["jpeg_rgb"]
            box = _state["box"]
        if jpg is None:
            time.sleep(0.05)
            continue
        frame = cv2.imdecode(np.frombuffer(jpg, np.uint8), cv2.IMREAD_COLOR)
        if frame is None:
            time.sleep(0.02)
            continue
        h, w = frame.shape[:2]
        if box and box[2] > 16 and box[3] > 16:
            x, y, bw, bh = box
            crop = frame[y:y + bh, x:x + bw]
        else:
            x, y, bw, bh = 0, 0, w, h
            crop = frame
        t0 = time.perf_counter()
        depth = model.infer(cv2.resize(crop, (DEPTH_IN, DEPTH_IN),
                                       interpolation=cv2.INTER_LINEAR))
        ms = (time.perf_counter() - t0) * 1000.0

        # colormap for the stream (grayscale decode is fine here — this is a
        # derived visualization of the crop, not the raw camera image)
        dn = cv2.normalize(depth, None, 0, 255, cv2.NORM_MINMAX).astype(np.uint8)
        colored = cv2.applyColorMap(dn, cv2.COLORMAP_TURBO)
        ok2, djpg = cv2.imencode(".jpg", colored, [cv2.IMWRITE_JPEG_QUALITY, 82])

        grid = cv2.resize(depth, (GRID, GRID), interpolation=cv2.INTER_AREA)
        dmin, dmax = float(depth.min()), float(depth.max())
        with _lock:
            _state["jpeg_depth"] = djpg.tobytes() if ok2 else None
            _state["depth"] = grid  # the served 64x64 grid (viewers poll this)
            _state["box"] = box
            _state["frame_shape"] = (h, w)
            _state["seq"] += 1
            _state["ts"] = time.time()
            _state["depth_ms"] = ms
            _state["range"] = (dmin, dmax)


# ---------------------------------------------------------------------------
# HTTP
# ---------------------------------------------------------------------------
class Handler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def log_message(self, fmt, *args):  # quiet
        pass

    def _send(self, code: int, ctype: str, body: bytes, cache: str = "no-store") -> None:
        self.send_response(code)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", cache)
        self.end_headers()
        self.wfile.write(body)

    def _latest(self, key):
        with _lock:
            return _state.get(key)

    def do_GET(self):
        path = self.path.split("?")[0]
        if path == "/":
            try:
                with open(VIEWER_PATH, "rb") as f:
                    self._send(200, "text/html; charset=utf-8", f.read())
            except OSError:
                self._send(404, "text/plain", b"viewer missing")
        elif path == "/healthz":
            self._send(200, "text/plain", b"ok")
        elif path == "/video.mjpg":
            self._stream("jpeg_rgb")
        elif path == "/depth.mjpg":
            self._stream("jpeg_depth")
        elif path == "/depth.json":
            with _lock:
                depth = _state["depth"]
                rng = _state.get("range")
                payload = {
                    "seq": _state["seq"], "ts": _state["ts"],
                    "box": _state["box"], "frame": _state["frame_shape"],
                    "depth_ms": round(_state["depth_ms"], 2),
                    "depth_min": rng[0] if rng else None,
                    "depth_max": rng[1] if rng else None,
                    "grid": GRID,
                    "depth_b64": base64.b64encode(
                        depth.astype("<f2").tobytes()).decode()
                    if depth is not None else None,
                }
            body = json.dumps(payload).encode()
            self._send(200, "application/json", body)
        else:
            self._send(404, "text/plain", b"not found")

    def _stream(self, key: str) -> None:
        self.send_response(200)
        self.send_header("Content-Type", "multipart/x-mixed-replace; boundary=frame")
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        try:
            while True:
                jpg = self._latest(key)
                if jpg is None:
                    time.sleep(0.05)
                    continue
                self.wfile.write(b"--frame\r\nContent-Type: image/jpeg\r\n"
                                 b"Content-Length: " + str(len(jpg)).encode() +
                                 b"\r\n\r\n" + jpg + b"\r\n")
                time.sleep(1.0 / 30.0)
        except (ConnectionAbortedError, BrokenPipeError, OSError):
            return


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--port", type=int, default=8086)
    ap.add_argument("--camera", type=int, default=0)
    ap.add_argument("--cpu-threads", type=int, default=2)
    a = ap.parse_args()

    if not os.path.isfile(MODEL_PATH):
        print(f"[depth] missing {MODEL_PATH}\n"
              "        download: curl -L -o depth_capture/model-small.onnx "
              "https://github.com/isl-org/MiDaS/releases/download/v2_1/model-small.onnx")
        return 1

    cap = open_camera(a.camera)
    cascade = load_face_cascade()
    print(f"[depth] camera {a.camera} open ({cap.get(3):.0f}x{cap.get(1):.0f}), "
          f"face detection: {'Haar' if cascade is not None else 'full-frame'}")

    threading.Thread(target=capture_loop, args=(cap, cascade), daemon=True).start()
    threading.Thread(target=depth_loop, args=(a.cpu_threads,), daemon=True).start()

    srv = ThreadingHTTPServer(("127.0.0.1", a.port), Handler)
    print(f"[depth] viewer -> http://127.0.0.1:{a.port}/   (Ctrl+C to stop)")
    try:
        srv.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        cap.release()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
