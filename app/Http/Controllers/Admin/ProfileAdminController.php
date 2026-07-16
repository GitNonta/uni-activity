<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Traits\LogsAdminActivity;

class ProfileAdminController extends Controller
{
    use LogsAdminActivity;

    /**
     * แสดงหน้าจัดการโปรไฟล์ของ staff / admin
     */
    public function edit()
    {
        $user = auth()->user();

        // Auto translate english_name if empty
        if (empty($user->english_name) && !empty($user->full_name)) {
            try {
                $cleanName = str_replace(['นาย ', 'นางสาว ', 'นาง '], '', $user->full_name);
                $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=th&tl=en&dt=t&q=' . urlencode($cleanName);
                $response = @file_get_contents($url);
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data[0][0][0])) {
                        $user->english_name = ucwords(strtolower(trim($data[0][0][0])));
                        $user->save();
                    }
                }
            } catch (\Exception $e) {
                // Fallback / ignore
            }
        }

        // สถิติการใช้งาน
        $stats = [
            'activities_count'     => Activity::where('created_by', $user->id)->count(),
            'announcements_count'  => Announcement::where('created_by', $user->id)->count(),
            'joined_at'            => $user->created_at,
        ];

        return view('admin.profile.edit', compact('user', 'stats'));
    }

    /**
     * อัปเดตข้อมูลโปรไฟล์และรหัสผ่าน
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        // ตรวจสอบความถูกต้องของข้อมูล
        $rules = [
            'full_name'    => 'required|string|max:255',
            'english_name' => 'required|string|max:255',
            'email'        => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'        => 'nullable|string|max:20',
            'position'     => 'nullable|string|max:100',
            'organization' => 'nullable|string|max:150',
        ];

        // ถ้ามีการกรอกเปลี่ยนรหัสผ่าน ให้บังคับกรอกรหัสผ่านเดิมด้วย
        if ($request->filled('password_old') || $request->filled('password')) {
            $rules['password_old'] = 'required|current_password';
            $rules['password']     = 'required|string|min:6|confirmed';
        }

        $data = $request->validate($rules, [
            'password_old.required'         => 'กรุณากรอกรหัสผ่านเดิมก่อนเปลี่ยนรหัสผ่านใหม่',
            'password_old.current_password' => 'รหัสผ่านเดิมไม่ถูกต้อง',
            'password.required'             => 'กรุณากรอกรหัสผ่านใหม่',
            'password.confirmed'            => 'การยืนยันรหัสผ่านใหม่ไม่ตรงกัน',
            'password.min'                  => 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร',
        ]);

        // บันทึกค่าเก่าเพื่อ Audit Log
        $oldValues = $user->only(['full_name', 'english_name', 'email', 'phone', 'position', 'organization']);

        // อัปเดตข้อมูลส่วนตัว
        $user->full_name    = $data['full_name'];
        $user->english_name = $data['english_name'];
        $user->email        = $data['email'];
        $user->phone        = $data['phone'] ?? null;
        $user->position     = $data['position'] ?? null;
        $user->organization = $data['organization'] ?? null;

        // เปลี่ยนรหัสผ่าน (ถ้ามี)
        if ($request->filled('password')) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        // Audit Log
        $this->auditUpdate($user, $oldValues, "แก้ไขโปรไฟล์ส่วนตัว");

        return redirect()->route('admin.profile.edit')->with('success', 'อัปเดตข้อมูลโปรไฟล์เรียบร้อยแล้ว');
    }
}
