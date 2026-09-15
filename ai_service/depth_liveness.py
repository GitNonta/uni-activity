#!/usr/bin/env python3
"""depth_liveness.py — liveness signal from the facial depth stream.

Honest signal design (what monocular depth CAN and CANNOT do):

  CANNOT: distinguish a *photo of a face* from a face by shape alone —
  MiDaS predicts the same relief for both. Any claim of "3D anti-spoofing"
  from a single monocular depth frame would be false.

  CAN: use the stream's *temporal* structure:
    1. flux     — live faces micro-move constantly (breathing, posture,
                  micro-expressions); a held-still photo has near-frozen
                  depth. Primary signal.
    2. span     — the near-far range inside the crop; a real face at kiosk
                  distance has a large relief, a flat print much less.
                  Secondary signal.

  This makes depth a WEAK ADDITIONAL signal layered on top of the existing
  texture/FFT/EAR liveness — it also does not stop video replay (a video of
  a live face has flux too). It fails OPEN: if the depth server is
  unreachable or malformed, the result is neutral/pass so verification UX
  never breaks (consistent with server.py's liveness philosophy).

Thresholds are env-tunable and marked UNCALIBRATED: they must be tuned with
real capture/attack data from the deployment camera (see test_depth_liveness
for the mechanics checks; calibration is a data-collection follow-up).
"""

from __future__ import annotations

import base64
import json
import logging
import os
import time
import urllib.request
from dataclasses import dataclass, field

import numpy as np

logger = logging.getLogger("AIServer.DepthLiveness")

GRID = 64  # depth grid side served by depth_server /depth.json


@dataclass
class DepthLivenessResult:
    is_live: bool
    liveness_score: float          # 0..1 (0.5 = neutral / unavailable)
    available: bool                # depth server reachable + data valid
    checks: dict = field(default_factory=dict)
    message: str = ""


class DepthLivenessAnalyzer:
    """Samples the depth server's /depth.json and scores liveness."""

    def __init__(self,
                 server_url: str = "http://127.0.0.1:8086",
                 sample_frames: int = 8,
                 poll_interval: float = 0.12,
                 flux_min: float | None = None,
                 span_min: float | None = None,
                 timeout: float = 1.5) -> None:
        self.server_url = server_url.rstrip("/")
        self.sample_frames = max(3, int(sample_frames))
        self.poll_interval = float(poll_interval)
        # UNCALIBRATED defaults; override via env in server.py wiring
        self.flux_min = float(os.environ.get("DEPTH_FLUX_MIN", flux_min or 0.35))
        self.span_min = float(os.environ.get("DEPTH_SPAN_MIN", span_min or 30.0))
        self.timeout = float(timeout)

    # -- fetching ------------------------------------------------------------
    def _fetch_one(self) -> np.ndarray | None:
        try:
            req = urllib.request.Request(
                f"{self.server_url}/depth.json",
                headers={"Cache-Control": "no-store"})
            with urllib.request.urlopen(req, timeout=self.timeout) as r:
                j = json.loads(r.read())
            b64 = j.get("depth_b64")
            if not b64:
                return None
            raw = base64.b64decode(b64)
            g = np.frombuffer(raw, "<f2").astype(np.float32)
            if g.size != GRID * GRID:
                return None
            return g.reshape(GRID, GRID)
        except Exception as e:  # network, JSON, decode — all fail-open
            logger.debug(f"depth fetch failed: {e}")
            return None

    # -- scoring -------------------------------------------------------------
    def analyze(self) -> DepthLivenessResult:
        grids: list[np.ndarray] = []
        for i in range(self.sample_frames):
            g = self._fetch_one()
            if g is not None:
                grids.append(g)
            if i < self.sample_frames - 1:
                time.sleep(self.poll_interval)

        if len(grids) < 3:
            logger.info("[depth-liveness] depth server unavailable — fail-open")
            return DepthLivenessResult(
                is_live=True, liveness_score=0.5, available=False,
                checks={"available": False, "frames": len(grids)},
                message="depth stream unavailable (fail-open)")

        spans = [float(g.max() - g.min()) for g in grids]
        flux = [float(np.abs(grids[i] - grids[i - 1]).mean())
                for i in range(1, len(grids))]
        mean_span = float(np.mean(spans))
        mean_flux = float(np.mean(flux))

        motion_ok = mean_flux >= self.flux_min
        span_ok = mean_span >= self.span_min
        score = 0.5 + (0.3 if motion_ok else 0.0) + (0.2 if span_ok else 0.0)
        is_live = motion_ok and span_ok

        return DepthLivenessResult(
            is_live=is_live,
            liveness_score=round(score, 4),
            available=True,
            checks={
                "available": True,
                "frames": len(grids),
                "mean_flux": round(mean_flux, 4),
                "flux_min": self.flux_min,
                "motion_ok": bool(motion_ok),
                "mean_span": round(mean_span, 2),
                "span_min": self.span_min,
                "span_ok": bool(span_ok),
                "note": "uncalibrated thresholds (env: DEPTH_FLUX_MIN/DEPTH_SPAN_MIN)",
            },
            message=("depth stream shows live motion" if is_live
                     else "depth stream too static for a live face "
                          "(possible photo attack)"),
        )
