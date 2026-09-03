#!/usr/bin/env python3
"""Fix S2 MONITOR_SERVICES line; probe AI node 8001/8000 reachability."""

import paramiko

NL = chr(10)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[current bad line]")
print(run("grep -n 'MONITOR_SERVICES' ~/uni-activity/.env"))
print()

value = ("Web Workers (artisan serve),Datastore (Valkey),Queue Store (Valkey),"
         "PostgreSQL Database,Queue Worker,AI Biometrics Face Service,SSH / SFTP Server")

lines = [
    "sed -i '/^MONITOR_SERVICES=/d' ~/uni-activity/.env",
    "printf '%s" + chr(92) + "n' '" + "MONITOR_SERVICES=" + value + "' >> ~/uni-activity/.env",
    "grep '^MONITOR_SERVICES=' ~/uni-activity/.env",
]
print("[rewrite line properly]")
print(run(NL.join(lines)))
print()

print("[AI service listening ports]")
print(run("ss -ltn | grep -E ':(8000|8001)' || echo none"))
print()

print("[find AI server.py]")
print(run("ls ~/ai_project/ 2>/dev/null; find ~ -maxdepth 3 -name 'server.py' 2>/dev/null | head -5"))
print()

print("[health checks]")
print(run("curl -s -m 3 http://127.0.0.1:8001/health || echo '8001 fail'"))
print(run("curl -s -m 3 http://127.0.0.1:8000/health || echo '8000 fail'"))
print()

ssh.close()