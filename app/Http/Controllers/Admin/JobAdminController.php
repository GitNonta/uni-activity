<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\JobPublished;
use App\Exports\JobApplicantExport;
use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobComment;
use App\Models\JobListing;
use App\Services\ImageOptimizationService;
use App\Services\ListCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ตัวควบคุมฝั่ง Admin: จัดการประกาศงาน, ผู้สมัคร, คำถาม, คอมเมนต์
 */
class JobAdminController extends Controller
{
    /** แสดงรายการประกาศงานทั้งหมด */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', JobListing::class);

        $query = JobListing::withCount(['applications', 'comments'])
            ->when(auth()->user()->isStaff(), fn($q) => $q->where('created_by', auth()->id()));

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $jobs = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return view('admin.jobs.index', compact('jobs'));
    }

    /** ฟอร์มสร้างประกาศงานใหม่ */
    public function create(): View
    {
        Gate::authorize('create', JobListing::class);

        return view('admin.jobs.create');
    }

    /** บันทึกประกาศงานใหม่ */
    public function store(Request $request, ImageOptimizationService $imageOptimizer): RedirectResponse
    {
        Gate::authorize('create', JobListing::class);

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'job_type'     => 'required|in:general,parttime',
            'position'     => 'required|string|max:255',
            'quota'        => 'required|integer|min:1',
            'work_period'  => 'nullable|string|max:255',
            'compensation' => 'nullable|string|max:255',
            'location'     => 'required|string|max:255',
            'start_date'   => 'required|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'dresscode'    => 'nullable|string|max:255',
            'gender'       => 'required|in:male,female,any',
            'note'         => 'nullable|string|max:2000',
            'description'  => 'nullable|string|max:5000',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'image'        => 'nullable|image|max:5120',
        ]);

        $validated['created_by'] = auth()->id();

        if ($request->hasFile('image')) {
            $validated['image_path'] = $imageOptimizer->storeImageAsWebp($request->file('image'), 'job-images');
        }

        unset($validated['image']);

        $job = JobListing::create($validated);

        // New post must appear on /jobs and the map immediately.
        ListCache::bump(ListCache::GROUP_JOBS);

        // ยิง event เพื่อส่ง LINE notification แบบ async
        JobPublished::dispatch($job);

        return redirect()->route('admin.jobs.index')->with('success', 'สร้างประกาศงานเรียบร้อย');
    }

    /** แสดงรายละเอียดงาน + ผู้สมัคร + คำถาม */
    public function show(JobListing $job): View
    {
        Gate::authorize('view', $job);

        $job->loadMissing([
            'creator',
            'applications.user',
            'comments.user',
        ]);

        $pendingCount = $job->applications->where('status', 'pending')->count();
        $confirmedCount = $job->applications->where('status', 'confirmed')->count();
        $rejectedCount = $job->applications->where('status', 'rejected')->count();

        return view('admin.jobs.show', compact('job', 'pendingCount', 'confirmedCount', 'rejectedCount'));
    }

    /** ฟอร์มแก้ไขประกาศงาน */
    public function edit(JobListing $job): View
    {
        Gate::authorize('update', $job);

        return view('admin.jobs.edit', compact('job'));
    }

    /** อัปเดตประกาศงาน */
    public function update(Request $request, JobListing $job, ImageOptimizationService $imageOptimizer): RedirectResponse
    {
        Gate::authorize('update', $job);

        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'job_type'     => 'required|in:general,parttime',
            'position'     => 'required|string|max:255',
            'quota'        => 'required|integer|min:1',
            'work_period'  => 'nullable|string|max:255',
            'compensation' => 'nullable|string|max:255',
            'location'     => 'required|string|max:255',
            'start_date'   => 'required|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'dresscode'    => 'nullable|string|max:255',
            'gender'       => 'required|in:male,female,any',
            'note'         => 'nullable|string|max:2000',
            'description'  => 'nullable|string|max:5000',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'image'        => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('image')) {
            // ลบรูปเก่า
            if ($job->image_path) {
                Storage::disk('public')->delete($job->image_path);
            }
            $validated['image_path'] = $imageOptimizer->storeImageAsWebp($request->file('image'), 'job-images');
        }

        unset($validated['image']);

        $job->update($validated);

        ListCache::bump(ListCache::GROUP_JOBS);

        return redirect()->route('admin.jobs.show', $job->id)->with('success', 'อัปเดตประกาศงานเรียบร้อย');
    }

    /** ลบประกาศงาน */
    public function destroy(JobListing $job): RedirectResponse
    {
        Gate::authorize('delete', $job);

        if ($job->image_path) {
            Storage::disk('public')->delete($job->image_path);
        }

        $job->delete();

        ListCache::bump(ListCache::GROUP_JOBS);

        return redirect()->route('admin.jobs.index')->with('success', 'ลบประกาศงานเรียบร้อย');
    }

    /** เปลี่ยนสถานะประกาศงาน */
    public function updateStatus(Request $request, JobListing $job): RedirectResponse
    {
        Gate::authorize('manage', $job);

        $request->validate(['status' => 'required|in:open,closed,completed']);
        $job->update(['status' => $request->status]);

        ListCache::bump(ListCache::GROUP_JOBS);

        $labels = ['open' => 'เปิดรับสมัคร', 'closed' => 'ปิดรับสมัคร', 'completed' => 'เสร็จสิ้น'];
        return back()->with('success', 'เปลี่ยนสถานะเป็น "' . $labels[$request->status] . '" เรียบร้อย');
    }

    /** Confirm / Reject ผู้สมัคร */
    public function updateApplicant(Request $request, JobListing $job, int $applicationId): RedirectResponse
    {
        Gate::authorize('manage', $job);

        $request->validate(['status' => 'required|in:confirmed,rejected']);
        $application = JobApplication::where('job_listing_id', $job->id)->findOrFail($applicationId);

        // ตรวจสอบ quota ถ้า confirm
        if ($request->status === 'confirmed') {
            if (!$job->hasAvailableSlots()) {
                return back()->with('error', 'จำนวนผู้ได้รับการยืนยันครบตามโควต้าแล้ว');
            }
        }

        $application->update(['status' => $request->status]);

        $label = $request->status === 'confirmed' ? 'ยืนยัน' : 'ปฏิเสธ';
        return back()->with('success', "{$label}ผู้สมัครเรียบร้อย");
    }

    /** ลบคอมเมนต์ (Admin/Staff Owner) */
    public function deleteComment(int $cid): RedirectResponse
    {
        $comment = JobComment::with('jobListing')->findOrFail($cid);
        Gate::authorize('delete', $comment);

        $comment->delete();

        return back()->with('success', 'ลบคอมเมนต์เรียบร้อย');
    }

    /** ส่งออกรายชื่อผู้สมัคร (CSV/Excel) */
    public function exportApplicants(Request $request, JobListing $job): \Symfony\Component\HttpFoundation\BinaryFileResponse|StreamedResponse
    {
        Gate::authorize('manage', $job);

        $format = $request->input('format', 'csv'); // csv or xlsx

        $applications = JobApplication::with('user')
            ->where('job_listing_id', $job->id)
            ->get();

        $filename = 'applicants_' . $job->id . '_' . now()->format('Y-m-d');

        if ($format === 'xlsx') {
            return Excel::download(
                new JobApplicantExport($applications, $job),
                $filename . '.xlsx'
            );
        }

        // CSV export
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ];

        $callback = function () use ($applications): void {
            $file = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['ลำดับ', 'รหัสนักศึกษา', 'ชื่อ-สกุล', 'คณะ', 'สาขา', 'โทรศัพท์', 'สถานะ', 'วันที่สมัคร']);

            foreach ($applications as $i => $app) {
                fputcsv($file, [
                    $i + 1,
                    $app->user->student_id ?? '-',
                    $app->user->full_name ?? '-',
                    $app->user->faculty ?? '-',
                    $app->user->department ?? '-',
                    $app->user->phone ?? '-',
                    match ($app->status) {
                        'pending'   => 'รอการพิจารณา',
                        'confirmed' => 'ยืนยันแล้ว',
                        'rejected'  => 'ไม่ผ่าน',
                        default     => $app->status,
                    },
                    $app->created_at->format('d/m/Y H:i'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
