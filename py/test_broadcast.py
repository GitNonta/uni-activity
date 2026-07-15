import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A', timeout=10)

def run_cmd(cmd):
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode() + stderr.read().decode()

php_code = """
$user = App\Models\User::first();
$room = App\Models\ChatRoom::first();
if ($user && $room) {
    $msg = App\Models\ChatMessage::create([
        'chat_room_id' => $room->id,
        'user_id' => $user->id,
        'message' => 'test broadcast'
    ]);
    event(new App\Events\MessageSent($msg));
    echo "Broadcasted successfully.\\n";
} else {
    echo "No user or room.\\n";
}
"""

with open('tmp_tinker.php', 'w') as f:
    f.write(php_code)

import os
os.system("sshpass -p '2345678A' scp -P 8022 tmp_tinker.php u0_a175@192.168.1.222:/data/data/com.termux/files/home/uni-activity/tmp_tinker.php")

print(run_cmd("cd /data/data/com.termux/files/home/uni-activity && php artisan tinker tmp_tinker.php"))

client.close()
