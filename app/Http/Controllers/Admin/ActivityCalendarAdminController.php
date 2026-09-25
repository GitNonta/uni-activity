<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CheckActivityConflictRequest;
use App\Models\ActivityCategory;
use App\Services\ActivityConflictService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller สำหรับหน้า Interactive Master Calendar ของเจ้าหน้าที่/ผู้ดูแลระบบ
 * รองรับ FullCalendar feed, filter กิจกรรม, และ Real-time Conflict Warning
 */
class ActivityCalendarAdminController extends Controller
{
    public function __construct(
        private readonly ActivityConflictService $conflictService
    ) {}

    /**
     * แสดงหน้าปฏิทินกิจกรรมอัจฉริยะ (Master Calendar)
     */
    public function index(Request $request): View
    {
        $stats = $this->conflictService->getConflictSummaryStats();
        $categories = ActivityCategory::orderBy('name')->get();

        return view('admin.calendar.index', [
            'stats'           => $stats,
            'categories'      => $categories,
            'uniqueLocations' => $stats['unique_locations'] ?? [],
        ]);
    }

    /**
     * JSON Endpoint: ดึงรายการกิจกรรมสำหรับ FullCalendar v6 พร้อม Conflict Flag
     */
    public function events(Request $request): JsonResponse
    {
        $filters = [
            'start'          => $request->query('start'),
            'end'            => $request->query('end'),
            'category_id'    => $request->query('category_id'),
            'status'         => $request->query('status'),
            'location'       => $request->query('location'),
            'only_conflicts' => $request->query('only_conflicts'),
        ];

        $events = $this->conflictService->getCalendarEvents($filters);

        return response()->json($events);
    }

    /**
     * AJAX Endpoint: ตรวจสอบความขัดแย้งของสถานที่และวันเวลาแบบ Real-time
     * สำหรับหน้าสร้าง/แก้ไขกิจกรรม
     */
    public function checkConflict(CheckActivityConflictRequest $request): JsonResponse
    {
        $result = $this->conflictService->checkConflict(
            location: (string) $request->validated('location'),
            activityDate: (string) $request->validated('activity_date'),
            startTime: $request->validated('start_time'),
            endTime: $request->validated('end_time'),
            excludeActivityId: $request->validated('exclude_id') ? (int) $request->validated('exclude_id') : null,
            endDate: $request->validated('end_date')
        );

        return response()->json($result);
    }
}
