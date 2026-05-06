<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Registration;
use App\Services\ActivityStatusService;
use Illuminate\Http\Request;

/**
 * คอนโทรลเลอร์กิจกรรม (ฝั่งนักศึกษา)
 * แสดงรายการกิจกรรมทั้งหมด และรายละเอียดแต่ละกิจกรรม
 */
class ActivityController extends Controller
{
    /** รับ service คำนวณสถานะกิจกรรมผ่าน dependency injection */
    public function __construct(private ActivityStatusService $statusService)
    {
    }

    /**
     * แสดงรายการกิจกรรมทั้งหมด
     * รองรับการกรองตาม: สถานะ, หมวดหมู่, บังคับ, ค้นหาชื่อ/สถานที่
     */
    public function index(Request $request)
    {
        $categories = ActivityCategory::all();

        $activities = Activity::with(['category', 'registrations'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->category, fn($q) => $q->where('category_id', $request->category))
            ->when($request->mandatory, fn($q) => $q->where('is_mandatory', true))
            ->when($request->search, fn($q) => $q->where(function ($query) use ($request) {
                $query->where('title', 'like', "%{$request->search}%")
                      ->orWhere('location', 'like', "%{$request->search}%");
            }))
            ->when($request->scope, fn($q) => $q->where('scope', $request->scope))
            ->where('status', '!=', 'cancelled')
            ->orderBy('activity_date')
            ->paginate(12)
            ->withQueryString();

        $registeredActivityIds = [];
        $attendedActivityIds = [];
        if (auth()->check()) {
            $registeredActivityIds = Registration::where('user_id', auth()->id())
                ->whereIn('status', ['pending', 'approved'])
                ->pluck('activity_id')
                ->toArray();
            $attendedActivityIds = Attendance::where('user_id', auth()->id())
                ->where('status', 'approved')
                ->pluck('activity_id')
                ->toArray();
        }

        $geoActivities = Activity::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status', '!=', 'cancelled')
            ->get()
            ->map(fn($a) => [
                'id'        => $a->id,
                'title'     => $a->title,
                'location'  => $a->location,
                'lat'       => (float) $a->latitude,
                'lng'       => (float) $a->longitude,
                'date'      => $a->activity_date->format('d/m/Y'),
                'start'     => $a->start_time,
                'end'       => $a->end_time,
                'hours'     => $a->activity_hours,
                'image'     => $a->image_path ? asset('storage/' . $a->image_path) : null,
                'url'       => route('activities.show', $a->id),
            ]);

        return view('activities.index', compact('activities', 'categories', 'registeredActivityIds', 'attendedActivityIds', 'geoActivities'));
    }

    /**
     * แสดงรายละเอียดกิจกรรม
     * อัปเดตสถานะ + ดึงข้อมูลการลงทะเบียน/เข้าร่วมของผู้ใช้ปัจจุบัน
     */
    public function show($id)
    {
        $activity = Activity::with(['category', 'registrations', 'creator'])->findOrFail($id);
        $this->statusService->updateStatus($activity);

        // ดึงข้อมูลการลงทะเบียนและการเข้าร่วมของผู้ใช้ปัจจุบัน (ถ้าล็อกอินแล้ว)
        $userRegistration = null;
        $userAttendance = null;
        if (auth()->check()) {
            $userRegistration = $activity->registrations()
                ->where('user_id', auth()->id())
                ->first();
            $userAttendance = $activity->attendances()
                ->where('user_id', auth()->id())
                ->first();
        }

        return view('activities.show', compact('activity', 'userRegistration', 'userAttendance'));
    }
}
