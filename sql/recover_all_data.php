<?php
declare(strict_types=1);

/**
 * Recover all old data: announcements, activity images, profile photos.
 * Usage: php sql/recover_all_data.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

echo "=== Recovering Old Data ===\n\n";

// ─── 1. Import Announcements from MySQL backup ───
echo "1. Importing announcements from MySQL backup...\n";

$announcements = [
    [
        'id' => 1,
        'title' => '📢 ประชาสัมพันธ์การเก็บชั่วโมงกิจกรรม 📚✨',
        'content' => '🔹 นักศึกษาที่ ยังไม่ได้เริ่มเก็บชั่วโมงกิจกรรมภายในมหาวิทยาลัย
ขณะนี้กิจกรรมดังกล่าว มีผู้สมัครครบเต็มแล้ว ❌

🔹 ส่วนนักศึกษาที่ ได้ติดต่อขอเก็บชั่วโมงภายในมหาวิทยาลัยไว้ก่อนแล้ว
ยังสามารถติดต่อเก็บชั่วโมงได้ ถึงวันที่ 30 มกราคม 2569 🗓️

🩸 กิจกรรมบริจาคโลหิต ❤️
นักศึกษาที่สนใจสามารถร่วมบริจาคโลหิตได้
📅 ระหว่างวันที่ 13 – 15 กุมภาพันธ์ 2569
📍 ณ อาคาร 9 ชั้น 5

📝 ผู้ที่ประสงค์จะบริจาคโลหิต
ต้องรับ ใบสมัครและจองคิวภายในวันที่ 10 กุมภาพันธ์ 2569
📌 ณ ห้องกองทุนเงินให้กู้ยืมเพื่อการศึกษา (กยศ.) เท่านั้น

⏱️ การบริจาคโลหิตครั้งนี้
จะได้รับ ชั่วโมงจิตอาสา จำนวน 18 ชั่วโมง ⭐⭐
❌ การบริจาคโลหิตไม่ได้เป็น กิจกรรมบังคับ และไม่สามารถบริจาคแทนกัน

📲 กิจกรรมอื่น ๆ
ขอให้นักศึกษาติดตามประกาศได้ที่
👉 เพจ งานบริการและสวัสดิการนักศึกษา มหาวิทยาลัยราชภัฏภูเก็ต

☎️ หากนักศึกษามีข้อสงสัยหรือต้องการสอบถามข้อมูลเพิ่มเติม
สามารถติดต่อได้ที่หมายเลขโทรศัพท์
📞 086-478-0062

ยินดีให้คำแนะนำค่ะ 😊💙',
        'image_path' => null,
        'target_faculty' => null,
        'type' => 'info',
        'is_active' => true,
        'created_by' => 1,
        'created_at' => '2026-03-19 17:09:54',
        'updated_at' => '2026-03-19 17:09:54',
    ],
    [
        'id' => 2,
        'title' => 'นักศึกษาที่เก็บ ชั่วโมงกิจกรรมไม่ครบ หรือ ยังไม่ได้เก็บชั่วโมงกิจกรรม ขอให้เร่งดำเนินการเก็บชั่วโมงให้แล้วเสร็จ ภายในวันที่ 15 มีนาคม 2569 ⏰',
        'content' => '📢 ประกาศกิจกรรม กยศ.

นักศึกษาที่เก็บ ชั่วโมงกิจกรรมไม่ครบ หรือ ยังไม่ได้เก็บชั่วโมงกิจกรรม
ขอให้เร่งดำเนินการเก็บชั่วโมงให้แล้วเสร็จ ภายในวันที่ 15 มีนาคม 2569 ⏰

โดยมีเงื่อนไขดังนี้

1️⃣ นักศึกษาสามารถเก็บชั่วโมงได้ในหน่วยงานกลางภายในมหาวิทยาลัย (คณะ / สำนัก / โรงเรียนสาธิต ฯลฯ) โดยต้อง ไม่เป็นส่วนหนึ่งของการเรียนการสอน และ ไม่ได้รับค่าตอบแทน

2️⃣ การนับชั่วโมงให้นับตามเวลาปฏิบัติงานจริง หากทำกิจกรรมเต็มวัน ให้หักเวลาพักกลางวัน 1 ชั่วโมง

3️⃣ นักศึกษาสามารถติดต่อหน่วยงานเพื่อขอปฏิบัติงานและเก็บชั่วโมงได้ด้วยตนเอง

4️⃣ การบันทึกชั่วโมง ให้ดาวน์โหลดแบบฟอร์มจาก QR Code ที่กำหนด และให้หน่วยงานลงนามรับรองตามความเป็นจริง หลังปฏิบัติงานเสร็จในแต่ละรอบ

5️⃣ หากตรวจสอบพบว่ามีการบันทึกข้อมูลอันเป็นเท็จ นักศึกษาจะต้องรับผิดชอบต่อการกระทำดังกล่าว และอาจส่งผลให้ ถูกตัดสิทธิ์การกู้ยืม กยศ.

6️⃣ หากเก็บชั่วโมงกิจกรรมไม่ครบภายในกำหนด จะถือว่านักศึกษา ไม่ประสงค์ขอกู้ยืม กยศ. ในปีการศึกษาถัดไป

📌 ขอให้นักศึกษาดำเนินการตามกำหนดอย่างเคร่งครัด
เพื่อรักษาสิทธิ์ของตนเองในการกู้ยืมต่อไป',
        'image_path' => 'announcements/gEHqbKq78FGzSdljfY6gaOp12JHKyZBIKSuUxKEe.jpg',
        'target_faculty' => null,
        'type' => 'info',
        'is_active' => true,
        'created_by' => 1,
        'created_at' => '2026-03-19 17:30:02',
        'updated_at' => '2026-03-19 17:30:02',
    ],
];

$count = 0;
foreach ($announcements as $ann) {
    DB::table('announcements')->updateOrInsert(
        ['id' => $ann['id']],
        $ann
    );
    $count++;
}
echo "   Announcements imported: {$count}\n";

// ─── 2. Map old activity image paths to existing files ───
echo "2. Updating activity image paths...\n";

// Map old image paths to new UUID-based filenames
$imageMap = [
    'activities/ER2I52BUIFJ3Un6zW8g28LyXWRRQyGr9YSKSshCU.png' => 'activities/activity_1.jpg',
    'activities/nMhDs67Qixzo5GMptGp5q2yB2zsNfHI9OM8tPCBv.png' => 'activities/activity_2.jpg',
    'activities/1eS8hNwxgeKjDaoJdwU7RslZ8JrqTkTjnENVoa2M.png' => 'activities/activity_5.jpg',
    'activities/SUHwLgAXlv18OqMEPsHOy2serWIsZjTQyXkLy3mP.jpg' => 'activities/activity_6.jpg',
];

$updated = 0;
foreach ($imageMap as $oldPath => $newPath) {
    $exists = DB::table('activities')->where('image_path', $oldPath)->count();
    if ($exists > 0) {
        DB::table('activities')->where('image_path', $oldPath)->update(['image_path' => $newPath]);
        $updated++;
    }
}
echo "   Activity images remapped: {$updated}\n";

// ─── 3. Verify all data ───
echo "\n=== Verification ===\n";

$tables = ['users', 'activities', 'activity_categories', 'registrations', 'attendances', 'announcements', 'messages', 'rooms'];
foreach ($tables as $t) {
    $c = DB::table($t)->count();
    echo "  {$t}: {$c}\n";
}

// Check photos on disk
$profileCount = count(glob('/data/data/com.termux/files/home/uni-activity/storage/app/public/profile-photos/*'));
$activityImgCount = count(glob('/data/data/com.termux/files/home/uni-activity/storage/app/public/activities/*'));
$announcementImgCount = count(glob('/data/data/com.termux/files/home/uni-activity/storage/app/public/announcements/*'));

echo "\n  Photos on disk:";
echo "\n    profile-photos: {$profileCount} files";
echo "\n    activities: {$activityImgCount} files";
echo "\n    announcements: {$announcementImgCount} files";

echo "\n\nDone!\n";
