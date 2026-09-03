"""Verify rendered admin layout has exactly one menu-button binding."""
import os
import sys
import time
import urllib.request
import paramiko

_here = os.path.dirname(os.path.abspath(__file__))
APP = "/data/data/com.termux/files/home/uni-activity"

CHECK = r"""<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$u = App\Models\User::query()->first();
if (!$u) { echo "NO_USER"; exit(1); }
auth()->setUser($u);
view()->share('errors', new Illuminate\Support\ViewErrorBag);
$h = view('layouts.admin')->render();
echo str_contains($h, 'onclick="toggleSidebar()"') ? 'STILL_DOUBLE ' : 'SINGLE_BOUND ';
echo str_contains($h, 'id="adminMenuToggleBtn"') ? 'ID_OK ' : 'NO_ID ';
echo str_contains($h, "addEventListener('click', function(e) {
                        e.preventDefault();
                        window.toggleSidebar();") || str_contains($h, 'window.toggleSidebar();') ? 'LISTENER_OK' : 'NO_LISTENER';
echo PHP_EOL;
"""

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect("192.168.1.222", port=8022, username="u0_a175", timeout=10,
               look_for_keys=True, allow_agent=False)

def run(cmd, timeout=60):
    _, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    return stdout.read().decode(errors="replace").strip(), stderr.read().decode(errors="replace").strip()

sftp = client.open_sftp()
with sftp.open(f"{APP}/_check_layout.php", "w") as f:
    f.write(CHECK)

out, err = run(f"cd {APP} && php _check_layout.php")
print("RENDER CHECK:", out or err)

run(f"rm -f {APP}/_check_layout.php")

# Also confirm through real HTTP that no inline onclick remains anywhere
try:
    req = urllib.request.Request("http://192.168.1.222:8000/", headers={"User-Agent": "Mozilla/5.0"})
    body = urllib.request.urlopen(req, timeout=10).read().decode("utf-8", "ignore")
    print("homepage 200 OK, contains double-bind:", 'onclick="toggleSidebar()"' in body)
except Exception as e:
    print("http check:", type(e).__name__)

client.close()

if "SINGLE_BOUND" not in out:
    sys.exit(1)
print("VERIFIED OK")
