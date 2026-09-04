<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\ActivityPublished;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuickStoreActivityRequest;
use App\Http\Requests\Admin\RegenerateQrRequest;
use App\Http\Requests\Admin\UpdateActivityRequest;
use App\Http\Requests\StoreActivityRequest;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityDay;
use App\Services\ImageOptimizationService;
use App\Services\ListCache;
use App\Services\QrCodeService;
use App\Traits\LogsAdminActivity;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Controller สำหรับจัดการกิจกรรม (CRUD & Core Operations ฝั่ง Admin/Staff)
 */
class ActivityAdminController extends Controller
{
    use LogsAdminActivity;

    /**
     * แสดงรายการกิจกรรมทั้งหมด รองรับกรองตามสถานะและค้นหา
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Activity::class);

        $user = Auth::user();
        $activities = Activity::with(['category', 'creator'])
            ->withCount([
                'registrations as pending_registrations_count' => fn ($q) => $q->where('status', 'pending'),
                'attendances as pending_attendances_count'     => fn ($q) => $q->where('status', 'pending'),
            ])
            ->when($user->isStaff(), function ($q) use ($user): void {
                $q->where(function ($sub) use ($user): void {
                    $sub->where('created_by', $user->id);
                    if ($user->faculty) {
                        $sub->orWhere('faculty', $user->faculty);
                    }
                });
            })
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->active()
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $completedActivities = Activity::with(['category', 'creator'])
            ->oldCompleted()
            ->orderByDesc('activity_date')
            ->paginate(12, ['*'], 'completed_page')
            ->withQueryString();

        return view('admin.activities.index', compact('activities', 'completedActivities'));
    }

    /**
     * แสดงฟอร์มสร้างกิจกรรมใหม่
     */
    public function create(): View
    {
        Gate::authorize('create', Activity::class);

        $categories = ActivityCategory::all();
        $faculties = Activity::whereNotNull('faculty')->distinct()->pluck('faculty')->sort()->values();
        $departments = Activity::whereNotNull('department')->distinct()->pluck('department')->sort()->values();

        return view('admin.activities.create', compact('categories', 'faculties', 'departments'));
    }

    /**
     * บันทึกกิจกรรมใหม่ พร้อมสร้าง QR token + อัปโหลดรูป (ถ้ามี)
     */
    public function store(
        StoreActivityRequest $request,
        QrCodeService $qrService,
        ImageOptimizationService $imageOptimizer
    ): RedirectResponse {
        Gate::authorize('create', Activity::class);

        $data = $request->validated();
        $data['created_by'] = Auth::id();
        $data['qr_token'] = $qrService->generateToken();
        $data['qr_checkout_token'] = $qrService->generateToken();
        $data['is_mandatory'] = $request->boolean('is_mandatory');
        $data['is_multiday'] = $request->boolean('is_multiday');
        $data['allow_walkin'] = $request->has('allow_walkin') ? $request->boolean('allow_walkin') : true;
        $data['require_attendance_approval'] = $request->boolean('require_attendance_approval');
        $data['require_selfie_verification'] = $request->boolean('require_selfie_verification');
        $data['require_face_scan'] = $request->has('require_face_scan') ? $request->boolean('require_face_scan') : true;

        if ($request->has('face_scan_method')) {
            $data['face_scan_method'] = $request->face_scan_method;
        }

        if (($data['scope'] ?? 'university') === 'university') {
            $data['faculty'] = null;
            $data['department'] = null;
        } elseif (($data['scope'] ?? 'university') === 'faculty') {
            $data['department'] = null;
        }

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageOptimizer->storeActivityImageAsWebp($request->file('image'));
        }

        if ($request->boolean('is_no_checkout')) {
            $data['checkout_open_at'] = null;
            $data['checkout_close_at'] = null;
        }

        // Auto-derive status from activity date & time
        $activityDate = Carbon::parse($data['activity_date']);
        $checkinOpen  = isset($data['checkin_open_at']) ? Carbon::parse($data['checkin_open_at']) : null;
        $checkinClose = isset($data['checkin_close_at']) ? Carbon::parse($data['checkin_close_at']) : null;
        $now          = now();

        if ($checkinClose && $now->greaterThan($checkinClose)) {
            $data['status'] = 'done';
        } elseif ($checkinOpen && $now->greaterThanOrEqualTo($checkinOpen)) {
            $data['status'] = 'ongoing';
        } elseif ($activityDate->isToday() || $activityDate->isPast()) {
            $data['status'] = 'open';
        } else {
            $data['status'] = 'upcoming';
        }

        $daysData = $data['days'] ?? [];
        unset($data['days']);

        $activity = DB::transaction(function () use ($data, $daysData) {
            $activity = Activity::create($data);

            if ($activity->is_multiday && !empty($daysData)) {
                $totalHours = 0.0;
                foreach ($daysData as $index => $day) {
                    if (empty($day['date'])) {
                        continue;
                    }
                    $dayDate = Carbon::parse($day['date']);
                    $dayHours = isset($day['activity_hours']) && $day['activity_hours'] !== '' ? (float)$day['activity_hours'] : 0.0;
                    $totalHours += $dayHours;

                    $activity->days()->create([
                        'day_number'        => $day['day_number'] ?? ($index + 1),
                        'date'              => $dayDate->toDateString(),
                        'start_time'        => !empty($day['start_time']) ? $day['start_time'] : null,
                        'end_time'          => !empty($day['end_time']) ? $day['end_time'] : null,
                        'activity_hours'    => $dayHours,
                        'checkin_open_at'   => !empty($day['checkin_open_at']) ? Carbon::parse($day['checkin_open_at']) : null,
                        'checkin_close_at'  => !empty($day['checkin_close_at']) ? Carbon::parse($day['checkin_close_at']) : null,
                        'checkout_open_at'  => !empty($day['checkout_open_at']) ? Carbon::parse($day['checkout_open_at']) : null,
                        'checkout_close_at' => !empty($day['checkout_close_at']) ? Carbon::parse($day['checkout_close_at']) : null,
                    ]);
                }

                if ($totalHours > 0) {
                    $activity->update(['activity_hours' => $totalHours]);
                }
            }

            return $activity;
        });

        $this->auditCreate($activity, "สร้างกิจกรรม \"{$activity->title}\"");

        // New post must be visible immediately on /activities and the map.
        ListCache::bump(ListCache::GROUP_ACTIVITIES);

        // Broadcast event เพื่อส่ง LINE notification แบบ async
        ActivityPublished::dispatch($activity);

        return redirect()->route('admin.activities.index')->with('success', 'สร้างกิจกรรมสำเร็จ!');
    }

    /**
     * แสดงรายละเอียดกิจกรรม พร้อมข้อมูลผู้ลงทะเบียน/เช็คอิน
     */
    public function show(Activity $activity): View
    {
        Gate::authorize('view', $activity);
        $activity->loadMissing(['category', 'registrations.user', 'attendances.user', 'days']);

        return view('admin.activities.show', compact('activity'));
    }

    /**
     * แสดงฟอร์มแก้ไขกิจกรรม
     */
    public function edit(Activity $activity): View
    {
        Gate::authorize('update', $activity);
        $activity->load('days');

        $categories = ActivityCategory::all();
        $faculties = Activity::whereNotNull('faculty')->distinct()->pluck('faculty')->sort()->values();
        $departments = Activity::whereNotNull('department')->distinct()->pluck('department')->sort()->values();

        return view('admin.activities.edit', compact('activity', 'categories', 'faculties', 'departments'));
    }

    /**
     * อัปเดตข้อมูลกิจกรรม ตรวจสอบ validation + อัปโหลดรูปใหม่ (ถ้ามี)
     */
    public function update(
        UpdateActivityRequest $request,
        Activity $activity,
        ImageOptimizationService $imageOptimizer
    ): RedirectResponse {
        Gate::authorize('update', $activity);

        $data = $request->validated();
        $data['is_mandatory'] = $request->boolean('is_mandatory');
        $data['is_multiday'] = $request->boolean('is_multiday');
        $data['require_face_scan'] = $request->boolean('require_face_scan');

        if ($request->has('face_scan_method')) {
            $data['face_scan_method'] = $request->face_scan_method;
        }

        $data['allow_walkin'] = $request->has('allow_walkin') ? $request->boolean('allow_walkin') : true;
        $data['require_attendance_approval'] = $request->boolean('require_attendance_approval');
        $data['require_selfie_verification'] = $request->boolean('require_selfie_verification');

        if (($data['scope'] ?? '') === 'university') {
            $data['faculty'] = null;
            $data['department'] = null;
        } elseif (($data['scope'] ?? '') === 'faculty') {
            $data['department'] = null;
        }

        $data['latitude'] = $request->filled('latitude') ? $request->latitude : null;
        $data['longitude'] = $request->filled('longitude') ? $request->longitude : null;
        $data['checkin_radius'] = $request->filled('checkin_radius') ? $request->checkin_radius : 200;

        if ($request->hasFile('image')) {
            if ($activity->image_path) {
                Storage::disk('public')->delete($activity->image_path);
            }
            $data['image_path'] = $imageOptimizer->storeActivityImageAsWebp($request->file('image'));
        }

        if ($request->boolean('is_no_checkout')) {
            $data['checkout_open_at'] = null;
            $data['checkout_close_at'] = null;
        }

        $daysData = $data['days'] ?? [];
        unset($data['days']);

        $oldValues = $activity->only(['title', 'location', 'activity_date', 'status', 'activity_hours']);

        DB::transaction(function () use ($activity, $data, $daysData) {
            $activity->update($data);

            if ($activity->is_multiday && !empty($daysData)) {
                $activity->days()->delete();
                $totalHours = 0.0;
                foreach ($daysData as $index => $day) {
                    if (empty($day['date'])) {
                        continue;
                    }
                    $dayDate = Carbon::parse($day['date']);
                    $dayHours = isset($day['activity_hours']) && $day['activity_hours'] !== '' ? (float)$day['activity_hours'] : 0.0;
                    $totalHours += $dayHours;

                    $activity->days()->create([
                        'day_number'        => $day['day_number'] ?? ($index + 1),
                        'date'              => $dayDate->toDateString(),
                        'start_time'        => !empty($day['start_time']) ? $day['start_time'] : null,
                        'end_time'          => !empty($day['end_time']) ? $day['end_time'] : null,
                        'activity_hours'    => $dayHours,
                        'checkin_open_at'   => !empty($day['checkin_open_at']) ? Carbon::parse($day['checkin_open_at']) : null,
                        'checkin_close_at'  => !empty($day['checkin_close_at']) ? Carbon::parse($day['checkin_close_at']) : null,
                        'checkout_open_at'  => !empty($day['checkout_open_at']) ? Carbon::parse($day['checkout_open_at']) : null,
                        'checkout_close_at' => !empty($day['checkout_close_at']) ? Carbon::parse($day['checkout_close_at']) : null,
                    ]);
                }

                if ($totalHours > 0) {
                    $activity->update(['activity_hours' => $totalHours]);
                }
            } elseif (!$activity->is_multiday) {
                $activity->days()->delete();
            }
        });

        $this->auditUpdate($activity, $oldValues, "แก้ไขกิจกรรม \"{$activity->title}\"");

        ListCache::bump(ListCache::GROUP_ACTIVITIES);

        return redirect()->route('admin.activities.index')->with('success', 'อัปเดตกิจกรรมสำเร็จ!');
    }

    /**
     * ลบกิจกรรม
     */
    public function destroy(Activity $activity): RedirectResponse
    {
        Gate::authorize('delete', $activity);

        $this->auditDelete($activity, "ลบกิจกรรม \"{$activity->title}\"");
        $activity->delete();

        ListCache::bump(ListCache::GROUP_ACTIVITIES);

        return redirect()->route('admin.activities.index')->with('success', 'ลบกิจกรรมสำเร็จ');
    }

    /**
     * สร้างกิจกรรมด่วน (จาก modal บน Dashboard)
     */
    public function quickStore(QuickStoreActivityRequest $request, QrCodeService $qrService): RedirectResponse
    {
        Gate::authorize('create', Activity::class);

        $data = $request->validated();
        $date = Carbon::parse($data['activity_date']);

        $activity = Activity::create(array_merge($data, [
            'max_participants'  => 50,
            'register_open_at'  => now(),
            'register_close_at' => $date->copy()->subHour(),
            'checkin_open_at'   => $date->copy()->setTimeFromTimeString($data['start_time'])->subMinutes(30),
            'checkin_close_at'  => $date->copy()->setTimeFromTimeString($data['end_time'])->addMinutes(30),
            'is_mandatory'      => false,
            'status'            => 'open',
            'created_by'        => Auth::id(),
            'qr_token'          => $qrService->generateToken(),
            'qr_checkout_token' => $qrService->generateToken(),
        ]));

        $this->auditCreate($activity, "สร้างกิจกรรมด่วน \"{$activity->title}\"");

        ListCache::bump(ListCache::GROUP_ACTIVITIES);

        return redirect()->route('admin.dashboard')->with('success', 'สร้างกิจกรรมด่วนสำเร็จ!');
    }

    /**
     * สลับเปิด/ปิดอนุญาตบันทึกกิจกรรมก่อนเวลาเช็คอิน
     */
    public function toggleEarlyCheckin(Activity $activity): RedirectResponse
    {
        Gate::authorize('manage', $activity);

        $activity->update(['allow_early_checkin' => !$activity->allow_early_checkin]);
        $this->auditToggle($activity, ($activity->allow_early_checkin ? 'เปิด' : 'ปิด') . "เช็คอินก่อนเวลา: \"{$activity->title}\"");

        $msg = $activity->allow_early_checkin
            ? "เปิดอนุญาตบันทึกกิจกรรมก่อนเวลาแล้ว"
            : "ปิดการบันทึกกิจกรรมก่อนเวลาแล้ว";

        return back()->with('success', $msg);
    }

    /**
     * สร้าง QR Code ใหม่ (Regenerate QR Token) และตั้งเวลาหมดอายุ (ถ้ามีการกำหนด)
     */
    public function regenerateQr(RegenerateQrRequest $request, Activity $activity, QrCodeService $qrService): RedirectResponse
    {
        Gate::authorize('manage', $activity);

        // กิจกรรมสิ้นสุดแล้ว → ปิดระบบ QR ไม่ให้สร้างใหม่
        if ($activity->isCheckInQrClosed()) {
            return back()->with('error', 'กิจกรรมสิ้นสุดแล้ว — ระบบ QR Code ถูกปิด ไม่สามารถสร้าง QR เข้างานใหม่ได้');
        }

        $oldToken = $activity->qr_token;
        $newToken = $qrService->generateToken();
        
        $expiresAt = null;
        if ($request->filled('expires_in_hours')) {
            $expiresAt = now()->addHours((int) $request->expires_in_hours);
        }

        $activity->update([
            'qr_token'      => $newToken,
            'qr_expires_at' => $expiresAt,
        ]);

        $this->auditUpdate($activity, ['qr_token' => $oldToken], "สร้าง QR Code ใหม่สำหรับกิจกรรม \"{$activity->title}\"");

        $expiryMsg = $expiresAt ? ' (หมดอายุใน ' . $request->expires_in_hours . ' ชั่วโมง)' : '';
        return back()->with('success', 'สร้าง QR Code ใหม่สำเร็จแล้ว' . $expiryMsg);
    }

    /**
     * สร้าง QR Code ออกงานใหม่ (Regenerate Checkout QR Token)
     */
    public function regenerateCheckoutQr(Activity $activity, QrCodeService $qrService): RedirectResponse
    {
        Gate::authorize('manage', $activity);

        // กิจกรรมสิ้นสุดแล้ว → ปิดระบบ QR ไม่ให้สร้างใหม่
        if ($activity->isCheckoutQrClosed()) {
            return back()->with('error', 'กิจกรรมสิ้นสุดแล้ว — ระบบ QR Code ถูกปิด ไม่สามารถสร้าง QR ออกงานใหม่ได้');
        }

        $oldToken = $activity->qr_checkout_token;
        $newToken = $qrService->generateToken();

        $activity->update([
            'qr_checkout_token' => $newToken,
        ]);

        $this->auditUpdate($activity, ['qr_checkout_token' => $oldToken], "สร้าง QR Code ออกงานใหม่สำหรับกิจกรรม \"{$activity->title}\"");

        return back()->with('success', 'สร้าง QR Code ออกงานใหม่สำเร็จแล้ว');
    }

    /**
     * คัดลอก/ทำซ้ำกิจกรรม — นำข้อมูลกิจกรรมเดิมมาสร้างเป็นรายการใหม่ (สถานะ draft)
     * ไม่คัดลอก: registrations, attendances, feedback
     */
    public function duplicate(Activity $activity, QrCodeService $qrService): RedirectResponse
    {
        Gate::authorize('view', $activity);
        Gate::authorize('create', Activity::class);

        $cloneData = $activity->only([
            'title', 'description', 'category_id', 'location',
            'activity_hours', 'max_participants', 'scope', 'faculty', 'department',
            'is_mandatory', 'is_multiday', 'allow_walkin',
            'require_attendance_approval', 'require_selfie_verification',
            'require_face_scan', 'face_scan_method',
            'activity_date', 'start_time', 'end_time',
            'register_open_at', 'register_close_at',
            'checkin_open_at', 'checkin_close_at',
        ]);

        $cloneData['title'] = '[สำเนา] ' . ($cloneData['title'] ?? '');
        $cloneData['status'] = 'upcoming';
        $cloneData['created_by'] = Auth::id();
        $cloneData['qr_token'] = $qrService->generateToken();
        $cloneData['qr_checkout_token'] = $qrService->generateToken();

        $newActivity = Activity::create($cloneData);
        $this->auditLog('clone_activity', "คัดลอกกิจกรรม #{$activity->id} \"{$activity->title}\" เป็น #{$newActivity->id}", Activity::class, $newActivity->id);

        ListCache::bump(ListCache::GROUP_ACTIVITIES);

        return redirect()->route('admin.activities.edit', $newActivity)
            ->with('success', "คัดลอกกิจกรรม \"{$activity->title}\" สำเร็จ! กรุณาตั้งวันเวลาและตรวจสอบข้อมูลก่อนเผยแพร่");
    }
}
