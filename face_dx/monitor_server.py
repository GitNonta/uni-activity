#!/usr/bin/env python3
"""
monitor_server.py — real-time read-only status dashboard for the long-running
CelebA 512-d extraction pipeline (or any run that writes a JSONL state file
next to a --out report path).

Design goals (explicitly requested: zero impact on the running system):
  * READ-ONLY: opens files with O_BINARY reads only; never writes, locks,
    flushes, or touches anything the pipeline owns.
  * O(1) PER POLL: keeps a byte offset + partial-line buffer and reads only
    newly appended bytes (no re-reading, no re-parsing of old rows).
  * NEAR-ZERO CPU: state counters live in memory; HTTP handler just renders
    a tiny cached SVG. The refresh work (parse of new rows only) is bounded
    by the append rate (~7-25 rows/s here, trivially cheap).
  * NO DEPENDENCIES: stdlib http.server + hand-written SVG (project rule:
    SVG-only UI, no emoji, no raster icons).

Usage:
    python monitor_server.py [--port 8787] [--state FILE] [--out-report FILE]
                             [--zip-images N] [--total IMAGES]

Defaults auto-derive from the pipeline's layout: state file is
<out-report>.state.jsonl, total is read from the report meta when written,
otherwise --total (default: 202,599 = full CelebA aligned zip).

Open:  http://127.0.0.1:8787/
"""

from __future__ import annotations

import argparse
import json
import os
import threading
import time
from collections import deque
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

# ---------------------------------------------------------------------------

T_TOTAL_DEFAULT = 202_599
HISTORY = 180  # samples kept for the sparkline (~30 min at 10 s cadence)
POLL_SEC = 10.0


class RunStats:
    """Incrementally tracked counters for the JSONL state file."""

    def __init__(self, state_path: str) -> None:
        self.state_path = state_path
        self.rows = 0
        self.failures = 0
        self.gpu_ms_sum = 0.0
        self.gpu_n_cum = 0
        self.gpu_ms: list[float] = []       # reservoir for latency percentiles
        self.spot_checks = 0
        self.worst_spot_cos = 1.0
        self.last_image = ""
        self.last_gpu_ms = 0.0
        self._offset = 0                    # byte offset into the state file
        self._partial = b""                 # partial trailing line
        self._hist: deque[tuple[float, int]] = deque(maxlen=HISTORY)
        # run start ≈ state file creation time (the pipeline creates it at
        # launch; the monitor may attach hours later and still get it right)
        try:
            self._run_start = os.path.getctime(state_path)
        except OSError:
            self._run_start = time.time()
        self._stop = False
        self._lock = threading.Lock()
        self._thread = threading.Thread(target=self._loop, daemon=True)
        self._thread.start()

    # ---- background poller: reads ONLY new bytes -------------------------
    def _loop(self) -> None:
        while not self._stop:
            try:
                self._poll()
            except OSError:
                pass  # file being rotated/replaced — retry next tick
            self._hist.append((time.time(), self.rows))
            time.sleep(POLL_SEC)

    def _poll(self) -> None:
        size = os.path.getsize(self.state_path)
        if size == self._offset:
            return
        with open(self.state_path, "rb") as f:
            f.seek(self._offset)
            chunk = f.read(size - self._offset)
        self._offset = size
        data = self._partial + chunk
        lines = data.split(b"\n")
        self._partial = lines.pop()          # last piece may be incomplete
        rows, fails, spots = 0, 0, 0
        gpu_new: list[float] = []
        worst = self.worst_spot_cos
        for ln in lines:
            ln = ln.strip()
            if not ln:
                continue
            try:
                r = json.loads(ln)
            except json.JSONDecodeError:
                continue
            rows += 1
            if not r.get("pass", True):
                fails += 1
            ms = r.get("gpu_ms")
            if isinstance(ms, (int, float)):
                gpu_new.append(float(ms))
            if r.get("cos_gpu_onnx") is not None:
                spots += 1
                worst = min(worst, r.get("cos_gpu_numpy") or 1.0,
                            r.get("cos_gpu_onnx") or 1.0)
            self.last_image = r.get("image", self.last_image)
            if isinstance(ms, (int, float)):
                self.last_gpu_ms = float(ms)
        with self._lock:
            self.rows += rows
            self.failures += fails
            self.spot_checks += spots
            self.worst_spot_cos = worst
            self.gpu_ms_sum += sum(gpu_new)
            self.gpu_n_cum += len(gpu_new)
            self.gpu_ms.extend(gpu_new)
            # cap percentile memory: keep at most 50k samples (uniform stride)
            if len(self.gpu_ms) > 50_000:
                stride = len(self.gpu_ms) // 25_000
                self.gpu_ms = self.gpu_ms[::stride]

    # ---- snapshot for rendering ------------------------------------------
    def snapshot(self) -> dict:
        with self._lock:
            ms = sorted(self.gpu_ms)
            n = len(ms)

            def pct(p: float) -> float:
                return ms[min(n - 1, int(p * n))] if n else 0.0

            elapsed_run = time.time() - self._run_start
            rate_avg = self.rows / elapsed_run if elapsed_run > 5 else 0.0
            rate_window = 0.0
            if len(self._hist) >= 2:
                (t0, r0), (t1, r1) = self._hist[0], self._hist[-1]
                if t1 > t0:
                    rate_window = (r1 - r0) / (t1 - t0)
            return {
                "rows": self.rows,
                "failures": self.failures,
                "spot_checks": self.spot_checks,
                "worst_spot_cos": self.worst_spot_cos,
                "last_image": self.last_image,
                "last_gpu_ms": self.last_gpu_ms,
                "gpu_p50": pct(0.50), "gpu_p90": pct(0.90),
                "gpu_p99": pct(0.99), "gpu_max": pct(1.0),
                "gpu_mean": (self.gpu_ms_sum / self.gpu_n_cum) if self.gpu_n_cum else 0.0,
                "gpu_n": n,
                "elapsed_s": elapsed_run,
                "rate_window": rate_window,
                "rate_avg": rate_avg,
                "hist": list(self._hist),
            }


# ---------------------------------------------------------------------------
# SVG rendering (project rule: SVG-only UI, no emoji / raster / icon fonts)
# ---------------------------------------------------------------------------

def esc(s: str) -> str:
    return (s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;"))


def bar(pct: float, x: int, y: int, w: int, h: int, color: str) -> str:
    pct = max(0.0, min(1.0, pct))
    return (f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="#1e2430" rx="4"/>'
            f'<rect x="{x}" y="{y}" width="{w * pct:.1f}" height="{h}" fill="{color}" rx="4"/>')


def sparkline(hist: list[tuple[float, int]], total: int,
              x: int, y: int, w: int, h: int) -> str:
    if len(hist) < 2:
        return (f'<text x="{x}" y="{y + h // 2}" fill="#5b6a8f" '
                f'font-family="monospace" font-size="12">collecting samples…</text>')
    t0, t1 = hist[0][0], hist[-1][0]
    span = max(t1 - t0, 1e-9)
    r0 = hist[0][1]
    pts = []
    for t, v in hist:
        px = x + (t - t0) / span * w
        py = y + h - (v - r0) / max(total - r0, 1) * h
        pts.append(f"{px:.1f},{py:.1f}")
    poly = " ".join(pts)
    area = f"{x + w:.1f},{y + h} {poly} {x:.1f},{y + h}"
    return (f'<polygon points="{area}" fill="#1b4332" opacity="0.55"/>'
            f'<polyline points="{poly}" fill="none" stroke="#52b788" stroke-width="2"/>')


def render_svg(s: dict, total: int) -> str:
    done = s["rows"]
    frac = min(1.0, done / max(total, 1))
    rate_avg = s["rate_avg"]
    rate_win = s["rate_window"]
    remaining = max(0, total - done)
    eta_s = remaining / rate_win if rate_win > 0.01 else 0.0
    ok = s["failures"] == 0
    status_color = "#52b788" if ok else "#e63946"
    status_text = "RUNNING · ALL PASS" if ok else "FAILURES DETECTED"

    W, H = 760, 560
    p = []
    p.append(f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" '
             f'viewBox="0 0 {W} {H}" font-family="monospace">')
    p.append(f'<rect width="{W}" height="{H}" fill="#0d1117" rx="10"/>')
    p.append(f'<text x="24" y="40" fill="#e6edf3" font-size="19" font-weight="bold">'
             f'CelebA 512-d fp16 extraction</text>')
    p.append(f'<circle cx="{W - 130}" cy="34" r="6" fill="{status_color}">'
             f'<animate attributeName="opacity" values="1;0.25;1" dur="2s" '
             f'repeatCount="indefinite"/></circle>')
    p.append(f'<text x="{W - 116}" y="39" fill="{status_color}" font-size="13">'
             f'{status_text}</text>')

    p.append(bar(frac, 24, 60, W - 48, 22, "#3fb950"))
    p.append(f'<text x="24" y="104" fill="#e6edf3" font-size="24" font-weight="bold">'
             f'{done:,} / {total:,}</text>')
    p.append(f'<text x="{W - 24}" y="104" fill="#8b949e" font-size="15" '
             f'text-anchor="end">{frac * 100:.2f}%</text>')

    def kv(label: str, value: str, x: int, y: int, color: str = "#e6edf3") -> str:
        return (f'<text x="{x}" y="{y}" fill="#8b949e" font-size="12">{label}</text>'
                f'<text x="{x}" y="{y + 18}" fill="{color}" font-size="15">'
                f'{esc(value)}</text>')

    hrs = int(eta_s // 3600); mins = int(eta_s % 3600 // 60)
    eta = f"~{hrs}h {mins:02d}m" if eta_s > 0 else "— (waiting for rate samples)"
    p.append(kv("RATE (10-MIN WINDOW)", f"{rate_win:.1f} img/s", 24, 150, "#79c0ff"))
    p.append(kv("RATE (SINCE LAUNCH)", f"{rate_avg:.1f} img/s", 210, 150, "#79c0ff"))
    p.append(kv("ETA", eta, 400, 150, "#d2a8ff"))
    p.append(kv("REMAINING", f"{remaining:,}", 590, 150))

    p.append(kv("GPU P50 / P90 / P99", f"{s['gpu_p50']:.1f} / {s['gpu_p90']:.1f} / "
                f"{s['gpu_p99']:.1f} ms", 24, 205))
    p.append(kv("GPU MAX / LAST", f"{s['gpu_max']:.1f} / {s['last_gpu_ms']:.1f} ms",
                400, 205))
    p.append(kv("SPOT-CHECKS", f"{s['spot_checks']:,}  (worst cos "
                f"{s['worst_spot_cos']:.6f})", 24, 255, "#7ee787"))
    p.append(kv("FAILURES", f"{s['failures']:,}", 400, 255,
                "#7ee787" if ok else "#ff7b72"))

    p.append(f'<text x="24" y="312" fill="#8b949e" font-size="12">'
             f'PROGRESS OVER LAST {HISTORY * POLL_SEC / 60:.0f} MIN</text>')
    p.append(sparkline(s["hist"], total, 24, 322, W - 48, 110))
    p.append(f'<rect x="24" y="322" width="{W - 48}" height="110" '
             f'fill="none" stroke="#30363d" rx="6"/>')

    p.append(f'<text x="24" y="470" fill="#8b949e" font-size="12">LAST IMAGE</text>')
    p.append(f'<text x="24" y="490" fill="#e6edf3" font-size="13">'
             f'{esc(s["last_image"] or "—")}</text>')
    p.append(f'<text x="24" y="{H - 20}" fill="#484f58" font-size="11">'
             f'read-only monitor · polls {POLL_SEC:.0f}s · page auto-refreshes '
             f'every {POLL_SEC:.0f}s</text>')
    p.append("</svg>")
    return "".join(p)


# ---------------------------------------------------------------------------

class Handler(BaseHTTPRequestHandler):
    stats: RunStats = None          # injected in main()
    total: int = T_TOTAL_DEFAULT
    out_report: str = ""

    def log_message(self, fmt: str, *args) -> None:  # silence request spam
        pass

    def _send(self, code: int, body: bytes, ctype: str) -> None:
        self.send_response(code)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self) -> None:
        if self.path.startswith("/status.json"):
            s = self.stats.snapshot()
            s["file_total"] = self._report_total()
            body = json.dumps(s, default=str).encode()
            self._send(200, body, "application/json")
        elif self.path.startswith("/metrics"):
            s = self.stats.snapshot()
            total = self._report_total() or self.total
            lines = [
                "# TYPE fdx_progress_images gauge",
                f"fdx_progress_images {s['rows']}",
                f"fdx_progress_total {total}",
                f"fdx_gpu_ms_p50 {s['gpu_p50']:.3f}",
                f"fdx_gpu_ms_p90 {s['gpu_p90']:.3f}",
                f"fdx_gpu_ms_p99 {s['gpu_p99']:.3f}",
                f"fdx_rate_window {s['rate_window']:.3f}",
                f"fdx_failures {s['failures']}",
            ]
            self._send(200, "\n".join(lines).encode(), "text/plain")
        elif self.path.startswith("/healthz"):
            self._send(200, b"ok", "text/plain")
        else:
            s = self.stats.snapshot()
            total = self._report_total() or self.total
            body = render_svg(s, total).encode()
            self._send(200, body, "image/svg+xml")

    def _report_total(self) -> int:
        """Once the final report exists, read the authoritative total from it
        (cheap: one small file read per HTTP request)."""
        try:
            with open(self.out_report, "rb") as f:
                meta = json.load(f)
            return int(meta["meta"]["sample"]["size"])
        except (OSError, KeyError, ValueError):
            return 0


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--port", type=int, default=8787)
    ap.add_argument("--out-report",
                    default=os.path.join(os.path.dirname(os.path.abspath(__file__)),
                                         "reports", "celeba_512d_full_fp16.json"))
    ap.add_argument("--state", default=None,
                    help="state JSONL (default: <out-report>.state.jsonl)")
    ap.add_argument("--total", type=int, default=T_TOTAL_DEFAULT,
                    help="expected image count (default: full CelebA zip)")
    args = ap.parse_args()

    state = args.state or args.out_report + ".state.jsonl"
    Handler.stats = RunStats(state)
    Handler.total = args.total
    Handler.out_report = args.out_report

    srv = ThreadingHTTPServer(("127.0.0.1", args.port), Handler)
    print(f"[monitor] http://127.0.0.1:{args.port}/  (state: {state})")
    try:
        srv.serve_forever()
    except KeyboardInterrupt:
        pass


if __name__ == "__main__":
    main()
