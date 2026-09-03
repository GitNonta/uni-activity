#!/usr/bin/env python3
"""Sync 5a0372b to both nodes, set MONITOR_SERVICES on S2, restart, verify alerts."""

import time

import paramiko

NL = chr(10)

WS_PROBE_LINES = [
    'import base64, json, os, socket, struct, time',
    'CRLF = chr(13) + chr(10)',
    'key = base64.b64encode(os.urandom(16)).decode()',
    'req = ("GET /ws HTTP/1.1" + CRLF + "Host: 127.0.0.1:9999" + CRLF',
    '       + "Upgrade: websocket" + CRLF + "Connection: Upgrade" + CRLF',
    '       + "Sec-WebSocket-Key: " + key + CRLF',
    '       + "Sec-WebSocket-Version: 13" + CRLF + CRLF)',
    's = socket.create_connection(("127.0.0.1", 9999), timeout=5)',
    's.sendall(req.encode())',
    'buf = b""',
    'marker = (chr(13) + chr(10) + chr(13) + chr(10)).encode()',
    'while marker not in buf:',
    '    c = s.recv(4096)',
    '    if not c:',
    '        break',
    '    buf += c',
    'head = buf.split(marker)[0].decode(errors="ignore")',
    'print("handshake:", head.splitlines()[0] if head else "EMPTY")',
    'rest = buf.split(marker, 1)[1]',
    '',
    'def recv_frame(sock, initial=b""):',
    '    try:',
    '        return _inner(sock, initial)',
    '    except Exception:',
    '        return None',
    '',
    'def _inner(sock, initial=b""):',
    '    data = initial',
    '    while len(data) < 2:',
    '        c = sock.recv(4096)',
    '        if not c:',
    '            return None',
    '        data += c',
    '    b1, b2 = data[0], data[1]',
    '    ln = b2 & 0x7F',
    '    off = 2',
    '    if ln == 126:',
    '        while len(data) < 4:',
    '            c = sock.recv(4096)',
    '            if not c:',
    '                return None',
    '            data += c',
    '        ln = struct.unpack(">H", data[2:4])[0]',
    '        off = 4',
    '    elif ln == 127:',
    '        while len(data) < 10:',
    '            c = sock.recv(4096)',
    '            if not c:',
    '                return None',
    '            data += c',
    '        ln = struct.unpack(">Q", data[2:10])[0]',
    '        off = 10',
    '    payload = data[off:]',
    '    while len(payload) < ln:',
    '        c = sock.recv(4096)',
    '        if not c:',
    '            break',
    '        payload += c',
    '    return b1 & 0x0F, payload[:ln]',
    '',
    'deadline = time.time() + 45',
    'got = None',
    'frame_rest = rest',
    'while time.time() < deadline:',
    '    fr = recv_frame(s, frame_rest)',
    '    frame_rest = b""',
    '    if fr is None:',
    '        break',
    '    opcode, payload = fr',
    '    if opcode in (1, 2):',
    '        try:',
    '            got = json.loads(payload.decode("utf-8", errors="ignore"))',
    '            break',
    '        except Exception:',
    '            pass',
    'if got:',
    '    st = got.get("stats", got)',
    '    print("alerts:", json.dumps(st.get("alerts", [])))',
    '    svcs = st.get("services") or {}',
    '    stopped = [k for k, v in svcs.items() if v == "Stopped"]',
    '    print("stopped services:", stopped)',
    'else:',
    '    print("NO BROADCAST")',
    's.close()',
]
WS_PROBE = NL.join(WS_PROBE_LINES)

NODES = {
    "S1": ("192.168.1.222", "u0_a175", "A2345678"),
    "S2": ("192.168.1.140", "u0_a135", "A23457"),
}

MONITOR_SERVICES_S2 = (
    "Web Workers (artisan serve),Datastore (Valkey),Queue Store (Valkey),"
    "PostgreSQL Database,Queue Worker,AI Biometrics Face Service,SSH / SFTP Server"
)


def connect(node):
    host, user, pw = node
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, user, pw, timeout=20)
    return ssh


def run(ssh, cmd, timeout=90):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[waiting 80s for auto-sync]")
time.sleep(80)

for label, node in NODES.items():
    print("=" * 60)
    print(f"[{label}]")
    ssh = connect(node)

    head = run(ssh, "cd ~/uni-activity && git rev-parse --short HEAD")
    if "653bc43" not in head:
        print("forcing sync from", head)
        print(run(ssh, "cd ~/uni-activity && git fetch origin main && git reset --hard origin/main && git rev-parse --short HEAD"))
    else:
        print("HEAD:", head)

    if label == "S2":
        # .env already has the correct MONITOR_SERVICES line (set out-of-band);
        # only verify it here.
        print("[verify MONITOR_SERVICES]", run(ssh, "grep '^MONITOR_SERVICES=' ~/uni-activity/.env"))

    launcher = "~/start_monitor_s1.sh" if label == "S1" else "~/start_monitor_s2.sh"
    print("[restart]", run(ssh,
        "pkill -f 'monitor_serve[r].py' 2>/dev/null; sleep 2; "
        f"nohup setsid {launcher} < /dev/null > /dev/null 2>&1 & sleep 5; "
        "pgrep -f 'monitor_serve[r].py'"
    ))
    time.sleep(3)
    print("[http]", run(ssh, "curl -s -o /dev/null -w '%{http_code}' -m 3 http://127.0.0.1:9999/"))

    wscmd = "python3 - << 'PYEOF'" + NL + WS_PROBE + NL + "PYEOF"
    _, o, e = ssh.exec_command(wscmd, timeout=90)
    print("[ws broadcast]")
    print(o.read().decode(errors="ignore"))
    err = e.read().decode(errors="ignore").strip()
    if err:
        print("STDERR:", err)
    ssh.close()