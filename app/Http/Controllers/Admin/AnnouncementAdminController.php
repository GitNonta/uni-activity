<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\AnnouncementPublished;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use App\Services\ImageOptimizationService;
use App\Services\ListCache;
use App\Traits\LogsAdminActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AnnouncementAdminController extends Controller
{
    use LogsAdminActivity;

    /** รายการประกาศทั้งหมด */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Announcement::class);

        $announcements = Announcement::with('creator')
            ->when(auth()->user()->isStaff(), fn($q) => $q->where('created_by', auth()->id()))
            ->when($request->search, function ($q) use ($request): void {
                $q->where(function ($inner) use ($request): void {
                    $inner->where('title', 'like', "%{$request->search}%")
                          ->orWhere('content', 'like', "%{$request->search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('admin.announcements.index', compact('announcements'));
    }

    /** ฟอร์มสร้างประกาศ */
    public function create(): View
    {
        Gate::authorize('create', Announcement::class);

        $faculties = User::whereNotNull('faculty')->distinct()->pluck('faculty')->sort();
        return view('admin.announcements.create', compact('faculties'));
    }

    /** บันทึกประกาศใหม่ */
    public function store(Request $request, ImageOptimizationService $imageOptimizer): RedirectResponse
    {
        Gate::authorize('create', Announcement::class);

        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'content'        => 'required|string',
            'target_faculty' => 'nullable|string',
            'type'           => 'required|in:info,warning,danger,success',
            'is_active'      => 'boolean',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'published_at'   => 'nullable|date',
        ]);

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageOptimizer->storeImageAsWebp($request->file('image'), 'announcements');
        }

        $data['created_by'] = auth()->id();
        $data['is_active'] = $request->has('is_active');
        $data['published_at'] = $request->filled('published_at') ? $request->input('published_at') : null;

        $announcement = Announcement::create($data);
        $this->auditCreate($announcement, __('สร้างประกาศ ":t"', ['t' => $announcement->title]));

        // New post must be visible immediately — bump the list-cache version
        // so every cached announcements page is invalidated in one write.
        ListCache::bump(ListCache::GROUP_ANNOUNCEMENTS);

        // ยิง event เพื่อส่ง LINE notification แบบ async (เฉพาะกรณีเผยแพร่ทันที)
        if ($announcement->published_at === null || $announcement->published_at->isPast()) {
            AnnouncementPublished::dispatch($announcement);
        }

        return redirect()->route('admin.announcements.index')->with('success', __('flash.สร้างประกาศสำเร็จ!'));
    }

    /** ฟอร์มแก้ไขประกาศ */
    public function edit(Announcement $announcement): View
    {
        Gate::authorize('update', $announcement);

        $faculties = User::whereNotNull('faculty')->distinct()->pluck('faculty')->sort();
        return view('admin.announcements.edit', compact('announcement', 'faculties'));
    }

    /** อัปเดตประกาศ */
    public function update(Request $request, Announcement $announcement, ImageOptimizationService $imageOptimizer): RedirectResponse
    {
        Gate::authorize('update', $announcement);

        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'content'        => 'required|string',
            'target_faculty' => 'nullable|string',
            'type'           => 'required|in:info,warning,danger,success',
            'published_at'   => 'nullable|date',
        ]);

        $data['is_active'] = $request->has('is_active');

        if ($request->hasFile('image')) {
            // ลบรูปเดิมถ้ามี
            if ($announcement->image_path) {
                Storage::disk('public')->delete($announcement->image_path);
            }
            $data['image_path'] = $imageOptimizer->storeImageAsWebp($request->file('image'), 'announcements');
        }

        $oldValues = $announcement->only(['title', 'is_active', 'target_faculty']);
        $announcement->update($data);
        $this->auditUpdate($announcement, $oldValues, __('แก้ไขประกาศ ":t"', ['t' => $announcement->title]));

        ListCache::bump(ListCache::GROUP_ANNOUNCEMENTS);

        return redirect()->route('admin.announcements.index')->with('success', __('flash.อัปเดตประกาศสำเร็จ!'));
    }

    /** ลบประกาศ */
    public function destroy(Announcement $announcement): RedirectResponse
    {
        Gate::authorize('delete', $announcement);

        $this->auditDelete($announcement, __('ลบประกาศ ":t"', ['t' => $announcement->title]));
        $announcement->delete();

        ListCache::bump(ListCache::GROUP_ANNOUNCEMENTS);

        return redirect()->route('admin.announcements.index')->with('success', __('flash.ลบประกาศสำเร็จ'));
    }

    /** สลับสถานะการเปิดใช้งาน */
    public function toggleActive(Announcement $announcement): RedirectResponse
    {
        Gate::authorize('toggle', $announcement);

        $announcement->update(['is_active' => !$announcement->is_active]);
        $status = $announcement->is_active ? 'เปิด' : 'ปิด';

        ListCache::bump(ListCache::GROUP_ANNOUNCEMENTS);
        $this->auditToggle($announcement, __($announcement->is_active ? 'เปิดการใช้งานประกาศ ":t"' : 'ปิดการใช้งานประกาศ ":t"', ['t' => $announcement->title]));

        return back()->with('success', __('สลับสถานะประกาศ: :statusการใช้งานเรียบร้อยแล้ว', ['status' => $status]));
    }
}
