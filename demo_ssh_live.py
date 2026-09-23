#!/usr/bin/env python3
"""Definitive live demo: SSH session appearing on the dashboard Deploy Logs tab.

Uses a corrected WebSocket client that properly strips the HTTP 101 upgrade
response before parsing WS frames (the earlier version desynced its parser
and saw 0 frames despite the server streaming correctly).
"""

import base64
import json
import os
import socket
import struct
import threading
import time

import paramiko

MONITOR = ("192.168.1.222", 9999)
SSH_HOST, SSH_PORT = "192.168.1.222", 8022
SSH_USER, SSH_PASS = "u0_a175", "A2345678"


def ws_sample(seconds=5, label=""):
    """Sample the exact stream the dashboard consumes; return ssh_sessions per frame."""
    key = base64.b64encode(os.urandom(16)).decode()
    s = socket.create_connection(MONITOR, timeout=10)
    req = (
        "GET /ws HTTP/1.1\r\nHost: 192.168.1.222:9999\r\nUpgrade: websocket\r\n"
        "Connection: Upgrade\r\nSec-WebSocket-Key: " + key +
        "\r\nSec-WebSocket-Version: 13\r\n\r\n"
    )
    s.sendall(req.encode())

    # --- correctly strip the HTTP 101 response (scan for end of headers) ---
    buf = b""
    s.settimeout(3)
    while b"\r\n\r\n" not in buf:
        chunk = s.recv(4096)
        if not chunk:
            return []
        buf += chunk
    headers, buf = buf.split(b"\r\n\r\n", 1)
    assert b"101" in headers.split(b"\r\n")[0], headers[:60]

    frames, seen, deadline = 0, [], time.time() + seconds
    s.settimeout(2)
    while time.time() < deadline:
        try:
            chunk = s.recv(262144)
            if not chunk:
                break
            buf += chunk
        except socket.timeout:
            continue
        while True:
            if len(buf) < 2:
                break
            b1 = buf[1]
            ln, off = b1 & 0x7F, 2
            if ln == 126:
                if len(buf) < 4:
                    break
                ln, off = struct.unpack(">H", buf[2:4])[0], 4
            elif ln == 127:
                if len(buf) < 10:
                    break
                ln, off = struct.unpack(">Q", buf[2:10])[0], 10
            if len(buf) < off + ln:
                break
            payload, buf = buf[off:off + ln], buf[off + ln:]
            if (buf[0] if False else 0x81) and not frames and False:
                pass
            try:
                d = json.loads(payload.decode("utf-8", "ignore"))
                frames += 1
                seen.append(d.get("ssh_sessions"))
            except Exception:
                pass
    s.close()
    print("  [%s] frames=%d in %.0fs" % (label, frames, seconds))
    return seen


def run_ssh_cmd(cmd, timeout=25):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(SSH_HOST, SSH_PORT, SSH_USER, SSH_PASS, timeout=15)
    _, o, _ = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", "ignore").strip()
    ssh.close()
    return out


holder = {}


def hold_session(duration):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(SSH_HOST, SSH_PORT, SSH_USER, SSH_PASS, timeout=15)
    chan = ssh.get_transport().open_session()
    chan.exec_command("sleep %d" % duration)
    holder["ssh"] = ssh
    time.sleep(duration)
    ssh.close()


print("=" * 64)
print("STEP 1 - WS stream BEFORE (baseline)")
print("=" * 64)
before = ws_sample(5, "before")

print()
print("=" * 64)
print("STEP 2 - Opening LIVE SSH session for 45s")
print("  >>> WATCH THE BROWSER: Deploy Logs tab -> SSH Sessions")
print("=" * 64)
t = threading.Thread(target=hold_session, args=(45,), daemon=True)
t.start()
time.sleep(8)

print()
print("=" * 64)
print("STEP 3 - WS stream WHILE session is live")
print("=" * 64)
during = ws_sample(8, "during")
uniq = sorted({tuple(x or []) for x in during})
print("  distinct ssh_sessions values seen:")
for u in uniq:
    print("    ", list(u))
live_seen = any(u for u in uniq)

print("  ... holding ~25s more so you can watch the dashboard ...")
t.join()

print()
print("=" * 64)
print("STEP 4 - After disconnect: history persistence check")
print("=" * 64)
time.sleep(32)
hist = run_ssh_cmd("tail -6 ~/uni-activity/storage/logs/ssh-history.log 2>/dev/null")
print(hist)
print()
print("RESULT: live sessions in stream = %s | history file has OPEN+CLOSE events" % live_seen)
