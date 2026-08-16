#!/usr/bin/env python3
"""
Uni-Activity Self-Healing Watchdog (runs ON server/Termux)
ตรวจสอบ Reverb, Queue Worker, Nginx, PHP-FPM ทุก 30 วิ
และ restart อัตโนมัติเมื่อล่ม
"""
import os
import time
import subprocess
import logging
from datetime import datetime

APP = '/data/data/com.termux/files/home/uni-activity'
LOG_FILE = f'{APP}/storage/logs/watchdog.log'

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
    handlers=[
        logging.FileHandler(LOG_FILE),
        logging.StreamHandler(),
    ]
)
log = logging.getLogger('watchdog')

CHECK_INTERVAL = 30  # seconds
last_restart = {}
COOLDOWN_MINS = 5


def can_restart(name):
    t = last_restart.get(name, 0)
    return (time.time() - t) > (COOLDOWN_MINS * 60)


def shell(cmd):
    try:
        r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=15)
        return r.stdout.strip(), r.stderr.strip()
    except Exception as e:
        return '', str(e)


# --- Checks ---
def is_redis_ok():
    out, _ = shell('redis-cli ping 2>/dev/null')
    return out == 'PONG'

def is_nginx_ok():
    out, _ = shell("netstat -tlnp 2>/dev/null | grep ':8080 '")
    return bool(out)

def is_phpfpm_ok():
    out, _ = shell("pgrep -f 'php-fpm: master'")
    return bool(out)

def is_octane_ok():
    out, _ = shell("pgrep -f 'octane:start' || netstat -tlnp 2>/dev/null | grep ':8000 '")
    return bool(out)

def is_queue_ok():
    out, _ = shell("pgrep -f 'artisan queue:work'")
    return bool(out)

def is_reverb_ok():
    out, _ = shell("netstat -tlnp 2>/dev/null | grep ':8082 '")
    return bool(out)

def is_cloudflared_ok():
    out, _ = shell("pgrep -f 'cloudflared tunnel'")
    return bool(out)


# --- Restarts ---
def restart_redis():
    log.warning('RESTART: Redis')
    shell('pkill -9 redis-server ; sleep 1')
    shell(f'nohup redis-server </dev/null >{APP}/storage/logs/redis.log 2>&1 &')
    time.sleep(4)
    return is_redis_ok()

def restart_nginx():
    log.warning('RESTART: Nginx')
    shell("pkill -9 -f 'nginx: master' ; sleep 1")
    shell('nginx 2>&1')
    time.sleep(3)
    return is_nginx_ok()

def restart_phpfpm():
    log.warning('RESTART: PHP-FPM')
    shell('pkill -9 -f php-fpm ; sleep 2')
    shell('nohup php-fpm </dev/null >/dev/null 2>&1 &')
    time.sleep(4)
    return is_phpfpm_ok()

def restart_queue():
    log.warning('RESTART: Queue Worker')
    shell("pkill -9 -f 'artisan queue:work' ; sleep 1")
    cmd = (
        f'nohup php {APP}/artisan queue:work redis '
        f'--queue=line-notifications,default '
        f'--tries=3 --sleep=3 --max-time=3600 '
        f'</dev/null >{APP}/storage/logs/queue.log 2>&1 &'
    )
    shell(f'cd {APP} && {cmd}')
    time.sleep(4)
    return is_queue_ok()

def restart_reverb():
    log.warning('RESTART: Reverb WebSocket')
    shell("pkill -9 -f 'artisan reverb:start' ; sleep 1")
    cmd = (
        f'nohup php {APP}/artisan reverb:start '
        f'--host=0.0.0.0 --port=8082 '
        f'</dev/null >{APP}/storage/logs/reverb.log 2>&1 &'
    )
    shell(f'cd {APP} && {cmd}')
    time.sleep(5)
    return is_reverb_ok()

def restart_cloudflared():
    import re
    log.warning('RESTART: Cloudflared Tunnel')
    shell(f'rm -f {APP}/cloudflared.log')
    shell("pkill -9 -f 'cloudflared tunnel' ; sleep 1")
    cmd = (
        'cloudflared tunnel --url http://127.0.0.1:8080 '
        '--no-autoupdate '
        f'</dev/null >{APP}/cloudflared.log 2>&1 &'
    )
    shell(f'nohup {cmd}')
    # Wait for URL
    for _ in range(10):
        time.sleep(3)
        log_txt, _ = shell(f'cat {APP}/cloudflared.log')
        m = re.search(r'https://[a-z0-9-]+\.trycloudflare\.com', log_txt)
        if m:
            new_url = m.group(0)
            log.info(f'  New tunnel URL: {new_url}')
            shell(f"sed -i 's|APP_URL=.*|APP_URL={new_url}|g' {APP}/.env")
            shell(f"python {APP}/py/start_cf_ubuntu.py &")
            break
    return is_cloudflared_ok()


def is_web_engine_ok():
    return is_octane_ok() or is_phpfpm_ok()

def restart_web_engine():
    if is_octane_ok() or os.path.exists(f'{APP}/frankenphp'):
        log.warning('RESTART: Octane Web Engine')
        shell("pkill -9 -f 'octane:start' ; sleep 1")
        shell(f"cd {APP} && nohup php artisan octane:start --host=0.0.0.0 --port=8000 </dev/null >{APP}/storage/logs/octane.log 2>&1 &")
        time.sleep(4)
        return is_octane_ok()
    return restart_phpfpm()

SERVICES = [
    {'name': 'Redis',       'check': is_redis_ok,       'restart': restart_redis,       'cascade': ['Queue', 'Reverb']},
    {'name': 'Nginx',       'check': is_nginx_ok,        'restart': restart_nginx,       'cascade': []},
    {'name': 'WebEngine',   'check': is_web_engine_ok,   'restart': restart_web_engine,  'cascade': []},
    {'name': 'Queue',       'check': is_queue_ok,        'restart': restart_queue,       'cascade': []},
    {'name': 'Reverb',      'check': is_reverb_ok,       'restart': restart_reverb,      'cascade': []},
    {'name': 'Cloudflared', 'check': is_cloudflared_ok,  'restart': restart_cloudflared, 'cascade': []},
]

def run_cycle(round_num):
    results = {}
    force = set()
    for svc in SERVICES:
        name = svc['name']
        ok = svc['check']()
        results[name] = ok
        if not ok:
            log.warning(f'[DOWN] {name}')
            if can_restart(name) or name in force:
                success = svc['restart']()
                last_restart[name] = time.time()
                results[name] = success
                log.info(f'  {"✅ Recovered" if success else "❌ Still DOWN"}: {name}')
                if success:
                    for dep in svc.get('cascade', []):
                        force.add(dep)
            else:
                mins = COOLDOWN_MINS - int((time.time() - last_restart.get(name, 0)) / 60)
                log.warning(f'  Cooldown {mins}m left for {name}')
    summary = ' | '.join(f'{"✅" if v else "❌"} {k}' for k, v in results.items())
    log.info(f'[Round {round_num}] {summary}')
    return results


if __name__ == '__main__':
    log.info('=' * 60)
    log.info('  Uni-Activity ON-SERVER Watchdog STARTED')
    log.info(f'  Interval: {CHECK_INTERVAL}s | Cooldown: {COOLDOWN_MINS}m')
    log.info(f'  Log: {LOG_FILE}')
    log.info('=' * 60)
    n = 0
    while True:
        n += 1
        try:
            run_cycle(n)
        except Exception as e:
            log.error(f'Cycle error: {e}')
        time.sleep(CHECK_INTERVAL)
