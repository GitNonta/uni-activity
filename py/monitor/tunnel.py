"""
monitor/tunnel.py — Cloudflare tunnel health checker (ping_url_thread).
"""
import time, threading, json, re, os, socket, ssl
import urllib.parse, http.client, subprocess
import monitor.config as cfg
from monitor.telegram import tg_send
from monitor.collectors import get_cf_url

def ping_url_thread():
    import urllib.parse, http.client, socket, ssl, time, subprocess, re

    # ── State tracking ────────────────────────────────────────────────────────
    _fail_count        = 0          # consecutive failures
    _last_restart_time = 0.0        # ป้องกัน restart loop
    _last_error_type   = ""         # DNS / TIMEOUT / HTTP_xxx / SSL / UNKNOWN
    # หมายเหตุ: ไม่ส่ง "Tunnel Recovered" — แจ้งเฉพาะตอนล่มเท่านั้น

    FAIL_THRESHOLD     = 3          # fail กี่ครั้งติดก่อน restart
    RESTART_COOLDOWN   = 120        # วินาที ระหว่าง auto-restart
    CHECK_INTERVAL     = 15         # วินาที ระหว่างการเช็ค

    def resolve_dns_udp(domain: str, dns_server: str = "8.8.8.8") -> str | None:
        """DNS lookup ผ่าน UDP โดยตรง — bypass Android DNS cache"""
        try:
            packet = bytearray([0x12,0x34,0x01,0x00,0x00,0x01,0x00,0x00,0x00,0x00,0x00,0x00])
            for part in domain.split("."):
                packet.append(len(part))
                packet.extend(part.encode("ascii"))
            packet.append(0)
            packet.extend([0x00,0x01,0x00,0x01])
            sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            sock.settimeout(3)
            sock.sendto(packet, (dns_server, 53))
            data, _ = sock.recvfrom(512)
            answers = (data[6] << 8) + data[7]
            if answers == 0:
                return None
            idx = 12
            while data[idx] != 0:
                idx += data[idx] + 1
            idx += 5
            for _ in range(answers):
                if (data[idx] & 0xC0) == 0xC0:
                    idx += 2
                else:
                    while data[idx] != 0:
                        idx += data[idx] + 1
                    idx += 1
                atype  = (data[idx]   << 8) + data[idx+1]
                rdlen  = (data[idx+8] << 8) + data[idx+9]
                idx   += 10
                if atype == 1 and rdlen == 4:
                    return ".".join(str(b) for b in data[idx:idx+4])
                idx += rdlen
        except Exception:
            pass
        return None

    def detect_error(exc: Exception, domain: str) -> str:
        """วิเคราะห์ exception → คืน error type + คำอธิบาย"""
        msg = str(exc).lower()
        # DNS failure
        if any(k in msg for k in ["name or service", "nodename", "gaierror", "dns", "resolve"]):
            return "DNS_FAIL"
        if resolve_dns_udp(domain) is None:
            return "DNS_FAIL"
        # Timeout
        if any(k in msg for k in ["timed out", "timeout", "time out"]):
            return "TIMEOUT"
        # SSL / TLS
        if any(k in msg for k in ["ssl", "certificate", "handshake", "tls"]):
            return "SSL_ERROR"
        # Connection refused
        if any(k in msg for k in ["refused", "connection refused", "111"]):
            return "CONN_REFUSED"
        # HTTP error codes
        for code in ["502", "503", "504", "521", "522", "523", "524", "530"]:
            if code in msg:
                return f"HTTP_{code}"
        return "UNKNOWN"

def push_active_url_to_github(http_url: str, ssh_url: str = "") -> bool:
    """Update docs/active_url.json on GitHub Pages via GitHub REST API."""
    pat = None
    env_path = "/data/data/com.termux/files/home/uni-activity/.env"
    if os.path.exists(env_path):
        try:
            with open(env_path, "r", encoding="utf-8") as f:
                for line in f:
                    if line.startswith("GITHUB_PAT="):
                        pat = line.split("=", 1)[1].strip().strip('"\'')
                        break
        except Exception:
            pass

    if not pat:
        return False

    import urllib.request, json as _json, base64

    owner = "GitNonta"
    repo = "uni-activity"
    path = "docs/active_url.json"
    api_url = f"https://api.github.com/repos/{owner}/{repo}/contents/{path}"

    headers = {
        "Authorization": f"token {pat}",
        "Accept": "application/vnd.github.v3+json",
        "User-Agent": "UniActivity-Monitor",
        "Content-Type": "application/json",
    }

    sha = None
    try:
        req = urllib.request.Request(api_url, headers=headers)
        with urllib.request.urlopen(req, timeout=8) as r:
            sha = _json.loads(r.read()).get("sha")
    except Exception:
        pass

    content_data = {
        "url": http_url,
        "ssh_url": ssh_url,
        "updated_at": time.strftime("%Y-%m-%d %H:%M:%S")
    }
    content_b64 = base64.b64encode(_json.dumps(content_data, indent=2).encode("utf-8")).decode("utf-8")

    payload = {
        "message": f"chore: update active tunnel URL to {http_url} [auto-sync]",
        "content": content_b64,
    }
    if sha:
        payload["sha"] = sha

    try:
        data = _json.dumps(payload).encode("utf-8")
        req = urllib.request.Request(api_url, data=data, method="PUT", headers=headers)
        with urllib.request.urlopen(req, timeout=10) as r:
            return r.status in (200, 201)
    except Exception:
        return False


def do_restart_tunnel() -> str | None:
    """Kill cloudflared → start ทั้ง 2 ใหม่ → รอ HTTP URL → return URL หรือ None"""
    try:
        subprocess.run(["pkill", "-9", "cloudflared"], capture_output=True)
        time.sleep(3)

        log_http = "/data/data/com.termux/files/home/cloudflared.log"
        log_ssh  = "/data/data/com.termux/files/home/cloudflared-ssh.log"

        for lp in [log_http, log_ssh]:
            with open(lp, "w") as f:
                f.write("")

        # Tunnel 1 — HTTP → Nginx Load Balancer :8088 (dual-node)
        subprocess.Popen(
            f"nohup cloudflared tunnel --url {cfg.TUNNEL_TARGET_URL} "
            f"--no-autoupdate > {log_http} 2>&1 &",
            shell=True,
        )
        time.sleep(2)

        # Tunnel 2 — SSH :80
        subprocess.Popen(
            f"nohup cloudflared tunnel --url http://127.0.0.1:80 "
            f"--no-autoupdate > {log_ssh} 2>&1 &",
            shell=True,
        )

        # รอ HTTP URL (สูงสุด 40 วิ)
        new_url = None
        ssh_url = None
        for _ in range(40):
            time.sleep(1)
            try:
                if not new_url:
                    with open(log_http, "r") as f:
                        content = f.read()
                    m = re.search(r"https://[a-zA-Z0-9-]+\.trycloudflare\.com", content)
                    if m:
                        new_url = m.group(0)
            except Exception:
                pass
            try:
                if not ssh_url:
                    with open(log_ssh, "r") as f:
                        content = f.read()
                    m = re.search(r"https://[a-zA-Z0-9-]+\.trycloudflare\.com", content)
                    if m:
                        ssh_url = m.group(0)
            except Exception:
                pass
            if new_url and ssh_url:
                break

        if new_url:
            # อัพเดต .env
            env_path = "/data/data/com.termux/files/home/uni-activity/.env"
            if os.path.exists(env_path):
                with open(env_path, "r") as f:
                    env_lines = f.readlines()
                with open(env_path, "w") as f:
                    for line in env_lines:
                        f.write(f"APP_URL={new_url}\n" if line.startswith("APP_URL=") else line)

            # อัพเดต active_url.json
            json_path = "/data/data/com.termux/files/home/uni-activity/docs/active_url.json"
            os.makedirs(os.path.dirname(json_path), exist_ok=True)
            with open(json_path, "w") as f:
                json.dump({
                    "url"        : new_url,
                    "ssh_url"    : ssh_url or "",
                    "updated_at" : time.strftime("%Y-%m-%d %H:%M:%S"),
                }, f)

            # Auto-push to GitHub Pages
            threading.Thread(target=push_active_url_to_github, args=(new_url, ssh_url or ""), daemon=True).start()

        return new_url
    except Exception:
        return None

    # ── Main loop ─────────────────────────────────────────────────────────────
    while True:
        time.sleep(2)
        url = get_cf_url()

        # ไม่มี URL หรือเป็น local address
        if not url or url == "Not Found" or any(
            loc in url for loc in ["localhost", "127.0.0.1", "192.168."]
        ):
            cfg.url_status["online"]     = False
            cfg.url_status["ping_ms"]    = 0
            cfg.url_status["error"]      = "NO_URL"
            cfg.url_status["url"]        = url or ""
            time.sleep(CHECK_INTERVAL)
            continue

        cfg.url_status["url"] = url
        parsed = urllib.parse.urlparse(url)
        domain = parsed.netloc
        error_type = ""

        try:
            t0 = time.time()

            # 1. DNS via UDP
            ip = resolve_dns_udp(domain)
            if not ip:
                raise Exception(f"DNS_FAIL: cannot resolve {domain}")

            # 2. HTTP HEAD request
            if parsed.scheme == "https":
                ctx  = ssl._create_unverified_context()
                conn = http.client.HTTPSConnection(ip, timeout=5, context=ctx)
            else:
                conn = http.client.HTTPConnection(ip, timeout=5)

            conn.request("HEAD", "/", headers={"Host": domain, "User-Agent": "UniMonitor/2.0"})
            resp = conn.getresponse()

            # HTTP 5xx / Cloudflare error codes = ถือว่า tunnel พัง
            if resp.status in (502, 503, 521, 522, 523, 524, 530):
                raise Exception(f"HTTP_{resp.status}")

            ping_ms = int((time.time() - t0) * 1000)
            cfg.url_status.update({"online": True, "ping_ms": ping_ms, "error": "", "url": url})

            _fail_count      = 0
            error_type       = ""
            # ไม่ส่ง Recovered — แจ้งเฉพาะตอนล่มเท่านั้น

        except Exception as exc:
            error_type = detect_error(exc, domain)
            cfg.url_status.update({"online": False, "ping_ms": 0, "error": error_type, "url": url})
            _fail_count    += 1

            # ──────────────────────────────────────────────────────────────────
            # Auto-restart เมื่อ fail ถึง threshold และผ่าน cooldown
            # ──────────────────────────────────────────────────────────────────
            if _fail_count >= FAIL_THRESHOLD and (time.time() - _last_restart_time) > RESTART_COOLDOWN:
                _last_restart_time = time.time()
                ts = time.strftime("%H:%M:%S")

                # แจ้งก่อน restart
                error_desc = {
                    "DNS_FAIL"    : "🔴 DNS ไม่สามารถ resolve ได้ (อาจ Tunnel ตาย)",
                    "TIMEOUT"     : "⏱ Connection Timeout",
                    "SSL_ERROR"   : "🔒 SSL/TLS Error",
                    "CONN_REFUSED": "🚫 Connection Refused",
                    "HTTP_502"    : "💥 HTTP 502 Bad Gateway",
                    "HTTP_503"    : "🔧 HTTP 503 Service Unavailable",
                    "HTTP_521"    : "☁️ HTTP 521 Web Server Down",
                    "HTTP_522"    : "⏰ HTTP 522 Connection Timed Out",
                    "HTTP_523"    : "🔌 HTTP 523 Origin Unreachable",
                    "HTTP_524"    : "⌛ HTTP 524 A Timeout Occurred",
                    "HTTP_530"    : "🌐 HTTP 530 Cloudflare Error",
                }.get(error_type, f"❓ {error_type}")

                tg_send(
                    f"🚨 <b>Tunnel Failure Detected!</b>\n"
                    f"━━━━━━━━━━━━━━━━━━━━\n"
                    f"❌ Error  : {error_desc}\n"
                    f"🔗 URL   : <a href='{url}'>{url}</a>\n"
                    f"🔢 Fails : {_fail_count} consecutive\n"
                    f"🕐 Time  : {ts}\n\n"
                    f"🔄 กำลัง Auto-restart Tunnel..."
                )

                # restart ใน background thread
                def _auto_restart(old_url=url, err=error_type):
                    new_url = do_restart_tunnel()
                    ts2 = time.strftime("%H:%M:%S")

                    # อ่าน SSH URL จาก active_url.json
                    new_ssh = ""
                    try:
                        jp = "/data/data/com.termux/files/home/uni-activity/docs/active_url.json"
                        with open(jp, "r") as f:
                            new_ssh = json.load(f).get("ssh_url", "")
                    except Exception:
                        pass

                    if new_url:
                        h_line = f"<a href='{new_url}'>{new_url}</a>"
                        s_line = (
                            f"\n🔐 SSH URL:\n<a href='{new_ssh}'>{new_ssh}</a>"
                            if new_ssh else ""
                        )
                        tg_send(
                            f"✅ <b>Auto-Restart สำเร็จ!</b>\n"
                            f"━━━━━━━━━━━━━━━━━━━━\n"
                            f"🌐 HTTP URL:\n{h_line}"
                            f"{s_line}\n"
                            f"📝 .env อัพเดตแล้ว\n"
                            f"🕐 {ts2}"
                        )
                    else:
                        tg_send(
                            f"❌ <b>Auto-Restart ล้มเหลว!</b>\n"
                            f"━━━━━━━━━━━━━━━━━━━━\n"
                            f"ไม่ได้รับ URL ใหม่จาก Cloudflare\n"
                            f"🕐 {ts2}\n"
                            f"💡 ลองพิมพ์ /tunnel_restart"
                        )

                threading.Thread(target=_auto_restart, daemon=True).start()
                _fail_count = 0   # reset หลัง trigger restart

        time.sleep(CHECK_INTERVAL)
