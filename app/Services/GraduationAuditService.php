<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\AdminAuditLog;
use App\Models\Attendance;
use App\Models\GraduationCriteria;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service สำหรับการตรวจสอบคุณสมบัติการสำเร็จการศึกษา (Graduation Activity Audit)
 */
class GraduationAuditService
{
    public function __construct(
        private readonly LineService $lineService
    ) {}

    /**
     * ดึงเกณฑ์การสำเร็จการศึกษาที่ตรงกับคณะ/สาขาของนักศึกษา
     */
    public function getActiveCriteriaForStudent(User $student): GraduationCriteria
    {
        // 1. หาเกณฑ์เฉพาะคณะและสาขา
        if (!empty($student->faculty) && !empty($student->department)) {
            $specific = GraduationCriteria::where('faculty', $student->faculty)
                ->where('department', $student->department)
                ->where('is_active', true)
                ->first();
            if ($specific) {
                return $specific;
            }
        }

        // 2. หาเกณฑ์เฉพาะคณะ
        if (!empty($student->faculty)) {
            $facultyCriteria = GraduationCriteria::where('faculty', $student->faculty)
                ->whereNull('department')
                ->where('is_active', true)
                ->first();
            if ($facultyCriteria) {
                return $facultyCriteria;
            }
        }

        // 3. หาเกณฑ์มาตรฐานเริ่มต้น (Default University-wide)
        $defaultCriteria = GraduationCriteria::getDefault();
        if ($defaultCriteria) {
            return $defaultCriteria;
        }

        // 4. Fallback สร้าง instance ชั่วคราวกรณีฐานข้อมูลยังไม่มีข้อมูล
        return new GraduationCriteria([
            'name'                 => 'เกณฑ์กิจกรรมมาตรฐานมหาวิทยาลัย',
            'code'                 => 'CRITERIA-FALLBACK',
            'min_total_hours'      => 100.0,
            'min_mandatory_hours'  => 10.0,
            'scope_requirements'   => ['university' => 40.0, 'faculty' => 40.0],
            'category_requirements' => ['volunteer' => 20.0],
            'require_all_mandatory_activities' => true,
            'is_default'           => true,
            'is_active'            => true,
        ]);
    }

    /**
     * ตรวจสอบสถานะการสะสมชั่วโมงและผลการประเมินการสำเร็จการศึกษาของนักศึกษาแบบละเอียด
     *
     * @return array<string, mixed>
     */
    public function auditStudent(User $student, ?GraduationCriteria $criteria = null): array
    {
        $criteria = $criteria ?? $this->getActiveCriteriaForStudent($student);

        // ดึงประวัติการเข้าร่วมกิจกรรมที่ได้รับการอนุมัติ (Status: approved) ทั้งหมด
        $attendances = Attendance::with(['activity.category'])
            ->where('user_id', $student->id)
            ->where('status', 'approved')
            ->orderBy('checked_in_at', 'desc')
            ->get();

        // 1. คำนวณชั่วโมงรวม
        $totalHours = (float) $attendances->sum(fn(Attendance $a) => (float) ($a->activity?->activity_hours ?? 0));
        $minTotalHours = (float) $criteria->min_total_hours;
        $totalPassed = $totalHours >= $minTotalHours;

        $missingRequirements = [];

        if (!$totalPassed) {
            $deficit = $minTotalHours - $totalHours;
            $missingRequirements[] = "ชั่วโมงรวมยังไม่ครบเกณฑ์ (มี {$totalHours} / ขาดอีก {$deficit} ชม.)";
        }

        // 2. ตรวจสอบชั่วโมงแยกตามขอบเขต (Scope: university, faculty/department)
        $scopeReqs = $criteria->scope_requirements ?? ['university' => 40.0, 'faculty' => 40.0];
        $scopeAudit = [];
        $allScopesPassed = true;

        foreach ($scopeReqs as $scopeKey => $requiredHours) {
            $requiredHours = (float) $requiredHours;
            $earned = 0.0;

            if ($scopeKey === 'university') {
                $earned = (float) $attendances->filter(fn(Attendance $a) => ($a->activity?->scope ?? '') === 'university')
                    ->sum(fn(Attendance $a) => (float) ($a->activity?->activity_hours ?? 0));
                $label = 'กิจกรรมระดับมหาวิทยาลัย (ส่วนกลาง)';
            } elseif ($scopeKey === 'faculty') {
                $earned = (float) $attendances->filter(fn(Attendance $a) => in_array($a->activity?->scope ?? '', ['faculty', 'department'], true))
                    ->sum(fn(Attendance $a) => (float) ($a->activity?->activity_hours ?? 0));
                $label = 'กิจกรรมระดับคณะ/สาขาวิชา';
            } else {
                $earned = (float) $attendances->filter(fn(Attendance $a) => ($a->activity?->scope ?? '') === $scopeKey)
                    ->sum(fn(Attendance $a) => (float) ($a->activity?->activity_hours ?? 0));
                $label = "กิจกรรมระดับ " . ucfirst($scopeKey);
            }

            $passed = $earned >= $requiredHours;
            $deficit = $passed ? 0.0 : ($requiredHours - $earned);

            if (!$passed) {
                $allScopesPassed = false;
                $missingRequirements[] = "ยังขาด{$label} อีก {$deficit} ชม. (มี {$earned} / {$requiredHours})";
            }

            $scopeAudit[$scopeKey] = [
                'name'     => $label,
                'earned'   => $earned,
                'required' => $requiredHours,
                'passed'   => $passed,
                'deficit'  => $deficit,
            ];
        }

        // 3. ตรวจสอบชั่วโมงแยกตามหมวดหมู่ (Category: volunteer, academic, etc.)
        $catReqs = $criteria->category_requirements ?? [];
        $categoryAudit = [];
        $allCategoriesPassed = true;

        // ดึงหมวดหมู่ทั้งหมดสำหรับ mapping
        $allCategories = ActivityCategory::all()->keyBy('id');

        foreach ($catReqs as $catKey => $requiredHours) {
            $requiredHours = (float) $requiredHours;
            $catName = 'หมวดหมู่กิจกรรม';
            $earned = 0.0;

            if (is_numeric($catKey)) {
                $categoryModel = $allCategories->get((int) $catKey);
                $catName = $categoryModel ? $categoryModel->name : "หมวดหมู่ #{$catKey}";
                $earned = (float) $attendances->filter(fn(Attendance $a) => $a->activity?->category_id === (int) $catKey)
                    ->sum(fn(Attendance $a) => (float) ($a->activity?->activity_hours ?? 0));
            } elseif ($catKey === 'volunteer' || str_contains(strtolower((string) $catKey), 'volunteer')) {
                $catName = 'กิจกรรมจิตอาสาและบำเพ็ญประโยชน์';
                $earned = (float) $attendances->filter(function (Attendance $a) {
                    $name = $a->activity?->category?->name ?? '';
                    return str_contains($name, 'จิตอาสา') || str_contains($name, 'บำเพ็ญประโยชน์');
                })->sum(fn(Attendance $a) => (float) ($a->activity?->activity_hours ?? 0));
            } else {
                $earned = (float) $attendances->filter(function (Attendance $a) use ($catKey) {
                    $name = $a->activity?->category?->name ?? '';
                    return str_contains(strtolower($name), strtolower((string) $catKey));
                })->sum(fn(Attendance $a) => (float) ($a->activity?->activity_hours ?? 0));
                $catName = "หมวด " . ucfirst((string) $catKey);
            }

            $passed = $earned >= $requiredHours;
            $deficit = $passed ? 0.0 : ($requiredHours - $earned);

            if (!$passed) {
                $allCategoriesPassed = false;
                $missingRequirements[] = "ยังขาด{$catName} อีก {$deficit} ชม. (มี {$earned} / {$requiredHours})";
            }

            $categoryAudit[$catKey] = [
                'name'     => $catName,
                'earned'   => $earned,
                'required' => $requiredHours,
                'passed'   => $passed,
                'deficit'  => $deficit,
            ];
        }

        // 4. ตรวจสอบกิจกรรมภาคบังคับ (Mandatory Activities)
        $mandatoryEarned = (float) $attendances->filter(fn(Attendance $a) => (bool) ($a->activity?->is_mandatory ?? false))
            ->sum(fn(Attendance $a) => (float) ($a->activity?->activity_hours ?? 0));
        $minMandatory = (float) $criteria->min_mandatory_hours;
        $mandatoryPassed = $mandatoryEarned >= $minMandatory;
        $mandatoryDeficit = $mandatoryPassed ? 0.0 : ($minMandatory - $mandatoryEarned);

        if (!$mandatoryPassed && $minMandatory > 0) {
            $missingRequirements[] = "ยังขาดชั่วโมงกิจกรรมภาคบังคับอีก {$mandatoryDeficit} ชม. (มี {$mandatoryEarned} / {$minMandatory})";
        }

        // สรุปสถานะการสำเร็จการศึกษา (ผ่านเกณฑ์ทั้งหมดหรือไม่)
        $isEligible = $totalPassed && $allScopesPassed && $allCategoriesPassed && $mandatoryPassed;

        return [
            'student'              => $student,
            'criteria'             => $criteria,
            'is_eligible'          => $isEligible,
            'total_hours'          => $totalHours,
            'min_total_hours'      => $minTotalHours,
            'total_passed'         => $totalPassed,
            'scope_audit'          => $scopeAudit,
            'category_audit'       => $categoryAudit,
            'mandatory_audit'      => [
                'earned'   => $mandatoryEarned,
                'required' => $minMandatory,
                'passed'   => $mandatoryPassed,
                'deficit'  => $mandatoryDeficit,
            ],
            'missing_requirements' => $missingRequirements,
            'attendance_count'     => $attendances->count(),
            'attendances'          => $attendances,
        ];
    }

    /**
     * ตรวจสอบสถานะและประมวลผล Metrics สำหรับนักศึกษาในระบบ (โดยเฉพาะชั้นปีที่ 4)
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getGraduationMetrics(array $filters = []): array
    {
        $yearFilter = $filters['year'] ?? '4'; // ค่าเริ่มต้น: นักศึกษาชั้นปีที่ 4 (ปีสุดท้าย)
        $facultyFilter = $filters['faculty'] ?? null;
        $departmentFilter = $filters['department'] ?? null;
        $statusFilter = $filters['status'] ?? null; // 'passed', 'deficit', or null
        $search = $filters['search'] ?? null;

        $query = User::where('role', 'student')->where('is_active', true);

        if ($yearFilter !== 'all' && is_numeric($yearFilter)) {
            $query->where('year', (int) $yearFilter);
        }

        if (!empty($facultyFilter)) {
            $query->where('faculty', $facultyFilter);
        }

        if (!empty($departmentFilter)) {
            $query->where('department', $departmentFilter);
        }

        if (!empty($search)) {
            $keyword = '%' . trim((string) $search) . '%';
            $query->where(function (Builder $q) use ($keyword) {
                $q->where('student_id', 'like', $keyword)
                  ->orWhere('full_name', 'like', $keyword)
                  ->orWhere('email', 'like', $keyword);
            });
        }

        $students = $query->orderBy('student_id')->get();

        $defaultCriteria = GraduationCriteria::getDefault() ?? $this->getActiveCriteriaForStudent(new User());

        $auditedStudents = collect();
        $totalHoursSum = 0.0;
        $eligibleCount = 0;
        $deficitCount  = 0;

        foreach ($students as $student) {
            $audit = $this->auditStudent($student, $defaultCriteria);
            $totalHoursSum += (float) $audit['total_hours'];

            if ($audit['is_eligible']) {
                $eligibleCount++;
            } else {
                $deficitCount++;
            }

            // ถ้ามี filter สถานะ
            if ($statusFilter === 'passed' && !$audit['is_eligible']) {
                continue;
            }
            if ($statusFilter === 'deficit' && $audit['is_eligible']) {
                continue;
            }

            $auditedStudents->push($audit);
        }

        $totalCount = $students->count();
        $eligiblePercentage = $totalCount > 0 ? round(($eligibleCount / $totalCount) * 100, 1) : 0.0;
        $deficitPercentage  = $totalCount > 0 ? round(($deficitCount / $totalCount) * 100, 1) : 0.0;
        $averageHours       = $totalCount > 0 ? round($totalHoursSum / $totalCount, 1) : 0.0;

        // ดึงรายการคณะและสาขาวิชาที่มีอยู่ในระบบสำหรับ Dropdown ตัวกรอง
        $faculties = User::where('role', 'student')->whereNotNull('faculty')->distinct()->pluck('faculty')->filter()->values();
        $departments = User::where('role', 'student')->whereNotNull('department')->distinct()->pluck('department')->filter()->values();

        return [
            'total_students'      => $totalCount,
            'eligible_count'      => $eligibleCount,
            'eligible_percentage' => $eligiblePercentage,
            'deficit_count'       => $deficitCount,
            'deficit_percentage'  => $deficitPercentage,
            'average_hours'       => $averageHours,
            'audited_students'    => $auditedStudents,
            'filters'             => [
                'year'       => $yearFilter,
                'faculty'    => $facultyFilter,
                'department' => $departmentFilter,
                'status'     => $statusFilter,
                'search'     => $search,
            ],
            'faculties'           => $faculties,
            'departments'         => $departments,
            'criteria'            => $defaultCriteria,
        ];
    }

    /**
     * ส่งข้อความแจ้งเตือนเร่งรัดชั่วโมงกิจกรรม (Graduation Deficit Alert) ทาง In-App และ LINE
     *
     * @param array<string, mixed> $auditResult
     * @return array{in_app_sent: bool, line_sent: bool}
     */
    public function sendDeficitAlert(User $student, array $auditResult, User $sender): array
    {
        $totalHours = number_format((float) ($auditResult['total_hours'] ?? 0), 1);
        $minHours   = number_format((float) ($auditResult['min_total_hours'] ?? 100), 1);
        $missing    = $auditResult['missing_requirements'] ?? [];
        $missingStr = !empty($missing) ? implode(', ', $missing) : 'ชั่วโมงกิจกรรมรวมยังไม่ครบเกณฑ์';

        $title = "แจ้งเตือนการสำเร็จการศึกษา: ชั่วโมงกิจกรรมยังไม่ครบตามเกณฑ์ ({$totalHours}/{$minHours} ชม.)";
        $message = "ระบบตรวจสอบพบว่าคุณมีชั่วโมงกิจกรรมสะสม {$totalHours} ชม. จากเกณฑ์ขั้นต่ำ {$minHours} ชม. และยังไม่ผ่านเกณฑ์ดังนี้: {$missingStr} กรุณาตรวจสอบและเข้าร่วมกิจกรรมเพิ่มเติมเพื่อความพร้อมในการสำเร็จการศึกษา";

        // 1. ส่ง In-App Notification
        Notification::create([
            'user_id' => $student->id,
            'title'   => "[แจ้งเตือนจบการศึกษา] " . $title,
            'message' => $message,
            'url'     => route('student.graduation.status'),
            'type'    => 'graduation_deficit',
            'is_read' => false,
        ]);
        $inAppSent = true;

        // 2. ส่ง LINE Push Notification
        $lineSent = false;
        if (!empty($student->line_user_id) && $student->line_notify_enabled) {
            try {
                $flex = $this->lineService->buildGraduationDeficitMessage($student, $auditResult);
                $this->lineService->pushMessage($student->line_user_id, [$flex]);
                $lineSent = true;
            } catch (\Throwable $e) {
                Log::warning("Failed to send LINE graduation deficit message to User #{$student->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 3. บันทึก Admin Audit Log
        try {
            AdminAuditLog::create([
                'user_id'    => $sender->id,
                'action'     => 'graduation_deficit_alert_sent',
                'model_type' => User::class,
                'model_id'   => $student->id,
                'new_data'   => [
                    'student_id'   => $student->student_id,
                    'total_hours'  => $totalHours,
                    'missing'      => $missing,
                    'line_sent'    => $lineSent,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log admin action for deficit alert', ['error' => $e->getMessage()]);
        }

        return [
            'in_app_sent' => $inAppSent,
            'line_sent'   => $lineSent,
        ];
    }
}
