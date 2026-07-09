import socket
import hashlib
import base64
import struct
import json

HOST = "192.168.1.222"
PORT = 9999

key = base64.b64encode(b"UniActivityMonitor0").decode()
request = (
    f"GET / HTTP/1.1\r\n"
    f"Host: {HOST}:{PORT}\r\n"
    "Upgrade: websocket\r\n"
    "Connection: Upgrade\r\n"
    f"Sec-WebSocket-Key: {key}\r\n"
    "Sec-WebSocket-Version: 13\r\n\r\n"
)

s = socket.socket()
s.connect((HOST, PORT))
s.sendall(request.encode())

# Read upgrade response
resp = b""
while b"\r\n\r\n" not in resp:
    resp += s.recv(1024)
print("Handshake:", resp.decode("utf-8", errors="replace").split("\r\n")[0])

# Read one WebSocket frame
header = s.recv(2)
opcode = header[0] & 0x0F
length = header[1] & 0x7F
if length == 126:
    length = struct.unpack(">H", s.recv(2))[0]
elif length == 127:
    length = struct.unpack(">Q", s.recv(8))[0]

payload = b""
while len(payload) < length:
    payload += s.recv(length - len(payload))

data = json.loads(payload.decode("utf-8"))
print("WebSocket OK! cf_url:", data.get("cf_url"))
print("memory:", data.get("memory"))
print("load:", data.get("load"))
s.close()
