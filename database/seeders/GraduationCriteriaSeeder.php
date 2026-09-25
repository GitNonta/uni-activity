<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ActivityCategory;
use App\Models\GraduationCriteria;
use Illuminate\Database\Seeder;

class GraduationCriteriaSeeder extends Seeder
{
    public function run(): void
    {
        // ค้นหา ID ของหมวดจิตอาสา หรือหมวดอื่นๆ
        $volunteerCat = ActivityCategory::where('name', 'like', '%จิตอาสา%')->first();
        $volunteerKey = $volunteerCat ? (string) $volunteerCat->id : 'volunteer';

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
                    'university' => 40.0, // กิจกรรมระดับมหาวิทยาลัย 40 ชม.
                    'faculty'    => 40.0, // กิจกรรมระดับคณะ/สาขา 40 ชม.
                ],
                'category_requirements' => [
                    $volunteerKey => 20.0, // กิจกรรมจิตอาสา 20 ชม.
                ],
                'require_all_mandatory_activities' => true,
                'is_default'           => true,
                'is_active'            => true,
                'description'          => 'ข้อกำหนดกิจกรรมพัฒนานักศึกษาเพื่อการสำเร็จการศึกษา: บังคับมหาวิทยาลัย 40 ชม., กิจกรรมคณะ 40 ชม., จิตอาสา 20 ชม. รวมไม่น้อยกว่า 100 ชม.',
            ]
        );
    }
}
