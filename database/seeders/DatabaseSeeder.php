<?php
declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeder หลักของระบบ
 * สร้างข้อมูลตัวอย่าง: ผู้ใช้, หมวดหมู่, กิจกรรม, การลงทะเบียน, การเข้าร่วม
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Truncate related tables to avoid duplicate constraints
        DB::statement('TRUNCATE TABLE users, registrations, attendances, activities, activity_categories, activity_feedbacks, rooms, room_user RESTART IDENTITY CASCADE');

        // Ensure admin user exists
        $this->call(AdminUserSeeder::class);

        // Reference admin for created_by fields
        $admin = User::where('email', 'nontawat2546.2546@gmail.com')->first();

        // ---------- สร้างนักศึกษา ----------
        $students = collect();
        $faculties = ['วิศวกรรมศาสตร์','วิทยาศาสตร์','บริหารธุรกิจ','มนุษยศาสตร์','ศึกษาศาสตร์','นิติศาสตร์','เกษตรศาสตร์','สถาปัตยกรรมศาสตร์'];
        $departments = ['วิทยาการคอมพิวเตอร์','ฟิสิกส์','การบัญชี','ภาษาอังกฤษ','คณิตศาสตร์','นิติศาสตร์','พืชศาสตร์','สถาปัตยกรรม'];
        $names = ['สมชาย ใจดี','สมหญิง รักเรียน','วิชัย แสนสุข','กมลา ศรีสุข','ธนกร พงษ์พานิช','นภา วงศ์วิวัฒน์','ปิยะ จริงใจ','สุดา มั่นคง','อภิชาต เรืองศักดิ์','จิราภา พรหมมา'];
        for ($i = 0; $i < 10; $i++) {
            $students->push(User::create([
                'student_id' => '65' . str_pad((string)($i + 1), 8, '0', STR_PAD_LEFT),
                'full_name'  => $names[$i],
                'faculty'    => $faculties[$i % count($faculties)],
                'department' => $departments[$i % count($departments)],
                'year'       => random_int(1, 4),
                'role'       => 'student',
            ]));
        }

        // ---------- สร้างหมวดหมู่กิจกรรม ----------
        $categories = collect([
            ['name' => 'จิตอาสา', 'description' => 'กิจกรรมจิตอาสาและบำเพ็ญประโยชน์', 'required_hours' => 15, 'icon' => 'heart', 'color' => '#EF4444'],
            ['name' => 'วิชาการ', 'description' => 'กิจกรรมวิชาการ สัมมนา อบรม', 'required_hours' => 12, 'icon' => 'book', 'color' => '#3B82F6'],
            ['name' => 'กีฬาและสุขภาพ', 'description' => 'กิจกรรมกีฬาและเสริมสร้างสุขภาพ', 'required_hours' => 10, 'icon' => 'trophy', 'color' => '#10B981'],
            ['name' => 'ศิลปวัฒนธรรม', 'description' => 'กิจกรรมศิลปะและวัฒนธรรม', 'required_hours' => 8, 'icon' => 'palette', 'color' => '#F59E0B'],
            ['name' => 'คุณธรรมจริยธรรม', 'description' => 'กิจกรรมเสริมสร้างคุณธรรม', 'required_hours' => 6, 'icon' => 'star', 'color' => '#8B5CF6'],
        ])->map(fn($c) => ActivityCategory::create($c));

        // ---------- สร้างกิจกรรมตัวอย่าง ----------
        $activityData = [
            ['title' => 'ปลูกป่าชายเลน', 'description' => 'ร่วมปลูกป่าชายเลนเพื่อฟื้นฟูระบบนิเวศชายฝั่ง', 'location' => 'ป่าชายเลนบางขุนเทียน', 'hours' => 6, 'cat' => 0, 'mandatory' => true],
            ['title' => 'สัมมนา AI ในอนาคต', 'description' => 'สัมมนาเรื่อง Artificial Intelligence กับโลกอนาคต โดยวิทยากรจากภาคอุตสาหกรรม', 'location' => 'ห้องประชุม อาคาร SC', 'hours' => 3, 'cat' => 1, 'mandatory' => false],
            ['title' => 'แข่งขันฟุตซอลต้านยาเสพติด', 'description' => 'การแข่งขันฟุตซอลระหว่างคณะ', 'location' => 'โรงยิม มหาวิทยาลัย', 'hours' => 4, 'cat' => 2, 'mandatory' => false],
            ['title' => 'ลอยกระทง', 'description' => 'สืบสานประเพณีลอยกระทง ร่วมทำกระทงจากวัสดุธรรมชาติ', 'location' => 'สระน้ำกลาง มหาวิทยาลัย', 'hours' => 3, 'cat' => 3, 'mandatory' => false],
            ['title' => 'ทำบุญตักบาตรเข้าพรรษา', 'description' => 'กิจกรรมทำบุญตักบาตรเนื่องในวันเข้าพรรษา', 'location' => 'ลานอเนกประสงค์', 'hours' => 2, 'cat' => 4, 'mandatory' => true],
            ['title' => 'Workshop: Laravel for Beginners', 'description' => 'อบรมเชิงปฏิบัติการ Laravel สำหรับผู้เริ่มต้น', 'location' => 'ห้อง Lab 3 อาคาร CS', 'hours' => 6, 'cat' => 1, 'mandatory' => false],
            ['title' => 'บริจาคโลหิต', 'description' => 'ร่วมบริจาคโลหิตกับสภากาชาดไทย', 'location' => 'อาคารพยาบาล', 'hours' => 2, 'cat' => 0, 'mandatory' => false],
            ['title' => 'วิ่งการกุศล Uni Run 2025', 'description' => 'วิ่งการกุศลระดมทุนเพื่อทุนการศึกษา ระยะ 5 กม. และ 10 กม.', 'location' => 'สนามกีฬามหาวิทยาลัย', 'hours' => 3, 'cat' => 2, 'mandatory' => false],
        ];

        $activities = collect();
        foreach ($activityData as $i => $data) {
            $date = now()->addDays($i * 5 + 3);
            $activity = Activity::create([
                'title' => $data['title'],
                'description' => $data['description'],
                'location' => $data['location'],
                'activity_date' => $date->toDateString(),
                'start_time' => '09:00',
                'end_time' => '12:00',
                'activity_hours' => $data['hours'],
                'max_participants' => random_int(30, 100),
                'register_open_at' => now()->subDays(2),
                'register_close_at' => $date->copy()->subDay(),
                'checkin_open_at' => $date->copy()->setTime(8, 30),
                'checkin_close_at' => $date->copy()->setTime(12, 30),
                'is_mandatory' => $data['mandatory'],
                'category_id' => $categories[$data['cat']]->id,
                'created_by' => $admin->id,
                'qr_token' => Str::random(64),
                'status' => 'open',
            ]);
            $activities->push($activity);
        }

        // ---------- การลงทะเบียน ----------
        foreach ($activities as $activity) {
            $registeredStudents = $students->random(random_int(3, 8));
            foreach ($registeredStudents as $student) {
                Registration::create([
                    'user_id' => $student->id,
                    'activity_id' => $activity->id,
                    'status' => 'approved',
                ]);
            }
        }

        // ---------- การเข้าร่วมกิจกรรมเก่า ----------
        $pastActivities = $activities->take(2);
        foreach ($pastActivities as $activity) {
            $activity->update([
                'activity_date' => now()->subDays(5),
                'register_open_at' => now()->subDays(15),
                'register_close_at' => now()->subDays(6),
                'checkin_open_at' => now()->subDays(5)->setTime(8, 30),
                'checkin_close_at' => now()->subDays(5)->setTime(12, 30),
                'status' => 'done',
            ]);

            $regs = $activity->registrations()->where('status', 'approved')->get();
            foreach ($regs as $reg) {
                Attendance::create([
                    'user_id' => $reg->user_id,
                    'activity_id' => $activity->id,
                    'method' => 'qr_scan',
                    'is_verified' => true,
                    'verified_by' => $admin->id,
                ]);
            }
        }

        $this->command->info('Seeded: 11 users (1 admin + 10 students), 5 categories, 8 activities, registrations & attendances');
    }
}
