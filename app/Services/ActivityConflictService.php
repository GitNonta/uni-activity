<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service สำหรับตรวจสอบและตรวจจับการจัดกิจกรรมชนกัน (สถานที่และเวลาทับซ้อน)
 * พร้อมจัดเตรียมข้อมูล Event Feed สำหรับ FullCalendar v6
 */
class ActivityConflictService
{
    /**
     * ปรับรูปแบบชื่อสถานที่ให้เป็นมาตรฐานเพื่อการเปรียบเทียบที่แม่นยำ
     * (ตัดช่องว่างซ้ำซ้อน, ตัวพิมพ์เล็ก, ลบช่องว่างหน้าหลัง)
     */
    public function normalizeLocation(string $location): string
    {
        $loc = mb_strtolower(trim($location));
        return preg_replace('/\s+/u', ' ', $loc) ?? $loc;
    }

    /**
     * ตรวจสอบว่าช่วงเวลา 2 ช่วงซ้อนทับกันหรือไม่
     */
    public function areTimesOverlapping(?string $startA, ?string $endA, ?string $startB, ?string $endB): bool
    {
        // หากฝั่งใดฝั่งหนึ่งไม่ได้ระบุเวลา ให้ถือว่ากินเวลาทั้งวัน จึงทับซ้อนกัน
        if (empty($startA) || empty($endA) || empty($startB) || empty($endB)) {
            return true;
        }

        // แปลงเวลาเป็นรูปแบบ H:i เพื่อเปรียบเทียบ
        $sA = substr($startA, 0, 5);
        $eA = substr($endA, 0, 5);
        $sB = substr($startB, 0, 5);
        $eB = substr($endB, 0, 5);

        // ช่วงเวลาทับซ้อนเมื่อ StartA < EndB และ EndA > StartB
        return ($sA < $eB) && ($eA > $sB);
    }

    /**
     * ตรวจสอบว่าวันที่ 2 ช่วงซ้อนทับกันหรือไม่
     */
    public function areDateRangesOverlapping(
        string $startDateA,
        string $endDateA,
        string $startDateB,
        string $endDateB
    ): bool {
        return ($startDateA <= $endDateB) && ($endDateA >= $startDateB);
    }

    /**
     * ตรวจสอบว่ากิจกรรมสองรายการมีความขัดแย้งกันหรือไม่
     */
    public function areActivitiesConflicting(Activity $a, Activity $b): bool
    {
        if ($a->id === $b->id) {
            return false;
        }

        if ($a->status === 'cancelled' || $b->status === 'cancelled') {
            return false;
        }

        $locA = $this->normalizeLocation($a->location ?? '');
        $locB = $this->normalizeLocation($b->location ?? '');

        if ($locA === '' || $locB === '' || $locA !== $locB) {
            return false;
        }

        $dateStartA = $a->activity_date?->toDateString();
        $dateEndA = $a->end_date?->toDateString() ?? $dateStartA;

        $dateStartB = $b->activity_date?->toDateString();
        $dateEndB = $b->end_date?->toDateString() ?? $dateStartB;

        if (!$dateStartA || !$dateStartB) {
            return false;
        }

        if (!$this->areDateRangesOverlapping($dateStartA, $dateEndA, $dateStartB, $dateEndB)) {
            return false;
        }

        // ตรวจสอบระดับวัน หากเป็น multiday และมีตารางรายวัน (ActivityDay)
        $aDays = $a->days;
        $bDays = $b->days;

        if ($aDays && $aDays->isNotEmpty() && $bDays && $bDays->isNotEmpty()) {
            foreach ($aDays as $dayA) {
                $dayDateA = $dayA->date?->toDateString();
                foreach ($bDays as $dayB) {
                    $dayDateB = $dayB->date?->toDateString();
                    if ($dayDateA === $dayDateB) {
                        $startA = $dayA->start_time ?? $a->start_time;
                        $endA = $dayA->end_time ?? $a->end_time;
                        $startB = $dayB->start_time ?? $b->start_time;
                        $endB = $dayB->end_time ?? $b->end_time;

                        if ($this->areTimesOverlapping($startA, $endA, $startB, $endB)) {
                            return true;
                        }
                    }
                }
            }
            return false;
        }

        // เปรียบเทียบช่วงเวลาทั่วไป
        return $this->areTimesOverlapping($a->start_time, $a->end_time, $b->start_time, $b->end_time);
    }

    /**
     * ตรวจสอบว่าพารามิเตอร์ของกิจกรรมใหม่/แก้ไข ชนกับกิจกรรมที่มีอยู่หรือไม่
     * (ใช้สำหรับ Live AJAX Validation ในหน้า Create/Edit)
     *
     * @return array{has_conflict: bool, conflict_count: int, conflicts: array<int, array<string, mixed>>, message: string}
     */
    public function checkConflict(
        string $location,
        string $activityDate,
        ?string $startTime = null,
        ?string $endTime = null,
        ?int $excludeActivityId = null,
        ?string $endDate = null
    ): array {
        $normalizedLoc = $this->normalizeLocation($location);
        if ($normalizedLoc === '') {
            return [
                'has_conflict' => false,
                'conflict_count' => 0,
                'conflicts' => [],
                'message' => 'ยังไม่ได้ระบุสถานที่',
            ];
        }

        $startDateStr = Carbon::parse($activityDate)->toDateString();
        $endDateStr = $endDate ? Carbon::parse($endDate)->toDateString() : $startDateStr;

        // ดึงกิจกรรมที่อาจซ้อนทับช่วงวันที่
        $candidates = Activity::with(['category', 'creator', 'days'])
            ->where('status', '!=', 'cancelled')
            ->when($excludeActivityId, fn($q) => $q->where('id', '!=', $excludeActivityId))
            ->where(function ($q) use ($startDateStr, $endDateStr) {
                $q->where(function ($sub) use ($startDateStr, $endDateStr) {
                    $sub->whereDate('activity_date', '<=', $endDateStr)
                        ->where(function ($inner) use ($startDateStr) {
                            $inner->whereDate('end_date', '>=', $startDateStr)
                                ->orWhere(function ($single) use ($startDateStr) {
                                    $single->whereNull('end_date')
                                        ->whereDate('activity_date', '>=', $startDateStr);
                                });
                        });
                });
            })
            ->get();

        $conflicts = [];

        foreach ($candidates as $cand) {
            $candLoc = $this->normalizeLocation($cand->location ?? '');
            if ($candLoc !== $normalizedLoc) {
                continue;
            }

            $candStart = $cand->activity_date?->toDateString();
            $candEnd = $cand->end_date?->toDateString() ?? $candStart;

            if (!$candStart) {
                continue;
            }

            if (!$this->areDateRangesOverlapping($startDateStr, $endDateStr, $candStart, $candEnd)) {
                continue;
            }

            // ตรวจสอบเวลา
            $isOverlap = false;

            // กรณีเปรียบเทียบกับกิจกรรมหลายวัน
            if ($cand->days && $cand->days->isNotEmpty()) {
                foreach ($cand->days as $d) {
                    $dDate = $d->date?->toDateString();
                    if ($dDate >= $startDateStr && $dDate <= $endDateStr) {
                        $cStart = $d->start_time ?? $cand->start_time;
                        $cEnd = $d->end_time ?? $cand->end_time;
                        if ($this->areTimesOverlapping($startTime, $endTime, $cStart, $cEnd)) {
                            $isOverlap = true;
                            break;
                        }
                    }
                }
            } else {
                $isOverlap = $this->areTimesOverlapping($startTime, $endTime, $cand->start_time, $cand->end_time);
            }

            if ($isOverlap) {
                $dateDisplay = $cand->activity_date?->format('d/m/Y') ?? '-';
                if ($cand->is_multiday && $cand->end_date) {
                    $dateDisplay .= ' - ' . $cand->end_date->format('d/m/Y');
                }

                $timeDisplay = ($cand->start_time ? substr($cand->start_time, 0, 5) : '00:00')
                    . ' - ' . ($cand->end_time ? substr($cand->end_time, 0, 5) : '23:59') . ' น.';

                $conflicts[] = [
                    'id'            => $cand->id,
                    'title'         => $cand->title,
                    'location'      => $cand->location,
                    'date_display'  => $dateDisplay,
                    'time_display'  => $timeDisplay,
                    'status'        => $cand->computed_status,
                    'category'      => $cand->category?->name ?? 'ทั่วไป',
                    'creator'       => $cand->creator?->name ?? 'เจ้าหน้าที่',
                    'view_url'      => route('activities.show', $cand),
                    'edit_url'      => route('admin.activities.edit', $cand),
                ];
            }
        }

        $count = count($conflicts);

        return [
            'has_conflict'   => $count > 0,
            'conflict_count' => $count,
            'conflicts'      => $conflicts,
            'message'        => $count > 0
                ? "พบการจัดกิจกรรมซ้ำซ้อนกัน {$count} รายการในสถานที่และวันเวลาเดียวกัน!"
                : "สถานที่ว่างพร้อมจัดกิจกรรมในช่วงเวลาที่เลือก",
        ];
    }

    /**
     * ดึงกิจกรรมทั้งหมดตาม Filter และระบุความขัดแย้งของแต่ละรายการสำหรับ FullCalendar Feed
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarEvents(array $filters = []): array
    {
        $startStr = $filters['start'] ?? now()->startOfMonth()->subMonths(1)->toDateString();
        $endStr = $filters['end'] ?? now()->endOfMonth()->addMonths(3)->toDateString();

        $startDate = Carbon::parse($startStr)->toDateString();
        $endDate = Carbon::parse($endStr)->toDateString();

        $query = Activity::with(['category', 'creator', 'days', 'registrations'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($sub) use ($startDate, $endDate) {
                    $sub->whereDate('activity_date', '<=', $endDate)
                        ->where(function ($inner) use ($startDate) {
                            $inner->whereDate('end_date', '>=', $startDate)
                                ->orWhere(function ($single) use ($startDate) {
                                    $single->whereNull('end_date')
                                        ->whereDate('activity_date', '>=', $startDate);
                                });
                        });
                });
            });

        // Filter ตาม Category
        if (!empty($filters['category_id']) && is_numeric($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        // Filter ตาม Status
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Filter ตามสถานที่
        if (!empty($filters['location'])) {
            $query->where('location', 'like', '%' . trim((string) $filters['location']) . '%');
        }

        $activities = $query->orderBy('activity_date')->get();

        // คำนวณ Conflict Matrix สำหรับกิจกรรมทั้งหมดในชุดผลลัพธ์
        $conflictMap = []; // [activity_id => [conflicting_act_info, ...]]

        /** @var Activity $actA */
        foreach ($activities as $actA) {
            if ($actA->status === 'cancelled') {
                continue;
            }

            foreach ($activities as $actB) {
                if ($actA->id >= $actB->id || $actB->status === 'cancelled') {
                    continue;
                }

                if ($this->areActivitiesConflicting($actA, $actB)) {
                    $infoB = [
                        'id'           => $actB->id,
                        'title'        => $actB->title,
                        'location'     => $actB->location,
                        'time_display' => ($actB->start_time ? substr($actB->start_time, 0, 5) : '00:00') . ' - ' . ($actB->end_time ? substr($actB->end_time, 0, 5) : '23:59'),
                        'edit_url'     => route('admin.activities.edit', $actB),
                        'view_url'     => route('activities.show', $actB),
                    ];

                    $infoA = [
                        'id'           => $actA->id,
                        'title'        => $actA->title,
                        'location'     => $actA->location,
                        'time_display' => ($actA->start_time ? substr($actA->start_time, 0, 5) : '00:00') . ' - ' . ($actA->end_time ? substr($actA->end_time, 0, 5) : '23:59'),
                        'edit_url'     => route('admin.activities.edit', $actA),
                        'view_url'     => route('activities.show', $actA),
                    ];

                    $conflictMap[$actA->id][] = $infoB;
                    $conflictMap[$actB->id][] = $infoA;
                }
            }
        }

        // หากผู้ใช้เลือก "เฉพาะกิจกรรมที่ชนกัน"
        if (!empty($filters['only_conflicts']) && ($filters['only_conflicts'] === '1' || $filters['only_conflicts'] === true)) {
            $activities = $activities->filter(fn(Activity $act) => isset($conflictMap[$act->id]) && count($conflictMap[$act->id]) > 0);
        }

        return $activities->map(function (Activity $act) use ($conflictMap) {
            $hasConflict = isset($conflictMap[$act->id]) && count($conflictMap[$act->id]) > 0;
            $conflictCount = $hasConflict ? count($conflictMap[$act->id]) : 0;
            $conflicts = $conflictMap[$act->id] ?? [];

            $dateStr = $act->activity_date?->format('Y-m-d') ?? now()->format('Y-m-d');
            $endDateStr = $act->end_date ? $act->end_date->format('Y-m-d') : $dateStr;

            $startTimeStr = $act->start_time ? substr($act->start_time, 0, 5) : '08:00';
            $endTimeStr = $act->end_time ? substr($act->end_time, 0, 5) : '17:00';

            // สีของ Event บนปฏิทิน
            if ($hasConflict) {
                $color = '#dc2626'; // สีแดงเข้ม: ชนกัน / แย่งสถานที่
                $borderColor = '#991b1b';
            } elseif ($act->status === 'cancelled') {
                $color = '#64748b'; // สีเทา: ยกเลิก
                $borderColor = '#475569';
            } elseif ($act->computed_status === 'ongoing') {
                $color = '#059669'; // เขียวเข้ม: กำลังจัด
                $borderColor = '#047857';
            } elseif ($act->computed_status === 'open') {
                $color = '#2563eb'; // น้ำเงิน: เปิดรับสมัคร
                $borderColor = '#1d4ed8';
            } elseif ($act->computed_status === 'upcoming') {
                $color = '#d97706'; // ส้มอำพัน: กำลังจะเปิด
                $borderColor = '#b45309';
            } elseif ($act->computed_status === 'done') {
                $color = '#4b5563'; // เทาดำ: เสร็จสิ้น
                $borderColor = '#374151';
            } else {
                $color = '#4f46e5'; // ม่วงคราม
                $borderColor = '#4338ca';
            }

            // จัดรูปแบบ Start / End สำหรับ FullCalendar
            $startIso = $dateStr . 'T' . $startTimeStr . ':00';
            $endIso = $endDateStr . 'T' . $endTimeStr . ':00';

            $regCount = $act->registrations ? $act->registrations->count() : $act->getRegisteredCount();

            return [
                'id'              => (string) $act->id,
                'title'           => $act->title,
                'start'           => $startIso,
                'end'             => $endIso,
                'allDay'          => empty($act->start_time) && empty($act->end_time),
                'backgroundColor' => $color,
                'borderColor'     => $borderColor,
                'textColor'       => '#ffffff',
                'extendedProps'   => [
                    'location'         => $act->location ?? '-',
                    'hours'            => (float) $act->activity_hours,
                    'category'         => $act->category?->name ?? 'ทั่วไป',
                    'category_id'      => $act->category_id,
                    'status'           => $act->computed_status,
                    'raw_status'       => $act->status,
                    'is_multiday'      => (bool) $act->is_multiday,
                    'registered_count' => $regCount,
                    'max_participants' => $act->max_participants,
                    'creator_name'     => $act->creator?->name ?? 'ระบบ',
                    'start_time'       => $startTimeStr,
                    'end_time'         => $endTimeStr,
                    'is_conflict'      => $hasConflict,
                    'conflict_count'   => $conflictCount,
                    'conflicts'        => $conflicts,
                    'view_url'         => route('activities.show', $act),
                    'edit_url'         => route('admin.activities.edit', $act),
                    'participants_url' => route('admin.activities.participants', $act),
                ],
            ];
        })->values()->toArray();
    }

    /**
     * ดึงข้อมูลสรุปภาพรวมปฏิทินและจำนวนรายการที่ชนกัน
     *
     * @return array{total_month: int, upcoming: int, ongoing: int, conflicts_count: int, unique_locations: array<int, string>}
     */
    public function getConflictSummaryStats(): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();

        $monthActivities = Activity::where('status', '!=', 'cancelled')
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereDate('activity_date', '<=', $endOfMonth)
                    ->where(function ($inner) use ($startOfMonth) {
                        $inner->whereDate('end_date', '>=', $startOfMonth)
                            ->orWhere(function ($s) use ($startOfMonth) {
                                $s->whereNull('end_date')->whereDate('activity_date', '>=', $startOfMonth);
                            });
                    });
            })
            ->get();

        $totalMonth = $monthActivities->count();
        $upcoming = Activity::whereIn('status', ['upcoming', 'open'])->count();
        $ongoing = Activity::where('status', 'ongoing')->count();

        // ตรวจสอบว่าในบรรดากิจกรรมเดือนนี้และอนาคต มีกี่กิจกรรมที่ชนกัน
        $activeActivities = Activity::with(['category', 'creator', 'days'])
            ->where('status', '!=', 'cancelled')
            ->whereDate('activity_date', '>=', $startOfMonth)
            ->get();

        $conflictingIds = [];
        $locSet = [];

        foreach ($activeActivities as $actA) {
            if (!empty($actA->location)) {
                $locSet[$this->normalizeLocation($actA->location)] = trim($actA->location);
            }

            foreach ($activeActivities as $actB) {
                if ($actA->id >= $actB->id) {
                    continue;
                }
                if ($this->areActivitiesConflicting($actA, $actB)) {
                    $conflictingIds[$actA->id] = true;
                    $conflictingIds[$actB->id] = true;
                }
            }
        }

        return [
            'total_month'      => $totalMonth,
            'upcoming'         => $upcoming,
            'ongoing'          => $ongoing,
            'conflicts_count'  => count($conflictingIds),
            'unique_locations' => array_values($locSet),
        ];
    }
}
