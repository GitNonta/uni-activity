# depth_capture — live facial depth + 3D display

Captures depth of the face in real time from the ordinary RGB webcam and
displays it as an interactive 3D relief. **No depth sensor is required** (and
none exists on this machine): depth comes from *monocular depth estimation* —
MiDaS-Small (ONNX) runs on the face crop of each webcam frame via
onnxruntime.

## Run

```
:: model: one-time download (66.8 MB, gitignored)
curl -L -o depth_capture\model-small.onnx ^
    https://github.com/isl-org/MiDaS/releases/download/v2_1/model-small.onnx

depth_capture\start_depth.bat        :: starts server if not running, opens viewer
:: or directly:
python depth_capture\depth_server.py --port 8086
```

Viewer: **http://127.0.0.1:8086/** — drag to orbit, scroll to zoom.

## What the viewer shows

- **3D depth relief** (center): a 64×64 grid displaced by inverse depth —
  nose/cheeks rise toward the camera — colored far-blue → near-accent, with a
  matching wireframe shell. Orbit/zoom freely; relief strength and smoothing
  are adjustable.
- **Camera + depth panels** (top-left): live RGB and the turbo-colormap depth
  map of the face crop.
- **Live stats**: depth latency (ms) and update rate (fps), connection dot.

## Endpoints (depth_server.py, stdlib HTTP, zero new deps)

| path | what |
|---|---|
| `/` | this 3D viewer |
| `/video.mjpg` | webcam MJPEG stream |
| `/depth.mjpg` | depth colormap MJPEG stream (face crop region) |
| `/depth.json` | `{seq, ts, box, depth_ms, depth_min/max, depth_b64, replay, recording, rec_*}` — 64×64 float16 inverse-depth grid |
| `/recordings` | list of `.fdz` recordings |
| `POST /record` | `{action: start\|stop}` — write a `.fdz` (one JSON line per depth frame) |
| `POST /replay` | `{action: play, name}` / `{action: stop}` — feed a recording back through the live state |
| `/healthz` | liveness (used by start_depth.bat) |

## Liveness integration (ai_service)

`ai_service/depth_liveness.py` polls `/depth.json` during `/verify` and adds a
**weak additional liveness signal** based on the stream's *temporal* structure:

- **flux** — live faces constantly micro-move (breathing, posture); a
  held-still photo's depth freezes (measured separation: ~250×)
- **span** — near–far relief range; a flat print has almost none

Monocular depth **cannot** distinguish a photo from a face by shape alone
(MiDaS predicts the same relief for both) — hence temporal-only, and it does
not stop video replay. It **fails open** when the depth server is off
(`available:false`, neutral 0.5 score), so verification UX never breaks.
Thresholds (`DEPTH_FLUX_MIN`, `DEPTH_SPAN_MIN`) are UNCALIBRATED — tune them
with real capture/attack data from the deployment camera. Disable entirely
with `USE_DEPTH_LIVENESS=0`.

## How it works

```
webcam ──► capture thread ──► latest frame + Haar face box (refresh ~2 Hz, 15% margin)
                │
                └► depth thread: crop ──► resize 256² ──► MiDaS-Small ──► inverse depth
                                            (~63-81 ms ≈ 13-15 fps, 2 CPU threads)
```

Larger depth value = **closer** (MiDaS v2 outputs inverse depth). If no face
is detected the full frame is used, so the viewer never goes blank.

## Verified (test_depth_server.py → reports/depth_capture.json)

- model: finite 256×256 output, span ≈ 950
- structure: nose-region nearer than border in **4/4** aligned face crops
- live server: health, JSON grid decode, both MJPEG streams — PASS

## Honest limitations

- **Relative, not metric**: MiDaS gives inverse depth up to scale/shift —
  correct shape and ordering, not millimeters. For metric depth you need a
  stereo/ToF/LiDAR sensor (e.g. RealSense) — the server architecture (capture
  + depth threads, same endpoints) is designed so a sensor backend could
  replace the MiDaS stage without touching the viewer.
- Refresh is ~13-15 fps with ~70 ms latency; fine for display and
  liveness-style observation, not for high-rate measurement.
- Hair/glasses/background can confound monocular depth at the frame borders;
  the smoothing toggle mitigates shimmer.
