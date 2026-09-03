#!/usr/bin/env python3
"""Finish optimization: add S2:8004 to nginx upstream, reload, run load test."""

import threading
import time
import urllib.request
from collections import deque

import paramiko

S1 = ("192.168.1.222", "u0_a175", "A2345678")
CONF = "/data/data/com.termux/files/usr/etc/nginx/nginx.conf"


def connect():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(S1[0], 8022, S1[1], S1[2], timeout=15)
    return ssh


def run(ssh, cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


ssh = connect()

print("[add S2:8004 to upstream if missing]")
NL = chr(92) + "n"
sed_cmd = (
    "grep -q '192.168.1.140:8004' " + CONF + " && echo already-present || "
    "(sed -i 's|server 192.168.1.140:8003 max_fails=2 fail_timeout=30s;|"
    "server 192.168.1.140:8003 max_fails=2 fail_timeout=30s;" + NL +
    "        server 192.168.1.140:8004 max_fails=2 fail_timeout=30s;|' " + CONF + " && echo added)"
)
print(run(ssh, sed_cmd))
print()

print("[nginx -t]")
print(run(ssh, "nginx -t 2>&1 | tail -1"))
print()

print("[reload]")
print(run(ssh, "nginx -s reload 2>&1; sleep 1; echo reloaded"))
print()

print("[upstream block now]")
print(run(ssh, "sed -n '/upstream laravel_cluster/,/^}/p' " + CONF))
print()

print("[edge probe x5]")
print(run(ssh, "for i in 1 2 3 4 5; do curl -s -o /dev/null -w '%{http_code} ' "
               "-m 10 http://127.0.0.1:8080/; done; echo"))
ssh.close()


# ---------- benchmark ----------
TARGET = "http://192.168.1.222:8080/"


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


print()
print("[POST-OPTIMIZATION load test] target=" + TARGET)
for conc, dur in [(20, 20), (40, 20), (80, 25)]:
    n, ok_n, errs, (rps, avg, p50, p95) = run_stage(conc, dur)
    top_errs = ", ".join(f"{k}:{v}" for k, v in sorted(errs.items(), key=lambda x: -x[1])[:3])
    print(f"[stage c={conc:>3} {dur}s] req={n:>6} ok={ok_n:>6} rps={rps:>6} "
          f"avg={avg:>5}ms p50={p50}ms p95={p95}ms | errors: {top_errs or 'none'}")

ssh = connect()
print()
print("[post-test health]")
print(run(ssh, "curl -s -o /dev/null -w '%{http_code} %{time_total}s' -m 10 http://127.0.0.1:8080/; echo"))
print(run(ssh, "uptime"))
ssh.close()