#!/usr/bin/env python3
"""
Hardware Metrics Exporter for Phone 1 (Termux)
Uses top/uptime/df commands (Termux blocks /proc/stat access)
Port: 9189
"""
import os
import re
import time
import subprocess
from http.server import HTTPServer, BaseHTTPRequestHandler

EXPORTER_PORT = 9189


def read_file(path, default=''):
    try:
        with open(path) as f:
            return f.read().strip()
    except:
        return default


def run_cmd(cmd, timeout=10):
    try:
        r = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout)
        return r.stdout.strip()
    except:
        return ''


def get_cpu_percent():
    """Parse CPU from top -bn1."""
    out = run_cmd(['top', '-bn1'])
    for line in out.split('\n'):
        line = line.replace('\r', '')  # Strip carriage returns
        if '%cpu' in line.lower() and 'idle' in line.lower():
            # "800%cpu   0%user   0%nice   0%sys 800%idle   0%iow   0%irq   0%sirq   0%host"
            m_idle = re.search(r'(\d+)%idle', line)
            m_total = re.search(r'(\d+)%cpu', line)
            if m_idle and m_total:
                idle_pct = int(m_idle.group(1))
                total_pct = int(m_total.group(1))
                if total_pct > 0:
                    return round((1.0 - idle_pct / total_pct) * 100, 1)
    return 0.0


def get_load_avg():
    """Parse load average from uptime."""
    out = run_cmd(['uptime'])
    m = re.search(r'load average:\s*([\d.]+),\s*([\d.]+),\s*([\d.]+)', out)
    if m:
        return float(m.group(1)), float(m.group(2)), float(m.group(3))
    return 0, 0, 0


def get_cpu_cores():
    """Get CPU core count from top."""
    out = run_cmd(['top', '-bn1'])
    for line in out.split('\n'):
        line = line.replace('\r', '')
        if '%cpu' in line.lower() and 'idle' in line.lower():
            m = re.search(r'(\d+)%cpu', line)
            if m:
                return int(m.group(1)) // 100
    return 1


def read_meminfo():
    info = {}
    try:
        with open('/proc/meminfo') as f:
            for line in f:
                parts = line.split()
                key = parts[0].rstrip(':')
                val = int(parts[1]) * 1024
                info[key] = val
    except:
        pass
    return info


def get_disk_usage():
    """Parse disk from df /data."""
    out = run_cmd(['df', '/data'])
    for line in out.split('\n'):
        if '/data' in line:
            parts = line.split()
            if len(parts) >= 4:
                total = int(parts[1]) * 1024
                used = int(parts[2]) * 1024
                avail = int(parts[3]) * 1024
                return total, used, avail
    return 0, 0, 0


def get_temperatures():
    temps = {}
    try:
        for zone_dir in sorted(os.listdir('/sys/class/thermal/')):
            if zone_dir.startswith('thermal_zone'):
                zone_path = f'/sys/class/thermal/{zone_dir}'
                zone_type = read_file(f'{zone_path}/type', zone_dir)
                zone_temp = read_file(f'{zone_path}/temp', '0')
                try:
                    temp_val = int(zone_temp)
                    if temp_val > 100:
                        temps[zone_type] = temp_val
                except:
                    pass
    except:
        pass
    return temps


def get_network_stats():
    stats = {}
    try:
        with open('/proc/net/dev') as f:
            for line in f:
                if 'wlan0' in line:
                    parts = line.split()
                    stats['rx_bytes'] = int(parts[1])
                    stats['rx_packets'] = int(parts[2])
                    stats['tx_bytes'] = int(parts[9])
                    stats['tx_packets'] = int(parts[10])
                    break
    except:
        pass
    return stats


def get_process_stats():
    try:
        result = subprocess.run(['ps', 'aux'], capture_output=True, text=True, timeout=5)
        lines = result.stdout.strip().split('\n')
        total = len(lines) - 1
        services = {}
        for line in lines[1:]:
            parts = line.split(None, 10)
            if len(parts) >= 11:
                cmd = parts[10].split()[0] if parts[10].split() else 'unknown'
                for short in ['postgres', 'python3', 'nginx', 'squid', 'pgbouncer', 'node', 'svlogd', 'runsv', 'sshd', 'cron', 'microsocks', 'grafana', 'prometheus', 'java']:
                    if short in cmd.lower():
                        cmd = short
                        break
                services[cmd] = services.get(cmd, 0) + 1
        return total, services
    except:
        return 0, {}


def get_uptime():
    """Get uptime in seconds from uptime command."""
    try:
        with open('/proc/uptime') as f:
            return float(f.read().split()[0])
    except:
        pass
    # Fallback: parse uptime -p output
    out = run_cmd(['uptime', '-p'])
    # 'up 2 weeks, 4 days, 11 hours, 51 minutes'
    total = 0
    m = re.search(r'(\d+)\s*week', out)
    if m: total += int(m.group(1)) * 7 * 86400
    m = re.search(r'(\d+)\s*day', out)
    if m: total += int(m.group(1)) * 86400
    m = re.search(r'(\d+)\s*hour', out)
    if m: total += int(m.group(1)) * 3600
    m = re.search(r'(\d+)\s*minute', out)
    if m: total += int(m.group(1)) * 60
    return total


def get_fd_usage():
    try:
        pid = subprocess.check_output(['pgrep', '-o', 'python3'], text=True).strip()
        fds = len(os.listdir(f'/proc/{pid}/fd'))
        limits = read_file(f'/proc/{pid}/limits')
        max_fds = 1024
        for line in limits.split('\n'):
            if 'open files' in line:
                max_fds = int(line.split()[3])
                break
        return fds, max_fds
    except:
        return 0, 1024


def collect_metrics():
    lines = []

    # ── CPU ──
    lines.append(f'hw_cpu_usage_percent {get_cpu_percent()}')
    lines.append(f'hw_cpu_cores {get_cpu_cores()}')

    # ── CPU Frequency per core ──
    max_freq = 0
    for i in range(16):
        freq = read_file(f'/sys/devices/system/cpu/cpu{i}/cpufreq/scaling_cur_freq')
        if freq:
            try:
                freq_khz = int(freq)
                lines.append(f'hw_cpu_frequency_hz{{core="{i}"}} {freq_khz * 1000}')
                if freq_khz > max_freq:
                    max_freq = freq_khz
            except:
                pass
    if max_freq > 0:
        lines.append(f'hw_cpu_max_frequency_hz {max_freq * 1000}')

    gov = read_file('/sys/devices/system/cpu/cpu0/cpufreq/scaling_governor')
    if gov:
        lines.append(f'hw_cpu_governor{{governor="{gov}"}} 1')

    # ── Load Average ──
    l1, l5, l15 = get_load_avg()
    lines.append(f'hw_load_avg_1m {l1}')
    lines.append(f'hw_load_avg_5m {l5}')
    lines.append(f'hw_load_avg_15m {l15}')

    # ── Memory ──
    mem = read_meminfo()
    total_mem = mem.get('MemTotal', 0)
    available_mem = mem.get('MemAvailable', 0)
    free_mem = mem.get('MemFree', 0)
    buffers = mem.get('Buffers', 0)
    cached = mem.get('Cached', 0)
    swap_total = mem.get('SwapTotal', 0)
    swap_free = mem.get('SwapFree', 0)
    used_mem = total_mem - available_mem

    lines.append(f'hw_memory_total_bytes {total_mem}')
    lines.append(f'hw_memory_used_bytes {used_mem}')
    lines.append(f'hw_memory_available_bytes {available_mem}')
    lines.append(f'hw_memory_free_bytes {free_mem}')
    lines.append(f'hw_memory_buffers_bytes {buffers}')
    lines.append(f'hw_memory_cached_bytes {cached}')
    lines.append(f'hw_memory_usage_percent {round(used_mem / total_mem * 100, 1) if total_mem > 0 else 0}')
    lines.append(f'hw_swap_total_bytes {swap_total}')
    lines.append(f'hw_swap_used_bytes {swap_total - swap_free}')
    lines.append(f'hw_swap_usage_percent {round((swap_total - swap_free) / swap_total * 100, 1) if swap_total > 0 else 0}')

    # ── Disk ──
    disk_total, disk_used, disk_avail = get_disk_usage()
    lines.append(f'hw_disk_total_bytes {disk_total}')
    lines.append(f'hw_disk_used_bytes {disk_used}')
    lines.append(f'hw_disk_available_bytes {disk_avail}')
    lines.append(f'hw_disk_usage_percent {round(disk_used / disk_total * 100, 1) if disk_total > 0 else 0}')

    # ── Temperature ──
    temps = get_temperatures()
    key_temps = {
        'xo-therm-adc': 'board', 'xo-therm-buf-adc': 'board_buf',
        'pm8953_tz': 'pmic', 'battery': 'battery',
    }
    for zone_type, temp_val in temps.items():
        label = key_temps.get(zone_type, zone_type)
        lines.append(f'hw_temperature_celsius{{zone="{label}"}} {temp_val / 1000:.1f}')

    cpu_temps = {k: v for k, v in temps.items() if 'cpu0' in k.lower() and 'usr' in k.lower()}
    if cpu_temps:
        lines.append(f'hw_cpu_temperature_celsius {sum(cpu_temps.values()) / len(cpu_temps) / 1000:.1f}')

    gpu_temps = {k: v for k, v in temps.items() if 'gpu' in k.lower() and 'usr' in k.lower()}
    if gpu_temps:
        lines.append(f'hw_gpu_temperature_celsius {sum(gpu_temps.values()) / len(gpu_temps) / 1000:.1f}')

    battery_temps = {k: v for k, v in temps.items() if k in ('battery', 'bk_battery', 'Battery')}
    if battery_temps:
        bt = sum(battery_temps.values()) / len(battery_temps)
        lines.append(f'hw_battery_temperature_celsius {bt / 1000:.1f}')

    # ── Network ──
    net = get_network_stats()
    if net:
        lines.append(f'hw_network_rx_bytes {net["rx_bytes"]}')
        lines.append(f'hw_network_tx_bytes {net["tx_bytes"]}')
        lines.append(f'hw_network_rx_packets {net["rx_packets"]}')
        lines.append(f'hw_network_tx_packets {net["tx_packets"]}')

    # ── Processes ──
    total_procs, services = get_process_stats()
    lines.append(f'hw_process_count {total_procs}')
    for svc, count in services.items():
        lines.append(f'hw_service_processes{{service="{svc}"}} {count}')

    # ── File Descriptors ──
    fd_used, fd_max = get_fd_usage()
    lines.append(f'hw_fd_used {fd_used}')
    lines.append(f'hw_fd_limit {fd_max}')
    lines.append(f'hw_fd_usage_percent {round(fd_used / fd_max * 100, 1) if fd_max > 0 else 0}')

    # ── Uptime ──
    lines.append(f'hw_uptime_seconds {get_uptime()}')
    lines.append('hw_up 1')

    return '\n'.join(lines)


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path == '/metrics':
            metrics = collect_metrics()
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')
            self.end_headers()
            self.wfile.write(metrics.encode())
        elif self.path == '/health':
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain')
            self.end_headers()
            self.wfile.write(b'OK')
        else:
            self.send_response(404)
            self.end_headers()

    def log_message(self, fmt, *args):
        pass


if __name__ == '__main__':
    print(f'Hardware exporter starting on port {EXPORTER_PORT}', flush=True)
    server = HTTPServer(('0.0.0.0', EXPORTER_PORT), Handler)
    print(f'Listening on http://0.0.0.0:{EXPORTER_PORT}/metrics', flush=True)
    server.serve_forever()
