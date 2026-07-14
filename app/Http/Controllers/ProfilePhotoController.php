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
            'profile_photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $user = auth()->user();
        
        if (!$user) {
            return back()->withErrors(['error' => 'ไม่สามารถระบุตัวตนของผู้ใช้']);
        }

        try {
            $file = $request->file('profile_photo');
            $filename = 'profile_' . $user->id . '_' . time() . '.webp';
            $directory = 'profile-photos';
            $fullPath = storage_path('app/public/' . $directory . '/' . $filename);

            // สร้างโฟลเดอร์ถ้ายังไม่มี
            if (!file_exists(storage_path('app/public/' . $directory))) {
                mkdir(storage_path('app/public/' . $directory), 0775, true);
            }

            // --- ประมวลผลรูปภาพ (GD) ---
            $imageInfo = getimagesize($file->getRealPath());
            $mime = $imageInfo['mime'];

            // สร้าง Image Resource ตามประเภทไฟล์ต้นฉบับ
            switch ($mime) {
                case 'image/jpeg': $src = imagecreatefromjpeg($file->getRealPath()); break;
                case 'image/png':  $src = imagecreatefrompng($file->getRealPath()); break;
                case 'image/webp': $src = imagecreatefromwebp($file->getRealPath()); break;
                default: return back()->withErrors(['error' => 'ไม่รองรับประเภทไฟล์นี้']);
            }

            // ปรับขนาด (Resize) ให้เป็นจัตุรัสและไม่เกิน 400px (เหมาะสำหรับโปรไฟล์)
            $width = imagesx($src);
            $height = imagesy($src);
            $size = min($width, $height);
            $tmp = imagecreatetruecolor(400, 400);
            
            // ทำให้พื้นหลังโปร่งใส (สำหรับ PNG/WebP)
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            
            imagecopyresampled($tmp, $src, 0, 0, ($width-$size)/2, ($height-$size)/2, 400, 400, $size, $size);

            // บันทึกเป็น WebP (Quality 80% เพื่อลดขนาด)
            imagewebp($tmp, $fullPath, 80);

            // คืนหน่วยความจำ
            imagedestroy($src);
            imagedestroy($tmp);

            // ลบรูปเก่า
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            $updateData = ['profile_photo' => $directory . '/' . $filename];
            
            // ส่งรูปให้ AI Server สกัด Vector (512-d) ทันที
            $aiServerUrl = config('services.ai_server.url');
            if (!empty($aiServerUrl)) {
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(15)
                        ->attach('image', file_get_contents($fullPath), $filename)
                        ->post(rtrim($aiServerUrl, '/') . '/extract');
                        
                    if ($response->successful()) {
                        $aiResult = $response->json();
                        if (!empty($aiResult['embedding'])) {
                            $updateData['face_descriptor'] = $aiResult['embedding'];
                        }
                    } else {
                        \Log::warning('AI Server extraction failed: ' . $response->body());
                    }
                } catch (\Exception $e) {
                    \Log::error('AI Server extraction error: ' . $e->getMessage());
                }
            }

            $user->update($updateData); 
            
            return back()->with('success', 'อัปโหลดและปรับปรุงรูปโปรไฟล์สำเร็จ (WebP)');
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

            $user->update([
                'profile_photo' => null,
                'face_descriptor' => null
            ]);

            return back()->with('success', 'ลบรูปโปรไฟล์เรียบร้อยแล้ว');
        } catch (\Exception $e) {
            \Log::error('Profile photo delete error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการลบรูป: ' . $e->getMessage()]);
        }
    }
}
