import paramiko, time

HOST     = "192.168.1.222"
PORT     = 8022
USER     = "u0_a175"
PASSWORD = "2345678A"
APP      = "/data/data/com.termux/files/home/uni-activity"
TG_TOKEN = "7781954776:AAGvG1JdgrEiyCJ6GWyk9GOXSqyF6g7b1Gw"
TG_CHAT  = "8646407522"

def run(client, cmd, timeout=30):
    _, stdout, _ = client.exec_command(cmd, timeout=timeout)
    return stdout.read().decode(errors="replace").strip()

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

print("⏳ Upload monitor_server.py...")
sftp = client.open_sftp()
sftp.put(r"d:\projects\uni-activity\py\monitor_server.py",
         f"{APP}/py/monitor_server.py")
sftp.close()
print("✅ Upload สำเร็จ")

print("⏳ Restart monitor_server.py...")
run(client, "pkill -f 'python py/monitor_server.py' 2>/dev/null; sleep 2")
out = run(client,
    f"cd {APP} && TELEGRAM_BOT_TOKEN={TG_TOKEN} TELEGRAM_CHAT_ID={TG_CHAT} "
    f"nohup python py/monitor_server.py > storage/logs/monitor.log 2>&1 & echo $!")
print(f"✅ PID: {out}")
time.sleep(5)

pid = run(client, "pgrep -f 'python py/monitor_server.py'")
log = run(client, f"tail -3 {APP}/storage/logs/monitor.log 2>/dev/null")
print(f"Process: {'✅ Running' if pid else '❌ Not running'} (PID: {pid})")
print(f"Log: {log}")

# ทดสอบ /tunnel command โดยตรง
print("\n⏳ ทดสอบ Cloudflare status...")
test = run(client,
    f"cd {APP} && python3 -c \""
    f"import subprocess, json, urllib.request;"
    f"procs = subprocess.run(['pgrep','-a','cloudflared'], capture_output=True, text=True).stdout.strip();"
    f"cf_count = len([l for l in procs.splitlines() if l]);"
    f"payload = json.dumps({{'chat_id':'{TG_CHAT}','text':'🌐 Cloudflare Commands พร้อมแล้ว!\\n━━━━━━━━━━━━━━━━━━━━\\nProcesses: ' + str(cf_count) + ' running\\n\\nลองพิมพ์ /tunnel ได้เลยครับ','parse_mode':'HTML'}}).encode();"
    f"req = urllib.request.Request('https://api.telegram.org/bot{TG_TOKEN}/sendMessage', data=payload, headers={{'Content-Type':'application/json'}});"
    f"r = urllib.request.urlopen(req, timeout=10);"
    f"print('OK' if r.status==200 else 'FAIL')"
    f"\" 2>&1",
    timeout=15)
print(f"  → {test}")

client.close()
print("\n✅ Deploy Cloudflare commands สำเร็จ!")
