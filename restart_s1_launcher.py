#!/usr/bin/env python3
"""Restart S1 monitor via launcher script + verify WS broadcast."""

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
    '    print("services:", json.dumps(st.get("services", {}), indent=1)[:900])',
    '    am = st.get("advanced_metrics") or {}',
    '    print("redis:", am.get("redis"))',
    '    print("queue:", am.get("queue"))',
    'else:',
    '    print("NO BROADCAST")',
    's.close()',
]
WS_PROBE = NL.join(WS_PROBE_LINES)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=90):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[write launcher]")
print(run(
    "cat > ~/start_monitor_s1.sh <<'EOF'" + NL
    + "#!/data/data/com.termux/files/usr/bin/sh" + NL
    + "cd $HOME/uni-activity" + NL
    + "exec python -u py/monitor_server.py >> $HOME/monitor_server.log 2>&1" + NL
    + "EOF" + NL
    + "chmod +x ~/start_monitor_s1.sh && echo launcher-ok"
))
print()

print("[kill old + launch detached]")
print(run("pkill -f 'monitor_serve[r].py' 2>/dev/null; sleep 2; nohup setsid ~/start_monitor_s1.sh < /dev/null > /dev/null 2>&1 & echo launched"))
time.sleep(6)

print("[pid]")
print(run("pgrep -f 'monitor_serve[r].py' || echo not-running"))
time.sleep(3)
print("[http]", run("curl -s -o /dev/null -w '%{http_code}' -m 3 http://127.0.0.1:9999/"))
print()

wscmd = "python3 - << 'PYEOF'" + NL + WS_PROBE + NL + "PYEOF"
_, o, e = ssh.exec_command(wscmd, timeout=90)
print("[ws broadcast]")
print(o.read().decode(errors="ignore"))
err = e.read().decode(errors="ignore").strip()
if err:
    print("STDERR:", err)

ssh.close()