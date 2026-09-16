"""Run ai_service/server.py with DirectML disabled (CPU-only ONNX).

The DmlExecutionProvider build hard-crashed the process (no Python
traceback) during the first SCRFD detection call; this wrapper restricts
the visible ONNX providers to CPU so server.py's provider selection (and
insightface's) falls back to CPUExecutionProvider. Everything else —
models, endpoints, API key — is unchanged.

Run:  python run_server_cpu.py            (binds 0.0.0.0 — LAN hosts connect)
      python run_server_cpu.py --host 127.0.0.1   (local only)
"""
import argparse
import onnxruntime as ort

ort.get_available_providers = lambda: ["CPUExecutionProvider"]

import uvicorn  # noqa: E402

if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--port", type=int, default=8001)
    ap.add_argument("--host", default="0.0.0.0",
                    help="bind address (0.0.0.0 lets LAN hosts like the "
                         "web server call /extract and /verify)")
    a = ap.parse_args()
    uvicorn.run("server:app", host=a.host, port=a.port,
                reload=False, workers=1)
