<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use App\Traits\LogsAdminActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnnouncementAdminController extends Controller
{
    use LogsAdminActivity;

    /** รายการประกาศทั้งหมด */
    public function index(Request $request)
    {
        $announcements = Announcement::with('creator')
            ->when($request->search, function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('content', 'like', "%{$request->search}%");
            })
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('admin.announcements.index', compact('announcements'));
    }

    /** ฟอร์มสร้างประกาศ */
    public function create()
    {
        $faculties = User::whereNotNull('faculty')->distinct()->pluck('faculty')->sort();
        return view('admin.announcements.create', compact('faculties'));
    }

    /** บันทึกประกาศใหม่ */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'content'        => 'required|string',
            'target_faculty' => 'nullable|string',
            'type'           => 'required|in:info,warning,danger,success',
            'is_active'      => 'boolean',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('announcements', 'public');
        }

        $data['created_by'] = auth()->id();
        $data['is_active'] = $request->has('is_active');

        $announcement = Announcement::create($data);
        $this->auditCreate($announcement, "สร้างประกาศ \"{$announcement->title}\"");

        return redirect()->route('admin.announcements.index')->with('success', 'สร้างประกาศสำเร็จ!');
    }

    /** ฟอร์มแก้ไขประกาศ */
    public function edit($id)
    {
        $announcement = Announcement::findOrFail($id);
        $faculties = User::whereNotNull('faculty')->distinct()->pluck('faculty')->sort();
        return view('admin.announcements.edit', compact('announcement', 'faculties'));
    }

    /** อัปเดตประกาศ */
    public function update(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);

        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'content'        => 'required|string',
            'target_faculty' => 'nullable|string',
            'type'           => 'required|in:info,warning,danger,success',
        ]);

        $data['is_active'] = $request->has('is_active');

        if ($request->hasFile('image')) {
            // ลบรูปเดิมถ้ามี
            if ($announcement->image_path) {
                Storage::disk('public')->delete($announcement->image_path);
            }
            $data['image_path'] = $request->file('image')->store('announcements', 'public');
        }

        $oldValues = $announcement->only(['title', 'is_active', 'target_faculty']);
        $announcement->update($data);
        $this->auditUpdate($announcement, $oldValues, "แก้ไขประกาศ \"{$announcement->title}\"");

        return redirect()->route('admin.announcements.index')->with('success', 'อัปเดตประกาศสำเร็จ!');
    }

    /** ลบประกาศ */
    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);
        $this->auditDelete($announcement, "ลบประกาศ \"{$announcement->title}\"");
        $announcement->delete();

        return redirect()->route('admin.announcements.index')->with('success', 'ลบประกาศสำเร็จ');
    }

    /** สลับสถานะการเปิดใช้งาน */
    public function toggleActive($id)
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->update(['is_active' => !$announcement->is_active]);
        $status = $announcement->is_active ? 'เปิด' : 'ปิด';
        $this->auditToggle($announcement, "{$status}การใช้งานประกาศ \"{$announcement->title}\"");

        return back()->with('success', "{$status}การใช้งานประกาศเรียบร้อยแล้ว");
    }
}
