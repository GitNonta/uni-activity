import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

def run_cmd(cmd):
    print(f">>> {cmd}")
    stdin, stdout, stderr = client.exec_command(cmd)
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    if err:
        print(f"Error: {err}")
    return out

print("--- 4. Running Seeder via Tinker ---")
tinker_code = """
$admin = App\\Models\\User::where('email', 'nontawat2546.2546@gmail.com')->first();
if (!$admin) { echo "Admin not found!\\n"; exit; }
$categories = App\\Models\\ActivityCategory::pluck('id')->toArray();
if (empty($categories)) {
    $cat = App\\Models\\ActivityCategory::create(['name' => 'ทั่วไป', 'points' => 10, 'is_active' => true]);
    $categories[] = $cat->id;
}
$names = ['สัมมนาวิชาการ', 'ค่ายอาสา', 'อบรมภาวะผู้นำ', 'กีฬาสี', 'ประกวดนวัตกรรม', 'พัฒนาทักษะไอที', 'อนุรักษ์สิ่งแวดล้อม', 'เตรียมความพร้อมสหกิจ', 'เทศกาลดนตรี', 'จิตอาสาพัฒนาชุมชน'];
$count = 0;
for ($i = 0; $i < 10; $i++) {
    $date = now()->addDays(rand(1, 30));
    $startTime = $date->copy()->setHour(rand(8, 14))->setMinute(0);
    $endTime = $startTime->copy()->addHours(rand(2, 4));
    App\\Models\\Activity::create([
        'title' => 'กิจกรรม: ' . $names[$i],
        'description' => 'กิจกรรมสำหรับให้นักศึกษาเข้าร่วมเพื่อเก็บชั่วโมง สะสมพอร์ตโฟลิโอ',
        'location' => 'ห้องประชุม ' . rand(101, 505),
        'activity_date' => $date->format('Y-m-d'),
        'start_time' => $startTime->format('H:i:s'),
        'end_time' => $endTime->format('H:i:s'),
        'activity_hours' => $startTime->diffInHours($endTime),
        'max_participants' => rand(50, 200),
        'register_open_at' => now(),
        'register_close_at' => $date->copy()->subDays(1),
        'checkin_open_at' => $startTime->copy()->subMinutes(30),
        'checkin_close_at' => $endTime,
        'is_mandatory' => rand(0, 1) == 1,
        'category_id' => $categories[array_rand($categories)],
        'created_by' => $admin->id,
        'image_path' => 'activities/activity_' . ($i + 1) . '.jpg',
        'status' => 'upcoming',
        'scope' => 'university',
        'require_attendance_approval' => false,
    ]);
    $count++;
}
echo "Created " . $count . " activities!\\n";
"""

cmd = f"cd {APP_DIR} && cat << 'EOF' | php artisan tinker\n{tinker_code}\nEOF\n"
out = run_cmd(cmd)
print("--- Tinker Output ---")
print(out)

client.close()
