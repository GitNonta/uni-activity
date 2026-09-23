#!/usr/bin/env python3
"""Face-scan speed benchmark: S2 (phone, ONNX CPU) vs PC (ONNX CPU).

POSTs real CelebA photos to each host's /extract endpoint and reports
per-request latency statistics. Uses the same images for both hosts so the
comparison is apples-to-apples (network included for the remote host).
"""
import io
import json
import statistics
import time
import zipfile

import urllib.request

KEY = "uni-activity-ai-secret-key-2026"
ZIP_PATH = r"D:\projects\uni-activity\face_cpp\img_align_celeba.zip"
HOSTS = {
    "S2 (phone 192.168.1.140)": "http://192.168.1.140:8001",
    "PC  (this machine)      ": "http://127.0.0.1:8001",
}
WARMUP = 1
ROUNDS = 12  # timed /extract calls per host


def load_images(n):
    imgs = []
    with zipfile.ZipFile(ZIP_PATH) as z:
        names = [x for x in z.namelist() if x.endswith(".jpg")][:n]
        for name in names:
            imgs.append(z.read(name))
    return imgs


def post_extract(base, blob):
    boundary = "----benchboundary9281"
    body = io.BytesIO()
    body.write(f"--{boundary}\r\n".encode())
    body.write(b'Content-Disposition: form-data; name="image"; filename="bench.jpg"\r\n')
    body.write(b"Content-Type: image/jpeg\r\n\r\n")
    body.write(blob)
    body.write(f"\r\n--{boundary}--\r\n".encode())
    req = urllib.request.Request(base + "/extract", data=body.getvalue(), method="POST")
    req.add_header("Content-Type", f"multipart/form-data; boundary={boundary}")
    req.add_header("X-API-Key", KEY)
    t0 = time.perf_counter()
    with urllib.request.urlopen(req, timeout=60) as resp:
        payload = json.loads(resp.read().decode())
    dt = (time.perf_counter() - t0) * 1000.0
    ok = payload.get("success", payload.get("embedding_512d") is not None)
    return dt, bool(ok), payload


def bench(base, images):
    # warmup (model paths, keep-alive)
    for blob in images[:WARMUP]:
        post_extract(base, blob)
    lat, fails = [], 0
    for blob in images:
        try:
            dt, ok, _ = post_extract(base, blob)
            if ok:
                lat.append(dt)
            else:
                fails += 1
        except Exception as exc:  # noqa: BLE001
            fails += 1
            print(f"    request error: {exc}")
    return lat, fails


def stats_line(lat):
    if not lat:
        return "no successful requests"
    lat_sorted = sorted(lat)
    p95 = lat_sorted[max(0, int(len(lat_sorted) * 0.95) - 1)]
    return (f"mean {statistics.mean(lat):7.1f} ms | median {statistics.median(lat):7.1f} ms | "
            f"min {min(lat):7.1f} | max {max(lat):7.1f} | p95 {p95:7.1f} | n={len(lat)}")


def main():
    print(f"loading {ROUNDS} test images from CelebA zip ...")
    images = load_images(ROUNDS)
    print(f"loaded {len(images)} images\n")

    results = {}
    for label, base in HOSTS.items():
        print(f"=== {label.strip()} -> {base}/extract ===")
        t0 = time.perf_counter()
        lat, fails = bench(base, images)
        wall = time.perf_counter() - t0
        print(f"  {stats_line(lat)}")
        if fails:
            print(f"  ({fails} failed requests)")
        print(f"  wall time for {ROUNDS} sequential scans: {wall:.1f}s "
              f"(~{wall / max(len(lat), 1):.2f}s per scan)\n")
        results[label.strip()] = lat

    if len(results) == 2:
        a, b = results.values()
        if a and b:
            ma, mb = statistics.mean(a), statistics.mean(b)
            faster = "PC" if mb < ma else "S2"
            ratio = max(ma, mb) / min(ma, mb)
            print(f"VERDICT: {faster} is ~{ratio:.2f}x faster on this workload "
                  f"(S2 mean {ma:.0f} ms vs PC mean {mb:.0f} ms)")


if __name__ == "__main__":
    main()
