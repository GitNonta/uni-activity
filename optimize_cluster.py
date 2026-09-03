#!/usr/bin/env python3
"""Optimize cluster for higher load.

Levers (from inspection):
- S2 missing config/route caches -> php artisan config:cache route:cache
- Both nodes: view:cache
- S2 has 1.35GB available vs S1 0.5GB -> add 4th worker :8004 on S2
- Update S2 launcher port list; add S2:8004 to S1 nginx upstream
- Verify workers, then quick benchmark at c=20/40
"""

import threading
import time
import urllib.request
from collections import deque

import paramiko

S1 = ("192.168.1.222", "u0_a175", "A2345678")
S2 = ("192.168.1.140", "u0_a135", "A23457")


def connect(host, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, user, pw, timeout=15)
    return ssh


def run(ssh, cmd, timeout=90):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


# ---------- S2: caches + 4th worker ----------
s2 = connect(*S2)
print("[S2] build config/route/view caches")
print(run(s2, "cd ~/uni-activity && php artisan config:cache 2>&1 | tail -1 && "
              "php artisan route:cache 2>&1 | tail -1 && "
              "php artisan view:cache 2>&1 | tail -1"))
print()

print("[S2] start worker :8004")
cmd = ("cd ~/uni-activity && nohup php artisan serve --host 0.0.0.0 --port 8004 "
       "> serve-8004.log 2>&1 < /dev/null & echo started-8004")
print(run(s2, cmd))
time.sleep(8)
print(run(s2, "pgrep -af 'artisan serve' | grep -v pgrep"))
print()

print("[S2] verify all workers x3")
for port in ("8000", "8002", "8003", "8004"):
    print(run(s2, f"for i in 1 2 3; do curl -s -o /dev/null -w '%{{http_code}} ' "
                  f"-m 10 http://127.0.0.1:{port}/; done; echo"))
print()

# update S2 launcher to include 8004
launcher = (
    "#!/data/data/com.termux/files/usr/bin/bash" + chr(10)
    + "cd $HOME/uni-activity || exit 1" + chr(10)
    + "for p in 8000 8002 8003 8004; do" + chr(10)
    + "  if ! (echo > /dev/tcp/127.0.0.1/$p) 2>/dev/null; then" + chr(10)
    + "    setsid nohup php artisan serve --host 0.0.0.0 --port $p"
    + " > serve-$p.log 2>&1 < /dev/null &" + chr(10)
    + "  fi" + chr(10)
    + "done" + chr(10)
    + "echo launcher-done" + chr(10)
)
sftp = s2.open_sftp()
with sftp.open("/data/data/com.termux/files/home/start_web_workers_s2.sh", "w") as f:
    f.write(launcher)
sftp.chmod("/data/data/com.termux/files/home/start_web_workers_s2.sh", 0o755)
sftp.close()
print("[S2] launcher updated with :8004")
s2.close()

# ---------- S1: add S2:8004 to nginx upstream ----------
s1 = connect(*S1)
conf_path = "/data/data/com.termux/files/usr/etc/nginx/nginx.conf"
print("[S1] current laravel_cluster block")
print(run(s1, f"sed -n '/upstream laravel_cluster/,/^}}/p' {conf_path}"))
print()

add_cmd = (
    f"grep -q '192.168.1.140:8004' {conf_path} || "
    f"sed -i 's|server 192.168.1.140:8003 max_fails=2 fail_timeout=30s;|"
    f"server 192.168.1.140:8003 max_fails=2 fail_timeout=30s;\
"
    f"        server 192.168.1.140:8004 max_fails=2 fail_timeout=30s;|' {conf_path} && "
    f"nginx -t 2>&1 | tail -1"
)
print("[S1] add S2:8004 to upstream")
print(run(s1, add_cmd))
print()

print("[S1] reload nginx")
print(run(s1, "nginx -s reload 2>&1; sleep 1; pgrep -c nginx"))
print()

print("[S1] verify edge x5")
print(run(s1, "for i in 1 2 3 4 5; do curl -s -o /dev/null -w '%{http_code} ' "
              "-m 10 http://127.0.0.1:8080/; done; echo"))
s1.close()


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
print(f"[POST-OPTIMIZATION load test] target={TARGET}")
for conc, dur in [(20, 20), (40, 20), (80, 25)]:
    n, ok_n, errs, (rps, avg, p50, p95) = run_stage(conc, dur)
    top_errs = ", ".join(f"{k}:{v}" for k, v in sorted(errs.items(), key=lambda x: -x[1])[:3])
    print(f"[stage c={conc:>3} {dur}s] req={n:>6} ok={ok_n:>6} rps={rps:>6} "
          f"avg={avg:>5}ms p50={p50}ms p95={p95}ms | errors: {top_errs or 'none'}")

ssh = connect(*S1)
print()
print("[post-test health]")
print(run(ssh, "curl -s -o /dev/null -w '%{http_code} %{time_total}s' -m 10 http://127.0.0.1:8080/; echo"))
print(run(ssh, "uptime"))
ssh.close()