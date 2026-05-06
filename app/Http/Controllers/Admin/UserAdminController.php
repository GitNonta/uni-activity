<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Traits\LogsAdminActivity;

/**
 * คอนโทรลเลอร์จัดการผู้ใช้งาน (ฝั่ง Admin)
 * รองรับ: สร้าง/แก้ไข/ลบ ทั้งนักศึกษาและเจ้าหน้าที่ (ผู้สร้างกิจกรรม)
 */
class UserAdminController extends Controller
{
    use LogsAdminActivity;
    /** แสดงรายชื่อผู้ใช้ทั้งหมด รองรับกรองตาม role และค้นหา */
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->role, fn($q) => $q->where('role', $request->role))
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('full_name', 'like', "%{$request->search}%")
                        ->orWhere('student_id', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%");
                });
            })
            ->when($request->status !== null && $request->status !== '', function ($q) use ($request) {
                $q->where('is_active', $request->status);
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'total'    => User::count(),
            'students' => User::where('role', 'student')->count(),
            'staff'    => User::where('role', 'staff')->count(),
        ];

        return view('admin.users.index', compact('users', 'counts'));
    }

    /** แสดงฟอร์มสร้างผู้ใช้ใหม่ */
    public function create(Request $request)
    {
        $type = $request->get('type', 'student');
        return view('admin.users.create', compact('type'));
    }

    /** บันทึกผู้ใช้ใหม่ */
    public function store(Request $request)
    {
        $rules = [
            'role'      => 'required|in:student,staff,admin',
            'full_name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ];

        if (in_array($request->role, ['staff', 'admin'])) {
            $rules['email'] = 'required|email|unique:users,email';
            $rules['password'] = 'required|string|min:6|confirmed';
        } else {
            // Student: email is automated, student_id is required
            $rules['email'] = 'nullable|email|unique:users,email';
            $rules['student_id'] = 'required|string|max:20|unique:users,student_id';
            $rules['faculty']    = 'nullable|string|max:100';
            $rules['department'] = 'nullable|string|max:100';
            $rules['year']       = 'nullable|integer|min:1|max:8';
            $rules['program']    = 'nullable|string|in:ปกติ,กศ.บป.';
            $rules['password']   = 'nullable|string|min:6';
        }

        $data = $request->validate($rules);

        $userData = [
            'role'      => $data['role'],
            'full_name' => $data['full_name'],
            'is_active' => $request->boolean('is_active', true),
        ];

        // Generate email for student
        if ($data['role'] === 'student') {
            $userData['email'] = 's' . $data['student_id'] . '@pkru.ac.th';
            $userData['student_id'] = $data['student_id'];
            $userData['faculty']    = $data['faculty'] ?? null;
            $userData['department'] = $data['department'] ?? null;
            $userData['year']       = $data['year'] ?? null;
            $userData['program']    = $data['program'] ?? null;
            $userData['password']   = !empty($data['password'])
                ? Hash::make($data['password'])
                : Hash::make($data['student_id']);
        } else {
            $userData['email'] = $data['email'];
            $userData['password'] = Hash::make($data['password']);
        }

        $user = User::create($userData);
        $this->auditCreate($user, "สร้างผู้ใช้ ({$data['role']}): \"{$data['full_name']}\"");

        $roleLabels = ['student' => 'นักศึกษา', 'staff' => 'เจ้าหน้าที่', 'admin' => 'ผู้ดูแลระบบ'];
        $label = $roleLabels[$data['role']] ?? 'ผู้ใช้';
        return redirect()->route('admin.users.index')->with('success', "สร้าง{$label} \"{$data['full_name']}\" สำเร็จ");
    }

    /** แสดงฟอร์มแก้ไขผู้ใช้ */
    public function edit(int $id)
    {
        $user = User::findOrFail($id);
        return view('admin.users.edit', compact('user'));
    }

    /** อัปเดตข้อมูลผู้ใช้ */
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $rules = [
            'full_name' => 'required|string|max:255',
            'email'     => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'is_active' => 'boolean',
        ];

        if ($user->role === 'student') {
            $rules['student_id'] = ['required', 'string', 'max:20', Rule::unique('users', 'student_id')->ignore($user->id)];
            $rules['faculty']    = 'nullable|string|max:100';
            $rules['department'] = 'nullable|string|max:100';
            $rules['year']       = 'nullable|integer|min:1|max:8';
            $rules['program']    = 'nullable|string|in:ปกติ,กศ.บป.';
        }

        $rules['password'] = 'nullable|string|min:6';

        $data = $request->validate($rules);

        $user->full_name = $data['full_name'];
        $user->is_active = $request->boolean('is_active', true);
        if ($user->role === 'student') {
            $user->student_id = $data['student_id'];
            $user->email      = 's' . $data['student_id'] . '@pkru.ac.th';
            $user->faculty    = $data['faculty'] ?? null;
            $user->department = $data['department'] ?? null;
            $user->year       = $data['year'] ?? null;
            $user->program    = $data['program'] ?? null;
        } elseif (in_array($user->role, ['staff', 'admin'])) {
            $user->email      = $data['email'];
        }

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $oldValues = $user->only(['full_name', 'email', 'is_active']);
        $user->save();
        $this->auditUpdate($user, $oldValues, "แก้ไขผู้ใช้ \"{$user->full_name}\"");

        $roleLabels = ['student' => 'นักศึกษา', 'staff' => 'เจ้าหน้าที่', 'admin' => 'ผู้ดูแลระบบ'];
        $label = $roleLabels[$user->role] ?? 'ผู้ใช้';
        return redirect()->route('admin.users.index')->with('success', "อัปเดต{$label} \"{$user->full_name}\" สำเร็จ");
    }

    /** ลบผู้ใช้ */
    public function destroy(int $id)
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'ไม่สามารถลบบัญชีของตัวเองได้');
        }

        $label = $user->role === 'student' ? 'นักศึกษา' : 'เจ้าหน้าที่';
        $name  = $user->full_name;
        $this->auditDelete($user, "ลบผู้ใช้ ({$user->role}): \"{$name}\"");
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', "ลบ{$label} \"{$name}\" สำเร็จ");
    }

    /** สลับสถานะเปิด/ปิดการใช้งาน */
    public function toggleActive(int $id)
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'ไม่สามารถปิดการใช้งานบัญชีของตัวเองได้');
        }

        $user->update(['is_active' => !$user->is_active]);
        $this->auditToggle($user, ($user->is_active ? 'เปิด' : 'ปิด') . "การใช้งาน: \"{$user->full_name}\"");

        $status = $user->is_active ? 'เปิดใช้งาน' : 'ปิดการใช้งาน';
        return back()->with('success', "{$status} \"{$user->full_name}\" สำเร็จ");
    }
}
