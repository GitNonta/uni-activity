"""
monitor/proxy_manager.py — Proxy Blocklist Management and Reachability Tester.
Manages domain/IP blacklist for Squid (:3128) and SOCKS5 (:1080), and runs multi-channel connectivity tests.
"""
import os
import json
import time
import uuid
import subprocess
from pathlib import Path

# Paths
APP_DIR = Path(__file__).resolve().parent.parent.parent
BLOCKLIST_JSON = APP_DIR / "storage" / "proxy_blocklist.json"
SQUID_CONF = Path("/data/data/com.termux/files/usr/etc/squid/squid.conf")
SQUID_BLOCKED_DOMAINS = Path("/data/data/com.termux/files/usr/etc/squid/blocked_domains.txt")
SQUID_BLOCKED_IPS = Path("/data/data/com.termux/files/usr/etc/squid/blocked_ips.txt")


def get_default_blocklist():
    return {
        "blocked_domains": ["tiktok.com", "doubleclick.net"],
        "blocked_ips": [],
        "items": [
            {
                "id": "blk-1",
                "target": "tiktok.com",
                "type": "domain",
                "reason": "Social media video streaming bandwidth restriction",
                "created_at": "2026-09-04 16:45:00",
                "enabled": True
            },
            {
                "id": "blk-2",
                "target": "doubleclick.net",
                "type": "domain",
                "reason": "Tracking and telemetry banner blocker",
                "created_at": "2026-09-04 16:45:00",
                "enabled": True
            }
        ],
        "updated_at": int(time.time())
    }


def load_blocklist():
    """Load blocklist configuration from storage/proxy_blocklist.json."""
    if not BLOCKLIST_JSON.exists():
        data = get_default_blocklist()
        save_blocklist(data)
        return data
    try:
        with open(BLOCKLIST_JSON, "r", encoding="utf-8") as f:
            return json.load(f)
    except Exception:
        return get_default_blocklist()


def normalize_target(raw_target):
    """
    Extract pure hostname or IP from raw_target (supporting URLs, schemes, ports, paths, etc.).
    Returns (cleaned_target, detected_type)
    """
    import re
    from urllib.parse import urlparse

    t = str(raw_target or "").strip()
    if not t:
        return "", "domain"

    # CIDR IP check e.g. 192.168.1.0/24
    if re.match(r"^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d{1,2}$", t):
        return t, "ip"

    # Parse URL if scheme or path present
    if "://" in t:
        try:
            parsed = urlparse(t)
            host = parsed.hostname or parsed.netloc or ""
        except Exception:
            host = t.split("://")[-1].split("/")[0]
    elif "/" in t:
        try:
            parsed = urlparse("http://" + t)
            host = parsed.hostname or parsed.netloc or ""
        except Exception:
            host = t.split("/")[0]
    else:
        host = t

    # Strip port if present e.g. host:8080 (unless IPv6)
    if ":" in host and not host.startswith("["):
        host = host.split(":")[0]

    host = host.strip().lower().rstrip("/")
    # Clean wildcards or dots
    host = host.lstrip("*.")

    if not host:
        return "", "domain"

    if re.match(r"^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$", host):
        return host, "ip"

    return host, "domain"


def rebuild_blocklist_arrays(data):
    """
    Synchronize data['blocked_domains'] and data['blocked_ips'] with data['items'].
    ONLY items with enabled: True are included, ensuring Squid and SOCKS5 unblock disabled items.
    """
    active_domains = []
    active_ips = []
    cleaned_items = []

    for it in data.get("items", []):
        raw_target = it.get("target", "")
        clean_target, detected_type = normalize_target(raw_target)
        if not clean_target:
            continue
        it["target"] = clean_target
        if it.get("type") not in ["domain", "ip"]:
            it["type"] = detected_type
        cleaned_items.append(it)

        if it.get("enabled", True):
            if it.get("type") == "ip":
                if clean_target not in active_ips:
                    active_ips.append(clean_target)
            else:
                if clean_target not in active_domains:
                    active_domains.append(clean_target)

    data["items"] = cleaned_items
    data["blocked_domains"] = active_domains
    data["blocked_ips"] = active_ips


def save_blocklist(data):
    """Save blocklist configuration and sync to Squid & SOCKS5."""
    rebuild_blocklist_arrays(data)
    data["updated_at"] = int(time.time())
    BLOCKLIST_JSON.parent.mkdir(parents=True, exist_ok=True)
    with open(BLOCKLIST_JSON, "w", encoding="utf-8") as f:
        json.dump(data, f, indent=2, ensure_ascii=False)
    sync_squid_files(data)


def sync_squid_files(data=None):
    """Sync blocklist entries to Squid text files and apply squid -k reconfigure."""
    if data is None:
        data = load_blocklist()

    # Domains & IPs come strictly from active enabled targets (rebuild_blocklist_arrays)
    domains = [d.strip().lower() for d in data.get("blocked_domains", []) if d.strip()]
    ips = [ip.strip() for ip in data.get("blocked_ips", []) if ip.strip()]

    # 1. Write Squid domain list (.domain matches domain and subdomains in squid dstdomain)
    try:
        SQUID_BLOCKED_DOMAINS.parent.mkdir(parents=True, exist_ok=True)
        lines = []
        for d in domains:
            clean_d, _ = normalize_target(d)
            if not clean_d:
                continue
            dot_domain = f".{clean_d.lstrip('.')}"
            if dot_domain not in lines:
                lines.append(dot_domain)
        with open(SQUID_BLOCKED_DOMAINS, "w", encoding="utf-8") as f:
            f.write("\n".join(lines) + "\n" if lines else "")
    except Exception:
        pass

    # 2. Write Squid IP list (0.0.0.0/32 as dummy to avoid empty ACL warning)
    try:
        SQUID_BLOCKED_IPS.parent.mkdir(parents=True, exist_ok=True)
        ip_lines = [ip for ip in ips if ip] or ["0.0.0.0/32"]
        with open(SQUID_BLOCKED_IPS, "w", encoding="utf-8") as f:
            f.write("\n".join(ip_lines) + "\n")
    except Exception:
        pass

    # 3. Ensure squid.conf has the ACL rules
    ensure_squid_conf_acl()

    # 4. Trigger Squid reconfigure
    try:
        subprocess.run(["squid", "-k", "reconfigure"], capture_output=True, timeout=5)
    except Exception:
        pass


def ensure_squid_conf_acl():
    """Ensure Squid configuration includes blocked_domains and blocked_ips ACLs."""
    if not SQUID_CONF.exists():
        return
    try:
        with open(SQUID_CONF, "r", encoding="utf-8") as f:
            conf_text = f.read()

        if "acl blocked_domains" in conf_text:
            return

        acl_block = (
            'acl blocked_domains dstdomain "/data/data/com.termux/files/usr/etc/squid/blocked_domains.txt"\n'
            'acl blocked_ips dst "/data/data/com.termux/files/usr/etc/squid/blocked_ips.txt"\n'
            'http_access deny blocked_domains\n'
            'http_access deny blocked_ips\n'
        )

        if "http_access allow local_clients" in conf_text:
            new_conf = conf_text.replace(
                "http_access allow local_clients",
                acl_block + "http_access allow local_clients"
            )
            with open(SQUID_CONF, "w", encoding="utf-8") as f:
                f.write(new_conf)
    except Exception:
        pass


def add_block_target(target, target_type=None, reason=""):
    """Add a new target (domain, IP, or URL) to blocklist."""
    clean_target, detected_type = normalize_target(target)
    if not clean_target:
        return False, "เป้าหมาย (Domain/IP/URL) ไม่ถูกต้องหรือว่างเปล่า"

    final_type = target_type if target_type in ["domain", "ip"] else detected_type

    data = load_blocklist()
    items = data.get("items", [])

    # Check duplicate
    for it in items:
        it_clean, _ = normalize_target(it.get("target", ""))
        if it_clean == clean_target:
            if not it.get("enabled", True):
                it["enabled"] = True
                if reason:
                    it["reason"] = reason
                save_blocklist(data)
                return True, it
            return False, f"'{clean_target}' มีอยู่ในรายการบล็อคแล้ว"

    new_item = {
        "id": f"blk-{uuid.uuid4().hex[:8]}",
        "target": clean_target,
        "type": final_type,
        "reason": reason or "Administrative block",
        "created_at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "enabled": True
    }
    items.append(new_item)
    data["items"] = items

    save_blocklist(data)
    return True, new_item


def remove_block_target(target_or_id):
    """Remove a target by ID, exact URL, domain, or IP from blocklist."""
    raw = str(target_or_id or "").strip()
    if not raw:
        return False, "Target or ID cannot be empty"

    clean_target, _ = normalize_target(raw)
    data = load_blocklist()
    items = data.get("items", [])
    
    removed = None
    new_items = []
    for it in items:
        it_id = str(it.get("id", "")).strip()
        it_target = str(it.get("target", "")).strip()
        it_clean, _ = normalize_target(it_target)

        # Matching checks
        match_id = (it_id and it_id.lower() == raw.lower())
        match_exact = (it_target and it_target.lower() == raw.lower())
        match_clean = bool(clean_target and (it_clean == clean_target or it_target.lower() == clean_target))
        match_url = (len(raw) > 3 and (raw.lower() in it_target.lower() or it_target.lower() in raw.lower()))

        if not removed and (match_id or match_exact or match_clean or match_url):
            removed = it
        else:
            new_items.append(it)

    if not removed:
        # Check standalone arrays
        if clean_target and clean_target in [d.lower() for d in data.get("blocked_domains", [])]:
            data["blocked_domains"] = [d for d in data.get("blocked_domains", []) if d.lower() != clean_target]
            removed = {"id": "", "target": clean_target, "type": "domain", "enabled": False}
        elif raw in data.get("blocked_ips", []):
            data["blocked_ips"] = [ip for ip in data.get("blocked_ips", []) if ip != raw]
            removed = {"id": "", "target": raw, "type": "ip", "enabled": False}

    if not removed:
        return False, f"ไม่พบรายการ '{raw}' ในระบบ"

    data["items"] = new_items
    save_blocklist(data)
    return True, removed


def toggle_block_target(target_id):
    """Enable or disable a blocklist item."""
    raw = str(target_id or "").strip()
    if not raw:
        return False, "Target ID cannot be empty"

    clean_target, _ = normalize_target(raw)
    data = load_blocklist()
    items = data.get("items", [])
    found = None

    for it in items:
        it_id = str(it.get("id", "")).strip()
        it_target = str(it.get("target", "")).strip()
        it_clean, _ = normalize_target(it_target)

        if (it_id and it_id.lower() == raw.lower()) or (it_target and it_target.lower() == raw.lower()) or (clean_target and it_clean == clean_target):
            it["enabled"] = not it.get("enabled", True)
            found = it
            break

    if not found:
        return False, f"ไม่พบรายการ '{raw}' ในระบบ"

    save_blocklist(data)
    return True, found


def test_single_channel(target_url, mode="direct", timeout=5):
    """
    Test connectivity to target_url via specified mode:
    mode: 'direct', 'squid' (127.0.0.1:3128), 'socks5' (127.0.0.1:1080)
    """
    # Ensure URL has protocol
    if not target_url.startswith("http://") and not target_url.startswith("https://"):
        target_url = "https://" + target_url

    cmd = [
        "curl", "-s", "-S",
        "-o", "/dev/null",
        "-w", "%{http_code} %{time_total} %{remote_ip} %{time_connect} %{time_appconnect}",
        "--connect-timeout", str(timeout),
        "--max-time", str(timeout * 2)
    ]

    if mode == "squid":
        cmd.extend(["-x", "http://127.0.0.1:3128"])
    elif mode == "socks5":
        cmd.extend(["-x", "socks5h://127.0.0.1:1080"])

    cmd.append(target_url)

    t0 = time.perf_counter()
    try:
        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout * 2 + 2)
        duration_ms = round((time.perf_counter() - t0) * 1000, 1)

        stdout = proc.stdout.strip()
        stderr = proc.stderr.strip()

        if proc.returncode == 0 and stdout:
            parts = stdout.split()
            code = parts[0] if len(parts) > 0 else "000"
            remote_ip = parts[2] if len(parts) > 2 else "-"

            status_num = int(code) if code.isdigit() else 0

            if 200 <= status_num < 400:
                result_tag = "success"
                status_text = "เชื่อมต่อสำเร็จ (OK)"
            elif status_num == 403:
                result_tag = "blocked"
                status_text = "ถูกบล็อคโดยนโยบาย (Blocked/Forbidden)"
            elif status_num >= 400:
                result_tag = "warning"
                status_text = f"HTTP {status_num}"
            else:
                result_tag = "unknown"
                status_text = f"Status {code}"

            return {
                "ok": True,
                "status_code": status_num,
                "status_text": status_text,
                "result": result_tag,
                "latency_ms": duration_ms,
                "remote_ip": remote_ip,
                "error": None
            }
        else:
            # Analyze curl error
            err_lower = stderr.lower()
            if "403" in err_lower or "refused" in err_lower or "not allowed" in err_lower or "(2)" in err_lower:
                result_tag = "blocked"
                status_text = "ถูกบล็อคโดยนโยบายความปลอดภัย (Blocked / 403)"
            elif "timed out" in err_lower or "timeout" in err_lower:
                result_tag = "timeout"
                status_text = "หมดเวลาเชื่อมต่อ (Timeout)"
            elif "could not resolve host" in err_lower:
                result_tag = "error"
                status_text = "ไม่พบโดเมน (DNS Unresolved)"
            else:
                result_tag = "error"
                status_text = stderr[:60] if stderr else "Connection failed"

            return {
                "ok": False,
                "status_code": 403 if result_tag == "blocked" else 0,
                "status_text": status_text,
                "result": result_tag,
                "latency_ms": duration_ms,
                "remote_ip": "-",
                "error": stderr or "Unknown error"
            }
    except subprocess.TimeoutExpired:
        return {
            "ok": False,
            "status_code": 0,
            "status_text": "หมดเวลาเชื่อมต่อ (Timeout)",
            "result": "timeout",
            "latency_ms": timeout * 1000,
            "remote_ip": "-",
            "error": f"Operation timed out after {timeout}s"
        }
    except Exception as e:
        return {
            "ok": False,
            "status_code": 0,
            "status_text": str(e),
            "result": "error",
            "latency_ms": 0,
            "remote_ip": "-",
            "error": str(e)
        }


def test_all_proxies(target_url, timeout=5):
    """Run reachability test across Direct, Squid, and SOCKS5 concurrently."""
    import concurrent.futures

    results = {}
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as executor:
        f_direct = executor.submit(test_single_channel, target_url, "direct", timeout)
        f_squid = executor.submit(test_single_channel, target_url, "squid", timeout)
        f_socks5 = executor.submit(test_single_channel, target_url, "socks5", timeout)

        results["direct"] = f_direct.result()
        results["squid"] = f_squid.result()
        results["socks5"] = f_socks5.result()

    return {
        "ok": True,
        "target": target_url,
        "timestamp": int(time.time()),
        "time_str": time.strftime("%H:%M:%S"),
        "results": results
    }
