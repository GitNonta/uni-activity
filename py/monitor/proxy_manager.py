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


def save_blocklist(data):
    """Save blocklist configuration and sync to Squid & SOCKS5."""
    data["updated_at"] = int(time.time())
    BLOCKLIST_JSON.parent.mkdir(parents=True, exist_ok=True)
    with open(BLOCKLIST_JSON, "w", encoding="utf-8") as f:
        json.dump(data, f, indent=2, ensure_ascii=False)
    sync_squid_files(data)


def sync_squid_files(data=None):
    """Sync blocklist entries to Squid text files and apply squid -k reconfigure."""
    if data is None:
        data = load_blocklist()

    enabled_items = [it for it in data.get("items", []) if it.get("enabled", True)]
    domains = [it["target"].strip().lower() for it in enabled_items if it.get("type") == "domain"]
    ips = [it["target"].strip() for it in enabled_items if it.get("type") == "ip"]

    # Also include standalone lists if any
    for d in data.get("blocked_domains", []):
        d = d.strip().lower()
        if d and d not in domains:
            domains.append(d)
    for ip in data.get("blocked_ips", []):
        ip = ip.strip()
        if ip and ip not in ips:
            ips.append(ip)

    # 1. Write Squid domain list (.domain matches domain and subdomains in squid dstdomain)
    try:
        SQUID_BLOCKED_DOMAINS.parent.mkdir(parents=True, exist_ok=True)
        lines = []
        for d in domains:
            d = d.strip().lower()
            if not d:
                continue
            dot_domain = f".{d.lstrip('.')}"
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
    """Add a new target (domain or IP) to blocklist."""
    target = target.strip().lower()
    if not target:
        return False, "Target cannot be empty"

    data = load_blocklist()
    items = data.get("items", [])

    # Check duplicate
    for it in items:
        if it["target"].lower() == target:
            return False, f"'{target}' is already in the blocklist"

    # Auto-detect type
    import re
    if target_type not in ["domain", "ip"]:
        if re.match(r"^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(/\d{1,2})?$", target):
            target_type = "ip"
        else:
            target_type = "domain"

    new_item = {
        "id": f"blk-{uuid.uuid4().hex[:8]}",
        "target": target,
        "type": target_type,
        "reason": reason or "Administrative block",
        "created_at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "enabled": True
    }
    items.append(new_item)

    if target_type == "domain" and target not in data["blocked_domains"]:
        data["blocked_domains"].append(target)
    elif target_type == "ip" and target not in data["blocked_ips"]:
        data["blocked_ips"].append(target)

    data["items"] = items
    save_blocklist(data)
    return True, new_item


def remove_block_target(target_or_id):
    """Remove a target by ID or domain/IP from blocklist."""
    target_or_id = target_or_id.strip()
    data = load_blocklist()
    items = data.get("items", [])
    
    removed = None
    new_items = []
    for it in items:
        if it.get("id") == target_or_id or it.get("target").lower() == target_or_id.lower():
            removed = it
        else:
            new_items.append(it)

    if not removed:
        return False, "Item not found"

    data["items"] = new_items
    tgt = removed["target"].lower()
    if tgt in data.get("blocked_domains", []):
        data["blocked_domains"].remove(tgt)
    if removed["target"] in data.get("blocked_ips", []):
        data["blocked_ips"].remove(removed["target"])

    save_blocklist(data)
    return True, removed


def toggle_block_target(target_id):
    """Enable or disable a blocklist item."""
    data = load_blocklist()
    items = data.get("items", [])
    found = None
    for it in items:
        if it.get("id") == target_id:
            it["enabled"] = not it.get("enabled", True)
            found = it
            break

    if not found:
        return False, "Item not found"

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
