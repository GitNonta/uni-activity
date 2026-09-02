#!/usr/bin/env python3
"""
Hardware Metrics Exporter for Phone 1 (Termux)
Exposes CPU, RAM, disk, temperature, battery, network metrics
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


def read_proc_stat():
    """Read CPU times from /proc/stat."""
    try:
        with open('/proc/stat') as f:
            line = f.readline()
        parts = line.split()
        # user, nice, system, idle, iowait, irq, softirq, steal
        vals = [int(x) for x in parts[1:9]]
        idle = vals[3] + vals[4]
        total = sum(vals)
        return idle, total
    except:
        return 0, 0


def calc_cpu_percent(interval=1):
    """Calculate CPU usage over interval."""
    idle1, total1 = read_proc_stat()
    time.sleep(interval)
    idle2, total2 = read_proc_stat()
    idle_delta = idle2 - idle1
    total_delta = total2 - total1
    if total_delta == 0:
        return 0.0
    return round((1.0 - idle_delta / total_delta) * 100, 1)


def read_meminfo():
    """Read memory info from /proc/meminfo."""
    info = {}
    try:
        with open('/proc/meminfo') as f:
            for line in f:
                parts = line.split()
                key = parts[0].rstrip(':')
                val = int(parts[1]) * 1024  # Convert kB to bytes
                info[key] = val
    except:
        pass
    return info


def get_disk_usage():
    """Get disk usage for /data."""
    try:
        result = subprocess.run(['df', '-B1', '/data'], capture_output=True, text=True, timeout=5)
        lines = result.stdout.strip().split('\n')
        if len(lines) >= 2:
            parts = lines[1].split()
            total = int(parts[1])
            used = int(parts[2])
            avail = int(parts[3])
            return total, used, avail
    except:
        pass
    return 0, 0, 0


def get_temperatures():
    """Read thermal zones."""
    temps = {}
    try:
        for zone_dir in os.listdir('/sys/class/thermal/'):
            if zone_dir.startswith('thermal_zone'):
                zone_path = f'/sys/class/thermal/{zone_dir}'
                zone_type = read_file(f'{zone_path}/type', zone_dir)
                zone_temp = read_file(f'{zone_path}/temp', '0')
                try:
                    temp_val = int(zone_temp)
                    if temp_val > 0:
                        temps[zone_type] = temp_val
                except:
                    pass
    except:
        pass
    return temps


def get_battery():
    """Get battery info."""
    battery = {}
    try:
        for item in ['capacity', 'status', 'voltage_now', 'current_now', 'temp', 'health']:
            val = read_file(f'/sys/class/power_supply/battery/{item}')
            if val:
                battery[item] = val
    except:
        pass
    # Fallback: check pmu-vbat-lvl0 for voltage
    if 'voltage_now' not in battery:
        vbat = read_file('/sys/class/thermal/thermal_zone35/temp')
        if vbat:
            try:
                battery['voltage_now'] = str(int(vbat) * 1000)  # Convert mV to uV
            except:
                pass
    return battery


def get_network_stats():
    """Read network stats from /proc/net/dev."""
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
    """Get process count and top processes."""
    try:
        result = subprocess.run(['ps', 'aux'], capture_output=True, text=True, timeout=5)
        lines = result.stdout.strip().split('\n')
        total = len(lines) - 1  # Exclude header

        # Count by service
        services = {}
        for line in lines[1:]:
            parts = line.split(None, 10)
            if len(parts) >= 11:
                cmd = parts[10].split()[0] if parts[10].split() else 'unknown'
                # Simplify command name
                for short in ['postgres', 'python3', 'nginx', 'squid', 'pgbouncer', 'node', 'svlogd', 'runsv', 'sshd', 'cron', 'microsocks', 'grafana', 'prometheus']:
                    if short in cmd.lower():
                        cmd = short
                        break
                services[cmd] = services.get(cmd, 0) + 1
        return total, services
    except:
        return 0, {}


def get_load_avg():
    """Get load average."""
    try:
        with open('/proc/loadavg') as f:
            parts = f.read().split()
            return float(parts[0]), float(parts[1]), float(parts[2])
    except:
        return 0, 0, 0


def get_uptime():
    """Get uptime in seconds."""
    try:
        with open('/proc/uptime') as f:
            return float(f.read().split()[0])
    except:
        return 0


def get_fd_usage():
    """Get file descriptor usage for a process."""
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
    now = time.time()

    # ── CPU Usage (quick 1s sample) ──
    cpu_pct = calc_cpu_percent(1)
    lines.append(f'hw_cpu_usage_percent {cpu_pct}')

    # ── Load Average ──
    load1, load5, load15 = get_load_avg()
    lines.append(f'hw_load_avg_1m {load1}')
    lines.append(f'hw_load_avg_5m {load5}')
    lines.append(f'hw_load_avg_15m {load15}')

    # ── CPU cores ──
    try:
        with open('/proc/cpuinfo') as f:
            cores = f.read().count('processor')
        lines.append(f'hw_cpu_cores {cores}')
    except:
        pass

    # ── Memory ──
    mem = read_meminfo()
    total_mem = mem.get('MemTotal', 0)
    free_mem = mem.get('MemFree', 0)
    available_mem = mem.get('MemAvailable', 0)
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

    # ── Disk ──
    disk_total, disk_used, disk_avail = get_disk_usage()
    lines.append(f'hw_disk_total_bytes {disk_total}')
    lines.append(f'hw_disk_used_bytes {disk_used}')
    lines.append(f'hw_disk_available_bytes {disk_avail}')
    lines.append(f'hw_disk_usage_percent {round(disk_used / disk_total * 100, 1) if disk_total > 0 else 0}')

    # ── Temperature ──
    temps = get_temperatures()
    key_temps = {
        'xo-therm-adc': 'board',
        'xo-therm-buf-adc': 'board_buf',
        'pm8953_tz': 'pmic',
        'battery': 'battery',
        'Battery': 'battery_alt',
        'pa-therm0': 'pa',
    }
    for zone_type, temp_val in temps.items():
        label = key_temps.get(zone_type, zone_type)
        # Values are in millidegrees
        lines.append(f'hw_temperature_celsius{{zone="{label}"}} {temp_val / 1000:.1f}')

    # CPU-specific temps
    cpu_temps = {k: v for k, v in temps.items() if 'cpu' in k.lower()}
    if cpu_temps:
        avg_cpu_temp = sum(cpu_temps.values()) / len(cpu_temps)
        lines.append(f'hw_cpu_temperature_celsius {avg_cpu_temp / 1000:.1f}')

    gpu_temps = {k: v for k, v in temps.items() if 'gpu' in k.lower()}
    if gpu_temps:
        avg_gpu_temp = sum(gpu_temps.values()) / len(gpu_temps)
        lines.append(f'hw_gpu_temperature_celsius {avg_gpu_temp / 1000:.1f}')

    # ── Battery ──
    battery = get_battery()
    if 'capacity' in battery:
        lines.append(f'hw_battery_percent {battery["capacity"]}')
    if 'voltage_now' in battery:
        try:
            lines.append(f'hw_battery_voltage_uv {int(battery["voltage_now"])}')
        except:
            pass
    if 'status' in battery:
        status_map = {'Charging': 1, 'Discharging': 0, 'Full': 2, 'Not charging': 3}
        lines.append(f'hw_battery_status {status_map.get(battery["status"], -1)}')

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
    uptime = get_uptime()
    lines.append(f'hw_uptime_seconds {uptime}')

    # ── Up ──
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
