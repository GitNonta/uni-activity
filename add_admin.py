import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

tinker_code = """
$u = App\\Models\\User::firstOrNew(['email' => 'nontawat2546.2546@gmail.com']);
$u->full_name = 'Nontawat Admin';
$u->password = Hash::make('Passwd1');
$u->role = 'admin';
$u->is_active = true;
$u->save();
echo "Admin account created successfully!\\n";
"""

cmd = f"cd {APP_DIR} && cat << 'EOF' | php artisan tinker\n{tinker_code}\nEOF\n"

stdin, stdout, stderr = client.exec_command(cmd)

print("--- Output ---")
print(stdout.read().decode())
print("--- Errors ---")
print(stderr.read().decode())

client.close()
