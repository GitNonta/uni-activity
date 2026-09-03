#!/usr/bin/env python3
"""Set up external S2 watchdog on S1 using adb + Termux UI injection."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=300):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] Install android-tools (adb) on S1")
print(run(
    "command -v adb >/dev/null && echo ALREADY-INSTALLED "
    "|| yes | pkg install -y android-tools 2>&1 | tail -3"
))
print()

print("[2] Start adb server")
print(run("adb start-server 2>&1 | tail -2; adb version | head -1"))
print()

watchdog_lines = [
    "#!/data/data/com.termux/files/usr/bin/bash",
    "# watch_s2.sh - External watchdog: revive S2 Termux cluster when it dies",
    "# Checks S2 web :8000 every 60s; if down, revives via WiFi ADB + keystrokes",
    'LOG="$HOME/s2-watchdog.log"',
    "",
    "log() { echo \"[$(date '+%F %T')] $*\" >> \"$LOG\"; }",
    "",
    "log \"watchdog started\"",
    "while true; do",
    "    if ! curl -s -o /dev/null --max-time 8 http://192.168.1.140:8000/health; then",
    "        log \"S2 DOWN - attempting revival\"",
    "        adb connect 192.168.1.140:5555 >> \"$LOG\" 2>&1",
    "        sleep 2",
    "        adb -s 192.168.1.140:5555 shell input keyevent KEYCODE_WAKEUP >> \"$LOG\" 2>&1",
    "        sleep 1",
    "        adb -s 192.168.1.140:5555 shell wm dismiss-keyguard >> \"$LOG\" 2>&1",
    "        sleep 2",
    "        adb -s 192.168.1.140:5555 shell am start -n com.termux/com.termux.app.TermuxActivity >> \"$LOG\" 2>&1",
    "        sleep 5",
    "        adb -s 192.168.1.140:5555 shell \\\"input text 'bash%s~/.termux/boot/start-cluster.sh%s>/dev/null%s2>%26%31%s%26'\\\" >> \"$LOG\" 2>&1",
    "        sleep 1",
    "        adb -s 192.168.1.140:5555 shell input keyevent 66 >> \"$LOG\" 2>&1",
    "        log \"revival keystrokes sent - waiting 90s for services\"",
    "        sleep 90",
    "    else",
    "        sleep 60",
    "    fi",
    "done",
]
body = "\n".join(watchdog_lines) + "\n"

sftp = ssh.open_sftp()
with sftp.open("/data/data/com.termux/files/home/watch_s2.sh", "w") as f:
    f.write(body)
sftp.close()
print("[3] watchdog script written")
print(run("chmod +x ~/watch_s2.sh && echo CHMOD-OK"))
print()

print("[4] Start watchdog detached")
print(run(
    "pgrep -f watch_s2.sh >/dev/null && echo ALREADY-RUNNING "
    "|| (setsid nohup bash ~/watch_s2.sh > /dev/null 2>&1 < /dev/null & sleep 2; pgrep -f watch_s2.sh && echo STARTED)"
))
print()

print("[5] Add to S1 boot script if missing")
print(run(
    "grep -q watch_s2 ~/.termux/boot/start-cluster.sh 2>/dev/null "
    "|| printf '\\n# External watchdog: revive S2 when it drops\\n"
    "if ! pgrep -f \"watch_s2[.]sh\" > /dev/null 2>&1; then\\n"
    "    setsid bash \"$HOME/watch_s2.sh\" > /dev/null 2>&1 < /dev/null &\\n"
    "fi\\n' >> ~/.termux/boot/start-cluster.sh; "
    "grep -c watch_s2 ~/.termux/boot/start-cluster.sh"
))
print()

print("[6] Watchdog log tail")
print(run("tail -5 ~/s2-watchdog.log 2>/dev/null || echo NO-LOG-YET"))

ssh.close()