#!/usr/bin/env python3
"""Fix quoting bug in S1's watch_s2.sh and restart it."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)

watchdog_lines = [
    "#!/data/data/com.termux/files/usr/bin/bash",
    "# watch_s2.sh - External watchdog: revive S2 Termux cluster when it dies",
    "# Checks S2 web :8000 every 60s; if down, revives via WiFi ADB + keystrokes",
    'LOG="$HOME/s2-watchdog.log"',
    "",
    'log() { echo "[$(date \'+%F %T\')] $*" >> "$LOG"; }',
    "",
    'log "watchdog started (v2)"',
    "while true; do",
    "    if ! curl -s -o /dev/null --max-time 8 http://192.168.1.140:8000/health; then",
    '        log "S2 DOWN - attempting revival"',
    '        adb connect 192.168.1.140:5555 >> "$LOG" 2>&1',
    "        sleep 2",
    '        adb -s 192.168.1.140:5555 shell input keyevent KEYCODE_WAKEUP >> "$LOG" 2>&1',
    "        sleep 1",
    '        adb -s 192.168.1.140:5555 shell wm dismiss-keyguard >> "$LOG" 2>&1',
    "        sleep 2",
    '        adb -s 192.168.1.140:5555 shell am start -n com.termux/com.termux.app.TermuxActivity >> "$LOG" 2>&1',
    "        sleep 6",
    '        adb -s 192.168.1.140:5555 shell "input text \'bash%s~/.termux/boot/start-cluster.sh%s>/dev/null%s2>%26%31%s%26\'" >> "$LOG" 2>&1',
    "        sleep 1",
    '        adb -s 192.168.1.140:5555 shell input keyevent 66 >> "$LOG" 2>&1',
    '        log "revival keystrokes sent - waiting 90s for services"',
    "        sleep 90",
    "    else",
    "        sleep 60",
    "    fi",
    "done",
]
body = "\n".join(watchdog_lines) + "\n"

# Stop old watchdog
_, o, _ = ssh.exec_command("pkill -f 'watch_s2[.]sh'; sleep 1; echo KILLED", timeout=15)
print("[1] Stop old:", o.read().decode().strip())

# Write fixed script
sftp = ssh.open_sftp()
with sftp.open("/data/data/com.termux/files/home/watch_s2.sh", "w") as f:
    f.write(body)
sftp.close()
_, o, _ = ssh.exec_command("chmod +x ~/watch_s2.sh && echo WRITTEN", timeout=15)
print("[2] Write:", o.read().decode().strip())

# Restart
_, o, _ = ssh.exec_command(
    "setsid nohup bash ~/watch_s2.sh > /dev/null 2>&1 < /dev/null & sleep 2; "
    "ps aux 2>/dev/null | grep 'bash.*watch_s2[.]sh' | grep -v grep && echo RUNNING", timeout=20)
print("[3] Restart:", o.read().decode().strip())

ssh.close()