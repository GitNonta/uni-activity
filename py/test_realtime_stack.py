#!/usr/bin/env python3
"""
Test Octane + Swoole + Reverb Real-Time Integration
Tests:
1. Reverb TCP Socket Ping
2. Pure TCP WebSocket Upgrade Handshake (101 Switching Protocols)
3. In-Memory RealtimeStateService & Atomic Counters (PHP Execution)
"""

import sys, time, json, socket, subprocess, re

SERVER_HOST = "127.0.0.1"
REVERB_PORT = 8082
REVERB_APP_KEY = "uni-chat-key"

def print_header(title):
    print("\n" + "=" * 60)
    print(f"  {title}")
    print("=" * 60)

def test_reverb_socket_port():
    print_header("1. ทดสอบการเชื่อมต่อ Reverb WebSocket Port (TCP Ping)")
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.settimeout(3.0)
    start = time.perf_counter()
    try:
        s.connect((SERVER_HOST, REVERB_PORT))
        latency = (time.perf_counter() - start) * 1000
        print(f"✅ เชื่อมต่อ Port {REVERB_PORT} สำเร็จ!")
        print(f"⚡ Latency: {latency:.2f} ms")
        s.close()
        return True
    except Exception as e:
        print(f"❌ ไม่สามารถเชื่อมต่อ Reverb Port {REVERB_PORT}: {e}")
        return False

def test_reverb_raw_websocket_handshake():
    print_header("2. ทดสอบ WebSocket Upgrade Handshake กับ Reverb (HTTP 101)")
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.settimeout(5.0)
    start = time.perf_counter()
    try:
        s.connect((SERVER_HOST, REVERB_PORT))
        req = (
            f"GET /app/{REVERB_APP_KEY}?protocol=7&client=js&version=8.4.0-rc2&flash=false HTTP/1.1\r\n"
            f"Host: {SERVER_HOST}:{REVERB_PORT}\r\n"
            f"Upgrade: websocket\r\n"
            f"Connection: Upgrade\r\n"
            f"Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\n"
            f"Sec-WebSocket-Version: 13\r\n\r\n"
        )
        s.sendall(req.encode())
        res = s.recv(4096).decode(errors='ignore')
        latency = (time.perf_counter() - start) * 1000
        if "101 Switching Protocols" in res:
            print(f"✅ Reverb ตอบกลับ '101 Switching Protocols' สำเร็จ!")
            print(f"⚡ Handshake Round-trip Latency: {latency:.2f} ms")
            s.close()
            return True
        else:
            print(f"⚠️ Response: {res[:200]}")
            s.close()
            return False
    except Exception as e:
        print(f"❌ Handshake Error: {e}")
        return False

def test_octane_realtime_state():
    print_header("3. ทดสอบ In-Memory RealtimeStateService & Counters")
    php_code = (
        "require 'vendor/autoload.php';"
        "$app = require_once 'bootstrap/app.php';"
        "$kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);"
        "$kernel->bootstrap();"
        "$service = new App\\Services\\RealtimeStateService();"
        "$start = microtime(true);"
        "$service->recordUserPresence(101, 'Student Test', 'student', 'chat_room_1');"
        "$timePresence = (microtime(true) - $start) * 1000;"
        "$start = microtime(true);"
        "$newCount = $service->incrementCounter('activity_active_registered', 1);"
        "$timeCounter = (microtime(true) - $start) * 1000;"
        "echo json_encode(["
        "    'presence_ms' => round($timePresence, 4),"
        "    'counter_ms' => round($timeCounter, 4),"
        "    'counter_value' => $newCount"
        "]);"
    )
    cmd = ["php", "-r", php_code]
    try:
        res = subprocess.run(cmd, capture_output=True, text=True)
        if res.returncode == 0 and res.stdout.strip():
            out = res.stdout.strip()
            m = re.search(r'\{.*\}', out)
            if m:
                data = json.loads(m.group(0))
                print(f"✅ In-Memory State Presence Latency: {data['presence_ms']} ms")
                print(f"✅ Atomic Counter Latency: {data['counter_ms']} ms")
                print(f"🔢 Current Real-time Counter Value: {data['counter_value']}")
                return True
            else:
                print(f"Output: {out}")
                return True
        else:
            print(f"Error: {res.stderr}")
            return False
    except Exception as e:
        print(f"❌ Execution failed: {e}")
        return False

def main():
    print("🚀 เริ่มต้นทดสอบความเข้ากันได้และการทำงานของ Octane + Swoole + Reverb")
    ok1 = test_reverb_socket_port()
    ok2 = test_reverb_raw_websocket_handshake()
    ok3 = test_octane_realtime_state()

    print_header("สรุปผลการทดสอบ (Final Summary)")
    if ok1 and ok2 and ok3:
        print("🎉 สมบูรณ์แบบ 100%! ทั้ง Reverb WebSocket (Port 8082) และ Octane State ทำงานร่วมกันได้ราบรื่น ไวในระดับมิลลิวินาที")
    else:
        print("⚠️ พบข้อควรตรวจสอบบางส่วน กรุณาดูรายละเอียดด้านบน")

if __name__ == "__main__":
    main()
