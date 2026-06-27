<?php

namespace App\Http\Controllers;

use App\Models\JobListing;
use App\Models\JobApplication;
use App\Models\JobComment;
use App\Models\Message;
use App\Models\Room;
use App\Repositories\ChatRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * ตัวควบคุมฝั่งนักศึกษา: ดูรายการงาน, สมัครงาน, คอมเมนต์, สอบถาม
 */
class JobController extends Controller
{
    public function __construct(protected ChatRepository $chatRepository) {}
    /** แสดงรายการประกาศงานทั้งหมด (พร้อม filter/search/map) */
    public function index(Request $request)
    {
        $cacheKey = 'jobs_page_' . md5($request->fullUrl());
        
        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function () use ($request) {
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

            // กรองตามค่าตอบแทน
            $sort = $request->input('sort', 'latest');
            if ($sort === 'compensation') {
                $query->orderByRaw("CAST(REGEXP_REPLACE(compensation, '[^0-9]', '') AS UNSIGNED) DESC");
            } else {
                $query->orderBy('created_at', 'desc');
            }

            return $query->paginate(12)->withQueryString();
        });

        $jobs = $data;

        // ข้อมูลสำหรับแผนที่ (เฉพาะงานที่มีพิกัด)
        $geoJobs = \Illuminate\Support\Facades\Cache::remember('jobs_geo_all', 300, function () {
            return JobListing::whereNotNull('latitude')
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
        });

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
        $job = JobListing::with(['creator:id,full_name,profile_photo_path'])->findOrFail($id);
        $comments = JobComment::with(['user:id,full_name,profile_photo_path', 'replies.user:id,full_name,profile_photo_path'])
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
            $userId = auth()->id();
            $room = Room::where('job_id', $id)
                ->whereHas('users', function ($q) use ($userId) {
                    $q->where('users.id', $userId);
                })
                ->first();

            if ($room) {
                $chatMessages = $this->chatRepository->getRecentMessages($room);
            }
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
        $data = $request->validate([
            'body' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:job_comments,id',
        ]);

        if (!empty($data['parent_id'])) {
            $validParent = JobComment::where('id', $data['parent_id'])
                ->where('job_listing_id', $id)
                ->whereNull('parent_id')
                ->exists();

            if (!$validParent) {
                throw ValidationException::withMessages([
                    'parent_id' => 'คอมเมนต์ต้นทางไม่ถูกต้อง',
                ]);
            }
        }

        JobComment::create([
            'job_listing_id' => $id,
            'user_id' => auth()->id(),
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
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
