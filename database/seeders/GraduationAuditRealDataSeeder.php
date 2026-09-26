<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\GraduationCriteria;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder สำหรับสร้างข้อมูลกิจกรรมและประวัติการเข้าร่วมของนักศึกษาจริงในระบบ
 * เพื่อให้ระบบ Graduation Audit, Transcript และการประเมินความพร้อมจบการศึกษามีข้อมูลที่สมบูรณ์และสมจริง
 */
class GraduationAuditRealDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::first();
        $adminId = $admin ? $admin->id : 1;

        // 1. ตรวจสอบหรือสร้างหมวดหมู่กิจกรรม
        $volunteerCat = ActivityCategory::firstOrCreate(
            ['name' => 'จิตอาสา'],
            [
                'description'    => 'กิจกรรมจิตอาสาและบำเพ็ญประโยชน์เพื่อสังคม',
                'required_hours' => 20.0,
                'icon'           => 'heart',
                'color'          => '#EF4444',
            ]
        );

        $academicCat = ActivityCategory::firstOrCreate(
            ['name' => 'วิชาการ'],
            [
                'description'    => 'กิจกรรมวิชาการ สัมมนา และอบรมทักษะวิชาชีพ',
                'required_hours' => 15.0,
                'icon'           => 'book',
                'color'          => '#3B82F6',
            ]
        );

        $sportsCat = ActivityCategory::firstOrCreate(
            ['name' => 'กีฬาและสุขภาพ'],
            [
                'description'    => 'กิจกรรมกีฬา นันทนาการ และเสริมสร้างสุขภาวะ',
                'required_hours' => 10.0,
                'icon'           => 'trophy',
                'color'          => '#10B981',
            ]
        );

        $cultureCat = ActivityCategory::firstOrCreate(
            ['name' => 'ศิลปวัฒนธรรม'],
            [
                'description'    => 'กิจกรรมศิลปวัฒนธรรม ประเพณี และภูมิปัญญาท้องถิ่น',
                'required_hours' => 8.0,
                'icon'           => 'palette',
                'color'          => '#F59E0B',
            ]
        );

        $ethicsCat = ActivityCategory::firstOrCreate(
            ['name' => 'คุณธรรมจริยธรรม'],
            [
                'description'    => 'กิจกรรมเสริมสร้างคุณธรรม จริยธรรม และธรรมาภิบาล',
                'required_hours' => 6.0,
                'icon'           => 'star',
                'color'          => '#8B5CF6',
            ]
        );

        // 2. ตรวจสอบหรือสร้างเกณฑ์การสำเร็จการศึกษา (Graduation Criteria)
        $volunteerKey = (string) $volunteerCat->id;
        GraduationCriteria::updateOrCreate(
            ['code' => 'CRITERIA-BACHELOR-DEFAULT'],
            [
                'name'                 => 'เกณฑ์กิจกรรมพัฒนานักศึกษาตามโครงสร้างหลักสูตรปริญญาตรี (เกณฑ์มาตรฐานมหาวิทยาลัย)',
                'faculty'              => null,
                'department'           => null,
                'academic_year_start'  => 2564,
                'min_total_hours'      => 100.0,
                'min_mandatory_hours'  => 10.0,
                'scope_requirements'   => [
                    'university' => 40.0,
                    'faculty'    => 40.0,
                ],
                'category_requirements' => [
                    $volunteerKey => 20.0,
                ],
                'require_all_mandatory_activities' => true,
                'is_default'           => true,
                'is_active'            => true,
                'description'          => 'ข้อกำหนดกิจกรรมพัฒนานักศึกษา: กิจกรรมมหาวิทยาลัย 40 ชม., กิจกรรมคณะ 40 ชม., จิตอาสา 20 ชม. รวมไม่น้อยกว่า 100 ชม.',
            ]
        );

        // 3. กิจกรรมระดับมหาวิทยาลัย (University Scope)
        $uniActivities = [
            'orientation' => [
                'title'          => 'พิธีปฐมนิเทศนักศึกษาใหม่และเตรียมความพร้อมสู่มหาวิทยาลัย',
                'description'    => 'กิจกรรมบังคับสำหรับนักศึกษาใหม่ทุกคน แนะนำโครงสร้างหลักสูตร การใช้ชีวิต และกฎระเบียบมหาวิทยาลัย',
                'location'       => 'หอประชุมใหญ่ อาคารเฉลิมพระเกียรติ',
                'hours'          => 10.0,
                'cat_id'         => $academicCat->id,
                'scope'          => 'university',
                'is_mandatory'   => true,
                'date'           => now()->subMonths(18)->format('Y-m-d'),
            ],
            'teacher_day' => [
                'title'          => 'พิธีไหว้ครูและเชิดชูเกียรติศิษย์เก่าดีเด่น',
                'description'    => 'พิธีไหว้ครูประจำปีการศึกษา เพื่อแสดงความกตัญญูกตเวทิตาและเสริมสร้างจริยธรรม',
                'location'       => 'ศูนย์ประชุมและแสดงศิลปวัฒนธรรม',
                'hours'          => 6.0,
                'cat_id'         => $ethicsCat->id,
                'scope'          => 'university',
                'is_mandatory'   => false,
                'date'           => now()->subMonths(14)->format('Y-m-d'),
            ],
            'sports_day' => [
                'title'          => 'งานมหกรรมการแข่งขันกีฬาและนันทนาการ PKRU Games',
                'description'    => 'การแข่งขันกีฬาเชื่อมความสามัคคีระหว่างคณะ เสริมสร้างสุขภาพและน้ำใจนักกีฬา',
                'location'       => 'สนามกีฬากลาง มหาวิทยาลัย',
                'hours'          => 8.0,
                'cat_id'         => $sportsCat->id,
                'scope'          => 'university',
                'is_mandatory'   => false,
                'date'           => now()->subMonths(10)->format('Y-m-d'),
            ],
            'national_conf' => [
                'title'          => 'การประชุมวิชาการระดับชาติและการนำเสนอผลงานวิจัยนักศึกษา',
                'description'    => 'เวทีนำเสนอผลงานวิจัย นวัตกรรม และสิ่งประดิษฐ์ของนักศึกษาในระดับอุดมศึกษา',
                'location'       => 'ห้องประชุมราชพฤกษ์',
                'hours'          => 12.0,
                'cat_id'         => $academicCat->id,
                'scope'          => 'university',
                'is_mandatory'   => false,
                'date'           => now()->subMonths(6)->format('Y-m-d'),
            ],
            'blood_donation' => [
                'title'          => 'กิจกรรมบริจาคโลหิตเพื่อชีวิตเพื่อนมนุษย์ ร่วมกับสภากาชาดไทย',
                'description'    => 'โครงการร่วมบริจาคโลหิตเพื่อสำรองคลังเลือดและช่วยเหลือผู้ป่วยวิกฤต',
                'location'       => 'ลานอเนกประสงค์ อาคารกิจกรรมนักศึกษา',
                'hours'          => 6.0,
                'cat_id'         => $volunteerCat->id,
                'scope'          => 'university',
                'is_mandatory'   => false,
                'date'           => now()->subMonths(4)->format('Y-m-d'),
            ],
            'loy_krathong' => [
                'title'          => 'สืบสานประเพณีลอยกระทงและอนุรักษ์สิ่งแวดล้อมทางน้ำ',
                'description'    => 'ร่วมประดิษฐ์กระทงจากวัสดุธรรมชาติและรณรงค์รักษาความสะอาดแหล่งน้ำ',
                'location'       => 'สระน้ำกลาง มหาวิทยาลัย',
                'hours'          => 6.0,
                'cat_id'         => $cultureCat->id,
                'scope'          => 'university',
                'is_mandatory'   => false,
                'date'           => now()->subMonths(3)->format('Y-m-d'),
            ],
        ];

        // 4. กิจกรรมระดับคณะ/สาขา (Faculty Scope)
        $facActivities = [
            'cs_fullstack' => [
                'title'          => 'อบรมเชิงปฏิบัติการ: Full-Stack Web Development & Modern DevOps',
                'description'    => 'พัฒนาทักษะวิชาชีพด้านการสร้าง Web Application ด้วย Laravel, Tailwind และ Docker',
                'location'       => 'ห้องปฏิบัติการคอมพิวเตอร์ Lab 3 อาคารเทคโนโลยีสารสนเทศ',
                'hours'          => 15.0,
                'cat_id'         => $academicCat->id,
                'scope'          => 'faculty',
                'is_mandatory'   => false,
                'date'           => now()->subMonths(8)->format('Y-m-d'),
            ],
            'cs_camp' => [
                'title'          => 'ค่ายส่งเสริมทักษะเทคโนโลยีและวิชาการคอมพิวเตอร์สู่อนาคต',
                'description'    => 'ค่ายวิชาการพัฒนาศักยภาพนักศึกษาด้านการแก้ปัญหาอัลกอริทึมและโครงงานนวัตกรรม',
                'location'       => 'ศูนย์การเรียนรู้ คณะวิทยาศาสตร์และเทคโนโลยี',
                'hours'          => 15.0,
                'cat_id'         => $academicCat->id,
                'scope'          => 'faculty',
                'is_mandatory'   => false,
                'date'           => now()->subMonths(5)->format('Y-m-d'),
            ],
            'ai_seminar' => [
                'title'          => 'สัมมนาเทคโนโลยีชีวมิติและปัญญาประดิษฐ์ยุคใหม่ 2026',
                'description'    => 'การประยุกต์ใช้ Face Recognition, Computer Vision และ Generative AI ในภาคอุตสาหกรรม',
                'location'       => 'ห้องประชุมสัมมนา อาคารวิทยบริการ',
                'hours'          => 6.0,
                'cat_id'         => $academicCat->id,
                'scope'          => 'faculty',
                'is_mandatory'   => false,
                'date'           => now()->subMonths(2)->format('Y-m-d'),
            ],
            'mangrove_volunteer' => [
                'title'          => 'ค่ายจิตอาสาฟื้นฟูระบบนิเวศป่าชายเลนและพัฒนาสิ่งแวดล้อมชุมชน',
                'description'    => 'ปลูกป่าชายเลนเพื่อเพิ่มพื้นที่สีเขียว ป้องกันการกัดเซาะชายฝั่ง และเก็บขยะอนุรักษ์ทะเล',
                'location'       => 'ศูนย์อนุรักษ์ทรัพยากรป่าชายเลน',
                'hours'          => 14.0,
                'cat_id'         => $volunteerCat->id,
                'scope'          => 'faculty',
                'is_mandatory'   => false,
                'date'           => now()->subMonths(7)->format('Y-m-d'),
            ],
            'community_dev' => [
                'title'          => 'โครงการอาสาพัฒนาโรงเรียนและสร้างห้องสมุดในชนบท',
                'description'    => 'ร่วมปรับปรุงทัศนียภาพ ทาสีอาคารเรียน และจัดหมวดหมู่หนังสือห้องสมุดเพื่อเยาวชน',
                'location'       => 'โรงเรียนบ้านหนองบอนพัฒนา',
                'hours'          => 10.0,
                'cat_id'         => $volunteerCat->id,
                'scope'          => 'faculty',
                'is_mandatory'   => false,
                'date'           => now()->subMonths(11)->format('Y-m-d'),
            ],
            'acct_seminar' => [
                'title'          => 'โครงการอบรมมาตรฐานการบัญชีและระบบภาษีอากรดิจิทัล',
                'description'    => 'เตรียมความพร้อมนักศึกษาบัญชีสู่การทำงานในยุคเศรษฐกิจดิจิทัล',
                'location'       => 'ห้องบรรยายรวม คณะบริหารธุรกิจ',
                'hours'          => 15.0,
                'cat_id'         => $academicCat->id,
                'scope'          => 'faculty',
                'is_mandatory'   => false,
                'date'           => now()->subMonths(9)->format('Y-m-d'),
            ],
            'lang_workshop' => [
                'title'          => 'โครงการพัฒนาทักษะภาษาอังกฤษเพื่อการสื่อสารและสอบวัดระดับสากล',
                'description'    => 'การฝึกอบรมทักษะการฟัง พูด อ่าน เขียน เพื่อเตรียมสอบ TOEIC และการทำงานนานาชาติ',
                'location'       => 'ศูนย์ภาษา คณะมนุษยศาสตร์',
                'hours'          => 15.0,
                'cat_id'         => $academicCat->id,
                'scope'          => 'faculty',
                'is_mandatory'   => false,
                'date'           => now()->subMonths(8)->format('Y-m-d'),
            ],
            'physics_lab' => [
                'title'          => 'อบรมการใช้งานเครื่องมือวัดและเซนเซอร์ทางฟิสิกส์ประยุกต์ขั้นสูง',
                'description'    => 'ฝึกทักษะการวัดทางแสง คลื่นแม่เหล็กไฟฟ้า และการควบคุมอุปกรณ์เซนเซอร์',
                'location'       => 'ห้องปฏิบัติการฟิสิกส์ชั้นสูง',
                'hours'          => 15.0,
                'cat_id'         => $academicCat->id,
                'scope'          => 'faculty',
                'is_mandatory'   => false,
                'date'           => now()->subMonths(10)->format('Y-m-d'),
            ],
        ];

        $createdActivities = [];

        foreach (array_merge($uniActivities, $facActivities) as $key => $actData) {
            $activity = Activity::updateOrCreate(
                ['title' => $actData['title']],
                [
                    'description'      => $actData['description'],
                    'location'         => $actData['location'],
                    'activity_date'    => $actData['date'],
                    'start_time'       => '09:00',
                    'end_time'         => '16:00',
                    'activity_hours'   => $actData['hours'],
                    'max_participants' => 150,
                    'register_open_at' => now()->subYear(),
                    'register_close_at'=> now()->addMonths(6),
                    'checkin_open_at'  => now()->subYear(),
                    'checkin_close_at' => now()->addMonths(6),
                    'is_mandatory'     => $actData['is_mandatory'],
                    'scope'            => $actData['scope'],
                    'category_id'      => $actData['cat_id'],
                    'created_by'       => $adminId,
                    'qr_token'         => Str::random(64),
                    'status'           => 'done',
                ]
            );
            $createdActivities[$key] = $activity;
        }

        // 5. บันทึก Attendance ให้แก่นักศึกษาในระบบตามระดับชั้นปีและความสมจริง
        $students = User::where('role', 'student')->get()->keyBy('student_id');

        $recordAttendance = function (User $student, Activity $activity, string $date) use ($adminId): void {
            // สร้าง Registration
            Registration::updateOrCreate(
                ['user_id' => $student->id, 'activity_id' => $activity->id],
                ['status' => 'approved']
            );

            // สร้าง Attendance ที่ได้รับการอนุมัติ
            Attendance::updateOrCreate(
                ['user_id' => $student->id, 'activity_id' => $activity->id],
                [
                    'status'        => 'approved',
                    'method'        => 'qr_scan',
                    'checked_in_at' => $date,
                    'is_verified'   => true,
                    'verified_by'   => $adminId,
                ]
            );
        };

        // --- นักศึกษาปี 4: สมหญิง รักเรียน (6500000002) -> สะสมครบเกณฑ์จบการศึกษา (104 ชม. ผ่านทุกเกณฑ์) ---
        if (isset($students['6500000002'])) {
            $s = $students['6500000002'];
            $acts = [
                'orientation'        => now()->subMonths(36)->format('Y-m-d H:i:s'), // 10 ชม. (Uni, Mand)
                'teacher_day'        => now()->subMonths(32)->format('Y-m-d H:i:s'), // 6 ชม. (Uni)
                'sports_day'         => now()->subMonths(26)->format('Y-m-d H:i:s'), // 8 ชม. (Uni)
                'national_conf'      => now()->subMonths(18)->format('Y-m-d H:i:s'), // 12 ชม. (Uni)
                'loy_krathong'       => now()->subMonths(14)->format('Y-m-d H:i:s'), // 6 ชม. (Uni) -> Uni รวม 42 ชม.
                'physics_lab'        => now()->subMonths(22)->format('Y-m-d H:i:s'), // 15 ชม. (Faculty)
                'ai_seminar'         => now()->subMonths(12)->format('Y-m-d H:i:s'), // 6 ชม. (Faculty)
                'acct_seminar'       => now()->subMonths(8)->format('Y-m-d H:i:s'),  // 15 ชม. (Faculty)
                'blood_donation'     => now()->subMonths(16)->format('Y-m-d H:i:s'), // 6 ชม. (Volunteer)
                'mangrove_volunteer' => now()->subMonths(10)->format('Y-m-d H:i:s'), // 14 ชม. (Volunteer) -> จิตอาสา 20 ชม.
                'community_dev'      => now()->subMonths(6)->format('Y-m-d H:i:s'),  // 10 ชม. (Faculty) -> คณะรวม 46 ชม.
            ];
            foreach ($acts as $k => $d) {
                if (isset($createdActivities[$k])) {
                    $recordAttendance($s, $createdActivities[$k], $d);
                }
            }
        }

        // --- นักศึกษาปี 3: วิชัย แสนสุข (6500000003) -> 77 ชม. (ยังขาดจิตอาสาและคณะ) ---
        if (isset($students['6500000003'])) {
            $s = $students['6500000003'];
            $acts = [
                'orientation'        => now()->subMonths(24)->format('Y-m-d H:i:s'), // 10 ชม.
                'teacher_day'        => now()->subMonths(20)->format('Y-m-d H:i:s'), // 6 ชม.
                'sports_day'         => now()->subMonths(16)->format('Y-m-d H:i:s'), // 8 ชม.
                'national_conf'      => now()->subMonths(12)->format('Y-m-d H:i:s'), // 12 ชม. -> Uni 36 ชม.
                'acct_seminar'       => now()->subMonths(14)->format('Y-m-d H:i:s'), // 15 ชม.
                'cs_fullstack'       => now()->subMonths(10)->format('Y-m-d H:i:s'), // 15 ชม.
                'blood_donation'     => now()->subMonths(8)->format('Y-m-d H:i:s'),  // 6 ชม. (Volunteer)
                'community_dev'      => now()->subMonths(4)->format('Y-m-d H:i:s'),  // 10 ชม. (Volunteer)
            ];
            foreach ($acts as $k => $d) {
                if (isset($createdActivities[$k])) {
                    $recordAttendance($s, $createdActivities[$k], $d);
                }
            }
        }

        // --- นักศึกษาปี 3: กมลา ศรีสุข (6500000004) -> 67 ชม. ---
        if (isset($students['6500000004'])) {
            $s = $students['6500000004'];
            $acts = [
                'orientation'    => now()->subMonths(24)->format('Y-m-d H:i:s'), // 10 ชม.
                'sports_day'     => now()->subMonths(18)->format('Y-m-d H:i:s'), // 8 ชม.
                'loy_krathong'   => now()->subMonths(14)->format('Y-m-d H:i:s'), // 6 ชม.
                'national_conf'  => now()->subMonths(10)->format('Y-m-d H:i:s'), // 12 ชม.
                'lang_workshop'  => now()->subMonths(12)->format('Y-m-d H:i:s'), // 15 ชม.
                'blood_donation' => now()->subMonths(8)->format('Y-m-d H:i:s'),  // 6 ชม.
                'community_dev'  => now()->subMonths(5)->format('Y-m-d H:i:s'),  // 10 ชม.
            ];
            foreach ($acts as $k => $d) {
                if (isset($createdActivities[$k])) {
                    $recordAttendance($s, $createdActivities[$k], $d);
                }
            }
        }

        // --- นักศึกษาปี 3: จิราภา พรหมมา (6500000010) -> 82 ชม. ---
        if (isset($students['6500000010'])) {
            $s = $students['6500000010'];
            $acts = [
                'orientation'        => now()->subMonths(24)->format('Y-m-d H:i:s'), // 10 ชม.
                'teacher_day'        => now()->subMonths(20)->format('Y-m-d H:i:s'), // 6 ชม.
                'sports_day'         => now()->subMonths(16)->format('Y-m-d H:i:s'), // 8 ชม.
                'national_conf'      => now()->subMonths(12)->format('Y-m-d H:i:s'), // 12 ชม.
                'loy_krathong'       => now()->subMonths(8)->format('Y-m-d H:i:s'),  // 6 ชม. -> Uni 42 ชม. (Passed Uni)
                'physics_lab'        => now()->subMonths(14)->format('Y-m-d H:i:s'), // 15 ชม.
                'mangrove_volunteer' => now()->subMonths(6)->format('Y-m-d H:i:s'),  // 14 ชม.
                'ai_seminar'         => now()->subMonths(2)->format('Y-m-d H:i:s'),  // 6 ชม.
            ];
            foreach ($acts as $k => $d) {
                if (isset($createdActivities[$k])) {
                    $recordAttendance($s, $createdActivities[$k], $d);
                }
            }
        }

        // --- นักศึกษาปี 3: พพพพ (671111111) -> 73 ชม. ---
        if (isset($students['671111111'])) {
            $s = $students['671111111'];
            $acts = [
                'orientation'        => now()->subMonths(24)->format('Y-m-d H:i:s'), // 10 ชม.
                'sports_day'         => now()->subMonths(18)->format('Y-m-d H:i:s'), // 8 ชม.
                'national_conf'      => now()->subMonths(12)->format('Y-m-d H:i:s'), // 12 ชม.
                'cs_fullstack'       => now()->subMonths(14)->format('Y-m-d H:i:s'), // 15 ชม.
                'cs_camp'            => now()->subMonths(8)->format('Y-m-d H:i:s'),  // 15 ชม.
                'mangrove_volunteer' => now()->subMonths(5)->format('Y-m-d H:i:s'),  // 14 ชม.
            ];
            foreach ($acts as $k => $d) {
                if (isset($createdActivities[$k])) {
                    $recordAttendance($s, $createdActivities[$k], $d);
                }
            }
        }

        // --- นักศึกษาปี 2: นลธวัช รักยิ่ง (6710886217) -> 58 ชม. (นักศึกษาตัวจริงที่ใช้ทดสอบ Face Check-in / LINE) ---
        if (isset($students['6710886217'])) {
            $s = $students['6710886217'];
            $acts = [
                'orientation'    => now()->subMonths(12)->format('Y-m-d H:i:s'), // 10 ชม. (Uni, Mand)
                'sports_day'     => now()->subMonths(8)->format('Y-m-d H:i:s'),  // 8 ชม. (Uni)
                'loy_krathong'   => now()->subMonths(4)->format('Y-m-d H:i:s'),  // 6 ชม. (Uni)
                'cs_fullstack'   => now()->subMonths(6)->format('Y-m-d H:i:s'),  // 15 ชม. (Faculty)
                'ai_seminar'     => now()->subMonths(2)->format('Y-m-d H:i:s'),  // 6 ชม. (Faculty)
                'blood_donation' => now()->subMonths(3)->format('Y-m-d H:i:s'),  // 6 ชม. (Volunteer)
            ];
            foreach ($acts as $k => $d) {
                if (isset($createdActivities[$k])) {
                    $recordAttendance($s, $createdActivities[$k], $d);
                }
            }

            // ถ้ามีกิจกรรม ID 17 ให้คง approved ไว้
            $act17 = Activity::find(17);
            if ($act17) {
                $recordAttendance($s, $act17, now()->subMonth()->format('Y-m-d H:i:s'));
            }
        }

        // --- นักศึกษาปี 2: พิพัฒนชัย วิมลเมือง (6710886221) -> 45 ชม. ---
        if (isset($students['6710886221'])) {
            $s = $students['6710886221'];
            $acts = [
                'orientation'    => now()->subMonths(12)->format('Y-m-d H:i:s'), // 10 ชม.
                'sports_day'     => now()->subMonths(8)->format('Y-m-d H:i:s'),  // 8 ชม.
                'cs_fullstack'   => now()->subMonths(6)->format('Y-m-d H:i:s'),  // 15 ชม.
                'ai_seminar'     => now()->subMonths(2)->format('Y-m-d H:i:s'),  // 6 ชม.
                'blood_donation' => now()->subMonths(3)->format('Y-m-d H:i:s'),  // 6 ชม.
            ];
            foreach ($acts as $k => $d) {
                if (isset($createdActivities[$k])) {
                    $recordAttendance($s, $createdActivities[$k], $d);
                }
            }
        }

        // --- นักศึกษาปี 1: สมชาย ใจดี (6500000001) -> 24 ชม. ---
        if (isset($students['6500000001'])) {
            $s = $students['6500000001'];
            $acts = [
                'orientation' => now()->subMonths(4)->format('Y-m-d H:i:s'), // 10 ชม. (Uni, Mand)
                'sports_day'  => now()->subMonths(2)->format('Y-m-d H:i:s'), // 8 ชม. (Uni)
                'ai_seminar'  => now()->subMonth()->format('Y-m-d H:i:s'),   // 6 ชม. (Faculty)
            ];
            foreach ($acts as $k => $d) {
                if (isset($createdActivities[$k])) {
                    $recordAttendance($s, $createdActivities[$k], $d);
                }
            }
        }
    }
}
