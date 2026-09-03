#!/usr/bin/env python3
"""Verify live data by reading an actual WebSocket broadcast from :9999."""

import paramiko

NL = chr(10)

L = [
    'import base64, json, os, socket, struct, time',
    'CRLF = chr(13) + chr(10)',
    'key = base64.b64encode(os.urandom(16)).decode()',
    'req = (',
    '    "GET /ws HTTP/1.1" + CRLF',
    '    + "Host: 127.0.0.1:9999" + CRLF',
    '    + "Upgrade: websocket" + CRLF',
    '    + "Connection: Upgrade" + CRLF',
    '    + "Sec-WebSocket-Key: " + key + CRLF',
    '    + "Sec-WebSocket-Version: 13" + CRLF + CRLF',
    ')',
    's = socket.create_connection(("127.0.0.1", 9999), timeout=5)',
    's.sendall(req.encode())',
    'buf = b""',
    'marker = (chr(13) + chr(10) + chr(13) + chr(10)).encode()',
    'while marker not in buf:',
    '    chunk = s.recv(4096)',
    '    if not chunk:',
    '        break',
    '    buf += chunk',
    'head = buf.split(marker)[0].decode(errors="ignore")',
    'print("handshake:", head.splitlines()[0] if head else "EMPTY")',
    'print("upgrade-ok:", "101" in head)',
    'rest = buf.split(marker, 1)[1]',
    '',
    'def recv_frame(sock, initial=b""):',
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
    '    opcode = b1 & 0x0F',
    '    return opcode, payload[:ln]',
    '',
    'deadline = time.time() + 45',
    'got = None',
    'frame_rest = rest',
    'while time.time() < deadline:',
    '    fr = recv_frame(s, frame_rest)',
    '    frame_rest = b""',
    '    if fr is None:',
    '        print("connection closed")',
    '        break',
    '    opcode, payload = fr',
    '    if opcode == 1:',
    '        try:',
    '            msg = json.loads(payload.decode())',
    '            got = msg',
    '            break',
    '        except Exception:',
    '            print("non-json text:", payload[:120])',
    '    else:',
    '        print("opcode", opcode, "len", len(payload))',
    '',
    'if got is not None:',
    '    print("message keys:", sorted(got.keys())[:20])',
    '    st = got.get("stats", got)',
    '    svcs = st.get("services") or {}',
    '    print("services in broadcast:", json.dumps(svcs, indent=1)[:900])',
    '    am = st.get("advanced_metrics") or {}',
    '    print("redis:", am.get("redis"))',
    '    print("queue:", am.get("queue"))',
    'else:',
    '    print("NO BROADCAST within deadline")',
    's.close()',
]

PROBE = NL.join(L)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)
cmd = "python3 - << 'PYEOF'" + NL + PROBE + NL + "PYEOF"
_, o, e = ssh.exec_command(cmd, timeout=90)
print(o.read().decode(errors="ignore"))
err = e.read().decode(errors="ignore").strip()
if err:
    print("STDERR:", err)
ssh.close()