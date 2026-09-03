#!/usr/bin/env python3
"""Staged HTTP load test against S1 nginx edge (:8080) with node health sampling."""

import threading
import time
import urllib.request
from collections import deque

import paramiko

TARGET = "http://192.168.1.222:8080/"
STAGES = [(10, 20), (25, 20), (50, 20), (100, 30)]

NODES = {
    "S1": ("192.168.1.222", "u0_a175", "A2345678"),
    "S2": ("192.168.1.140", "u0_a135", "A23457"),
}

stop_sampling = False
samples = []


def sampler():
    conns = {}
    for label, (host, user, pw) in NODES.items():
        ssh = paramiko.SSHClient()
        ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        try:
            ssh.connect(host, 8022, user, pw, timeout=15)
            conns[label] = ssh
        except Exception as e:
            print(f"[sampler] {label} connect failed: {e}")
    while not stop_sampling:
        row = {"t": time.strftime("%H:%M:%S")}
        for label, ssh in conns.items():
            try:
                _, o, _ = ssh.exec_command(
                    "cat /proc/loadavg | cut -d' ' -f1-3; "
                    "cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null; "
                    "grep MemAvailable /proc/meminfo; grep MemTotal /proc/meminfo",
                    timeout=10)
                lines = o.read().decode().strip().splitlines()
                load = lines[0] if lines else "?"
                temp = round(int(lines[1]) / 1000, 1) if len(lines) > 1 and lines[1].isdigit() else "?"
                mem = "?"
                if len(lines) >= 3:
                    avail = int(lines[-2].split()[1])
                    total = int(lines[-1].split()[1])
                    mem = f"{round((total - avail) / total * 100, 1)}%"
                row[label] = f"load={load} temp={temp}C mem={mem}"
            except Exception as e:
                row[label] = f"err {e}"
        samples.append(row)
        time.sleep(10)
    for ssh in conns.values():
        ssh.close()


def worker(results, deadline):
    while time.time() < deadline:
        t0 = time.time()
        ok = False
        try:
            req = urllib.request.Request(TARGET, headers={"User-Agent": "LoadTest/1.0"})
            opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
            with opener.open(req, timeout=10) as r:
                r.read(4096)
                ok = r.status == 200
        except Exception:
            pass
        results.append((ok, time.time() - t0))


def run_stage(conc, dur):
    results = deque([])
    deadline = time.time() + dur
    threads = [threading.Thread(target=worker, args=(results, deadline), daemon=True)
               for _ in range(conc)]
    for t in threads:
        t.start()
    for t in threads:
        t.join()
    n = len(results)
    oks = [lat for ok, lat in results if ok]
    fails = n - len(oks)
    if oks:
        srt = sorted(oks)
        p50 = srt[int(len(srt) * 0.5)] * 1000
        p95 = srt[int(len(srt) * 0.95)] * 1000
        p99 = srt[min(int(len(srt) * 0.99), len(srt) - 1)] * 1000
        avg = sum(srt) / len(srt) * 1000
    else:
        p50 = p95 = p99 = avg = 0
    return {
        "concurrency": conc, "duration": dur, "requests": n,
        "rps": round(n / dur, 1), "errors": fails,
        "err_pct": round(fails / n * 100, 1) if n else 0,
        "avg_ms": round(avg), "p50_ms": round(p50),
        "p95_ms": round(p95), "p99_ms": round(p99),
    }


print(f"[load test] target={TARGET}")
print("[baseline health sample]")
th = threading.Thread(target=sampler, daemon=True)
th.start()
time.sleep(12)

all_results = []
for conc, dur in STAGES:
    r = run_stage(conc, dur)
    all_results.append(r)
    print(f"[stage c={conc:>3} {dur}s] req={r['requests']:>6} rps={r['rps']:>6} "
          f"err={r['err_pct']:>5}% avg={r['avg_ms']:>5}ms p50={r['p50_ms']}ms "
          f"p95={r['p95_ms']}ms p99={r['p99_ms']}ms")

stop_sampling = True
time.sleep(11)

print()
print("[health during test]")
for s in samples:
    print(s)

print()
print("[post-test service check]")
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(*NODES["S1"], timeout=15)
_, o, _ = ssh.exec_command("curl -s -o /dev/null -w '%{http_code}' -m 5 http://127.0.0.1:8080/", timeout=20)
print("S1 nginx after test:", o.read().decode())
ssh.close()

best = max(all_results, key=lambda r: r["rps"])
print(f"[summary] peak throughput: {best['rps']} rps @ concurrency {best['concurrency']}")