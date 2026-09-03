#!/usr/bin/env python3
"""Final classified load test against cluster after S2 worker fix."""

import threading
import time
import urllib.request
from collections import deque

import paramiko

TARGET = "http://192.168.1.222:8080/"
STAGES = [(5, 15), (10, 15), (20, 20), (40, 20), (80, 25)]

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=15)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


def worker(results, deadline):
    while time.time() < deadline:
        t0 = time.time()
        ok = False
        etype = ""
        try:
            req = urllib.request.Request(TARGET, headers={"User-Agent": "LoadTest/1.0"})
            opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
            with opener.open(req, timeout=10) as r:
                r.read(2048)
                ok = r.status < 400
        except urllib.error.HTTPError as ex:
            etype = f"HTTP {ex.code}"
        except Exception as ex:
            etype = type(ex).__name__
        results.append((ok, time.time() - t0, etype))


def run_stage(conc, dur):
    results = deque([])
    deadline = time.time() + dur
    ts = [threading.Thread(target=worker, args=(results, deadline), daemon=True)
          for _ in range(conc)]
    for t in ts:
        t.start()
    for t in ts:
        t.join()
    n = len(results)
    oks = [lat for ok, lat, _ in results if ok]
    errs = {}
    for _, _, et in results:
        if et:
            errs[et] = errs.get(et, 0) + 1
    if oks:
        srt = sorted(oks)
        stats = (round(n / dur, 1), round(sum(srt) / len(srt) * 1000),
                 round(srt[int(len(srt) * .5)] * 1000), round(srt[int(len(srt) * .95)] * 1000))
    else:
        stats = (0, 0, 0, 0)
    return n, len(oks), errs, stats


print(f"[FINAL load test] target={TARGET} (success = HTTP < 400)")
best = None
for conc, dur in STAGES:
    n, ok_n, errs, (rps, avg, p50, p95) = run_stage(conc, dur)
    top_errs = ", ".join(f"{k}:{v}" for k, v in sorted(errs.items(), key=lambda x: -x[1])[:3])
    print(f"[stage c={conc:>3} {dur}s] req={n:>6} ok={ok_n:>6} rps={rps:>6} "
          f"avg={avg:>5}ms p50={p50}ms p95={p95}ms | errors: {top_errs or 'none'}")
    if best is None or rps > best[1]:
        best = (conc, rps)

print()
print("[post-test health]")
print(run("curl -s -o /dev/null -w '%{http_code} %{time_total}s' -m 10 http://127.0.0.1:8080/; echo"))
print(run("uptime"))
ssh.close()