<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * คอนโทรลเลอร์จัดการรูปโปรไฟล์นักศึกษา
 * รองรับ: อัปโหลดรูปใหม่ / ลบรูป
 */
class ProfilePhotoController extends Controller
{
    /** อัปโหลดหรือเปลี่ยนรูปโปรไฟล์ */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'profile_photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $user = auth()->user();
        
        if (!$user) {
            return back()->withErrors(['error' => 'ไม่สามารถระบุตัวตนของผู้ใช้']);
        }

        try {
            // ลบรูปเก่าถ้ามี
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            // บันทึกรูปใหม่ไปที่ storage/app/public/profile-photos/
            $path = $request->file('profile_photo')->store('profile-photos', 'public');

            // ตรวจสอบว่าไฟล์ถูกเก็บสำเร็จ
            if (!$path) {
                return back()->withErrors(['error' => 'ไม่สามารถบันทึกไฟล์ได้']);
            }

            $user->update(['profile_photo' => $path]);

            return back()->with('success', 'อัปโหลดรูปโปรไฟล์เรียบร้อยแล้ว');
        } catch (\Exception $e) {
            \Log::error('Profile photo upload error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการอัปโหลด: ' . $e->getMessage()]);
        }
    }

    /** ลบรูปโปรไฟล์ */
    public function destroy()
    {
        $user = auth()->user();

        if (!$user) {
            return back()->withErrors(['error' => 'ไม่สามารถระบุตัวตนของผู้ใช้']);
        }

        try {
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $user->update(['profile_photo' => null]);

            return back()->with('success', 'ลบรูปโปรไฟล์เรียบร้อยแล้ว');
        } catch (\Exception $e) {
            \Log::error('Profile photo delete error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการลบรูป: ' . $e->getMessage()]);
        }
    }
}
