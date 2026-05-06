<?php

namespace App\Http\Controllers;

use App\Models\JobListing;
use App\Models\JobApplication;
use App\Models\JobComment;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * ตัวควบคุมฝั่งนักศึกษา: ดูรายการงาน, สมัครงาน, คอมเมนต์, สอบถาม
 */
class JobController extends Controller
{
    /** แสดงรายการประกาศงานทั้งหมด (พร้อม filter/search/map) */
    public function index(Request $request)
    {
        $query = JobListing::query()->withCount(['applications', 'comments']);

        // ค้นหาด้วยชื่องาน / สถานที่
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // กรองตามประเภทงาน
        if ($type = $request->input('job_type')) {
            $query->where('job_type', $type);
        }

        // กรองตามเพศ
        if ($gender = $request->input('gender')) {
            $query->where(function ($q) use ($gender) {
                $q->where('gender', $gender)->orWhere('gender', 'any');
            });
        }

        // กรองตามสถานะ
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // กรองตามค่าตอบแทน (ค้นหาตัวเลขใน compensation string)
        // เรียงลำดับ
        $sort = $request->input('sort', 'latest');
        if ($sort === 'compensation') {
            $query->orderByRaw("CAST(REGEXP_REPLACE(compensation, '[^0-9]', '') AS UNSIGNED) DESC");
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $jobs = $query->paginate(12)->withQueryString();

        // ข้อมูลสำหรับแผนที่ (เฉพาะงานที่มีพิกัด)
        $geoJobs = JobListing::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status', 'open')
            ->get()
            ->map(fn($j) => [
                'id'       => $j->id,
                'title'    => $j->title,
                'position' => $j->position,
                'location' => $j->location,
                'lat'      => (float) $j->latitude,
                'lng'      => (float) $j->longitude,
                'type'     => $j->job_type,
                'compensation' => $j->compensation,
                'image'    => $j->image_path ? Storage::url($j->image_path) : null,
                'url'      => route('jobs.show', $j->id),
            ]);

        // งานที่นักศึกษาสมัครแล้ว
        $appliedJobIds = [];
        if (auth()->check()) {
            $appliedJobIds = JobApplication::where('user_id', auth()->id())
                ->pluck('job_listing_id')->toArray();
        }

        return view('jobs.index', compact('jobs', 'geoJobs', 'appliedJobIds'));
    }

    /** แสดงรายละเอียดงาน */
    public function show($id)
    {
        $job = JobListing::with(['creator', 'applications.user'])->findOrFail($id);
        $comments = JobComment::with(['user', 'replies.user'])
            ->where('job_listing_id', $id)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->get();
        $userApplication = null;
        if (auth()->check()) {
            $userApplication = JobApplication::where('job_listing_id', $id)
                ->where('user_id', auth()->id())
                ->first();
        }

        $chatMessages = [];
        if (auth()->check()) {
            $chatMessages = ChatMessage::where('job_id', $id)
                ->where(function ($q) {
                    $q->where('student_id', auth()->id())
                      ->orWhere(function ($q2) {
                          $q2->where('sender_role', 'student')
                             ->where('sender_id', auth()->id());
                      });
                })
                ->orderBy('created_at', 'asc')
                ->get();
        }

        return view('jobs.show', compact('job', 'comments', 'userApplication', 'chatMessages'));
    }

    /** สมัครงาน */
    public function apply(Request $request, $id)
    {
        $job = JobListing::findOrFail($id);

        if (!$job->isOpen()) {
            return back()->with('error', 'ประกาศงานนี้ปิดรับสมัครแล้ว');
        }

        // ตรวจสอบว่าสมัครแล้วหรือยัง
        $existing = JobApplication::where('job_listing_id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if ($existing) {
            return back()->with('error', 'คุณได้สมัครงานนี้แล้ว');
        }

        // ตรวจสอบ quota
        if (!$job->hasAvailableSlots()) {
            return back()->with('error', 'ตำแหน่งงานเต็มแล้ว');
        }

        JobApplication::create([
            'job_listing_id' => $id,
            'user_id' => auth()->id(),
            'status' => 'pending',
        ]);

        return back()->with('success', 'สมัครงานเรียบร้อย รอการพิจารณาจากผู้ดูแล');
    }

    /** เพิ่มคอมเมนต์ */
    public function comment(Request $request, $id)
    {
        $request->validate([
            'body' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:job_comments,id',
        ]);

        JobComment::create([
            'job_listing_id' => $id,
            'user_id' => auth()->id(),
            'parent_id' => $request->parent_id,
            'body' => $request->body,
        ]);

        return back()->with('success', 'แสดงความคิดเห็นเรียบร้อย');
    }

    /** ลบคอมเมนต์ (เจ้าของเท่านั้น) */
    public function deleteComment($id)
    {
        $comment = JobComment::findOrFail($id);

        if ($comment->user_id !== auth()->id()) {
            return back()->with('error', 'คุณไม่มีสิทธิ์ลบคอมเมนต์นี้');
        }

        $comment->delete();
        return back()->with('success', 'ลบคอมเมนต์เรียบร้อย');
    }

}
