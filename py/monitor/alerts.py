"""
monitor/alerts.py — get_alerts(), collect_stats(), fetch_public_ip_loop().
"""
import time, threading, json
import monitor.config as cfg
from monitor.telegram import tg_alert, tg_resolved, tg_daily_report
from monitor import collectors
from monitor.collectors import (
    get_uptime, get_server_info, get_cf_url, get_line_status,
    get_memory, get_load, get_temp, get_battery, get_disk,
    get_services, get_network, get_network_info, get_logs,
    get_deploy_logs, get_github_sync_logs_dict, get_github_events,
    get_ai_logs, get_active_sessions, get_sftp_active, get_scp_active,
    get_listening_ports, get_cpu_freqs, get_wifi_rssi, get_net_speeds,
    get_top_processes, get_postgres_stats, get_redis_stats,
    get_queue_stats, get_cloudflared_stats, get_gpu_stats,
)

def get_alerts(stats):
    # active_alert_ids lives in cfg (no global needed)
    alerts = []

    # 1. Cloudflare Connection Offline
    # ── กรอง: ไม่ alert ถ้า url_status ยังไม่มีข้อมูล หรืออยู่ในช่วง startup grace ──
    cf_st = stats.get("cf_status", {})
    cf_online  = cf_st.get("online", True)   # default True เพื่อไม่ false-alert ตอนเริ่ม
    cf_has_url = bool(cf_st.get("url", ""))  # ต้องมี URL ก่อนจึงจะ alert ได้
    in_grace   = (time.time() - cfg._monitor_start_time) < cfg.STARTUP_GRACE
    if not cf_online and cf_has_url and not in_grace:
        alerts.append({"id": "cf_offline", "type": "critical", "message": "Cloudflare Tunnel is Offline!"})
        
    # 2. Services Crash
    offline_services = []
    for svc, status in stats.get("services", {}).items():
        if status == "Stopped":
            offline_services.append(svc)
    if offline_services:
        alerts.append({"id": "service_crash", "type": "critical", "message": f"Service(s) Offline: {', '.join(offline_services)}"})
        
    # 3. High CPU Load
    load = stats.get("load", [0,0,0])[0]
    if load > 6.0:
        alerts.append({"id": "high_load", "type": "warning", "message": f"High CPU Load: {load}"})
        
    # 4. Overheating
    try:
        temp = float(stats.get("temp", 0))
        if temp > 75.0:
            alerts.append({"id": "high_temp", "type": "warning", "message": f"Server Overheating: {temp}°C"})
    except:
        pass
        
    # 5. High Memory Usage
    mem_percent = stats.get("memory", {}).get("percent", 0)
    if mem_percent > 90:
        alerts.append({"id": "high_mem", "type": "warning", "message": f"High Memory Usage: {mem_percent}%"})
        
    # 6. High Storage Usage
    disk_percent = stats.get("disk", {}).get("percent", 0)
    if disk_percent > 90:
        alerts.append({"id": "high_disk", "type": "warning", "message": f"Disk Space Low: {disk_percent}% used"})
        
    # 7. Abnormal Traffic Spike (Per IP)
    current_time = time.time()
    ip_counts = {}
    for log in cfg.inspector_logs:
        server_time = log.get("server_time", 0)
        if current_time - server_time <= 10:
            ip = log.get("ip", "unknown")
            ip_counts[ip] = ip_counts.get(ip, 0) + 1
            
    for ip, count in ip_counts.items():
        if count >= 40: # 40 requests in 10s from a single IP
            alerts.append({"id": f"traffic_spike_{ip}", "type": "warning", "message": f"Abnormal Traffic: {count} reqs in 10s from {ip}"})
            
    # Track history + Telegram alerts
    current_ids = set()
    from datetime import datetime
    for a in alerts:
        current_ids.add(a["id"])
        if a["id"] not in cfg.active_alert_ids:
            # บันทึก history
            history_item = a.copy()
            history_item["time"] = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            cfg.alerts_history.appendleft(history_item)
            # ส่ง Telegram เฉพาะ alert ใหม่
            tg_alert(a["id"], a["type"], a["message"])

    # แจ้ง resolved เมื่อ alert หายไป
    for resolved_id in (cfg.active_alert_ids - current_ids):
        cfg._tg_resolved.discard(resolved_id)   # reset เพื่อให้ส่งได้อีกถ้าเกิดซ้ำ
        resolved_msg = {
            "cf_offline"    : "Cloudflare Tunnel กลับมา Online แล้ว",
            "service_crash" : "Services กลับมา Running แล้ว",
            "high_load"     : "CPU Load กลับสู่ระดับปกติแล้ว",
            "high_temp"     : "อุณหภูมิ Server กลับสู่ระดับปกติแล้ว",
            "high_mem"      : "Memory Usage กลับสู่ระดับปกติแล้ว",
            "high_disk"     : "Disk Space กลับสู่ระดับปกติแล้ว",
        }.get(resolved_id, f"{resolved_id} resolved")
        tg_resolved(resolved_id, resolved_msg)

    cfg.active_alert_ids = current_ids
    return alerts

def collect_stats():
    stats = {
        "timestamp": int(time.time()),
        "uptime": get_uptime(),
        "server_info": get_server_info(),
        "cf_url": get_cf_url(),
        "cf_status": cfg.url_status,
        "speedtest": cfg.speedtest_data,
        "line_status": get_line_status(),
        "memory": get_memory(),
        "load": get_load(),
        "temp": get_temp(),
        "battery": get_battery(),
        "disk": get_disk(),
        "services": get_services(),
        "network": get_network(),
        "network_info": get_network_info(),
        "logs": get_logs(),
        "inspector": list(cfg.inspector_logs),
        "deploy_log": get_deploy_logs(),
        "github_deploy_logs": get_github_sync_logs_dict(),
        "events": get_github_events(),
        "ai_log": get_ai_logs(),
        "ssh_sessions": get_active_sessions(),
        "sftp_sessions": get_sftp_active(),
        "scp_sessions": get_scp_active(),
        "listening_ports": get_listening_ports(),
        "advanced_metrics": {
            "cpu_freqs": get_cpu_freqs(),
            "wifi_rssi": get_wifi_rssi(),
            "net_speeds": get_net_speeds(),
            "top_procs": get_top_processes(),
            "postgres": get_postgres_stats(),
            "redis": get_redis_stats(),
            "queue": get_queue_stats(),
            "cloudflared": get_cloudflared_stats(),
            "gpu": get_gpu_stats()
        },
        "public_ip": cfg.CACHED_PUBLIC_IP
    }
    stats["alerts"] = get_alerts(stats)
    stats["alerts_history"] = list(cfg.alerts_history)
    return stats



def fetch_public_ip_loop():
    import urllib.request
    while True:
        try:
            req = urllib.request.Request("https://api.ipify.org", headers={'User-Agent': 'Mozilla/5.0'})
            with urllib.request.urlopen(req, timeout=10) as response:
                ip = response.read().decode('utf-8').strip()
                if ip:
                    cfg.CACHED_PUBLIC_IP = ip
        except Exception:
            pass
        import time
        time.sleep(600)  # every 10 mins