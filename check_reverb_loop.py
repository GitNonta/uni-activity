#!/usr/bin/env python3
"""Check reverb restart loop + rerun corrected load test (2xx/3xx = success)."""

import threading
import time
import urllib.request
from collections import deque

import paramiko

TARGET = "http://192.168.1.222:8080/"
STAGES = [(5, 15), (10, 15), (20, 15), (40, 20)]

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=15)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[reverb processes]")
print(run("pgrep -fc 'reverb:start'; pgrep -af 'reverb:start' | head -3"))
print()

print("[who spawns reverb? watchdog scripts]")
print(run("grep -rln 'reverb:start' ~/.termux/boot/ ~/uni-activity/*.sh ~/start_*.sh 2>/dev/null"))
print()

print("[current loadavg]")
print(run("cat /proc/loadavg"))


def worker(results, deadline):
    while time.time() < deadline:
        t0 = time.time()
        ok = False
        try:
            req = urllib.request.Request(TARGET, headers={"User-Agent": "LoadTest/1.0"})
            opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
            with opener.open(req, timeout=10) as r:
                r.read(2048)
                ok = r.status < 400
        except Exception:
            pass
        results.append((ok, time.time() - t0))


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
    oks = [lat for ok, lat in results if ok]
    fails = n - len(oks)
    if oks:
        srt = sorted(oks)
        return n, fails, round(n / dur, 1), round(sum(srt) / len(srt) * 1000), \
            round(srt[int(len(srt) * .5)] * 1000), round(srt[int(len(srt) * .95)] * 1000)
    return n, fails, 0, 0, 0, 0


print()
print(f"[corrected load test] target={TARGET} (success = HTTP < 400)")
for conc, dur in STAGES:
    n, f, rps, avg, p50, p95 = run_stage(conc, dur)
    print(f"[stage c={conc:>3} {dur}s] req={n:>6} ok={n - f:>6} fail={f:>4} "
          f"rps={rps:>6} avg={avg:>5}ms p50={p50}ms p95={p95}ms")

print()
print("[post-test loadavg + probe]")
print(run("cat /proc/loadavg"))
print(run("curl -s -o /dev/null -w '%{http_code} %{time_total}s' -m 10 http://127.0.0.1:8080/; echo"))
ssh.close()