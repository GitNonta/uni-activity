<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ExtractFaceBiometricsJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * คอนโทรลเลอร์จัดการรูปโปรไฟล์นักศึกษา (Thin Controller)
 * บันทึกรูปภาพและส่งต่องานสกัดรหัสใบหน้า (AI Vector) เข้าสู่ Background Queue
 */
class ProfilePhotoController extends Controller
{
    /**
     * อัปโหลดหรือเปลี่ยนรูปโปรไฟล์ พร้อมส่งงานสกัดใบหน้าเข้า Queue
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'profile_photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $user = $request->user();
        if (!$user) {
            return back()->withErrors(['error' => 'ไม่สามารถระบุตัวตนของผู้ใช้']);
        }

        try {
            $file = $request->file('profile_photo');
            if (!$file) {
                return back()->withErrors(['error' => 'ไม่พบไฟล์รูปภาพ']);
            }

            $imageInfo = @getimagesize($file->getRealPath());
            if (!$imageInfo) {
                return back()->withErrors(['error' => 'ไฟล์รูปภาพไม่ถูกต้องหรือเสียหาย']);
            }

            $filename = 'profile_' . $user->id . '_' . time() . '.webp';
            $directory = 'profile-photos';
            $relativePath = $directory . '/' . $filename;
            $fullPath = Storage::disk('public')->path($relativePath);

            $dirPath = dirname($fullPath);
            if (!file_exists($dirPath)) {
                @mkdir($dirPath, 0775, true);
            }

            // แปลงและปรับขนาดเป็น WebP (Square 400x400)
            $src = match ($imageInfo['mime'] ?? '') {
                'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
                'image/png'  => @imagecreatefrompng($file->getRealPath()),
                'image/webp' => @imagecreatefromwebp($file->getRealPath()),
                default      => false,
            };

            if (!$src) {
                return back()->withErrors(['error' => 'ไม่สามารถอ่านไฟล์รูปภาพนี้ได้']);
            }

            $width  = imagesx($src);
            $height = imagesy($src);
            $size   = min($width, $height);
            $tmp    = imagecreatetruecolor(400, 400);

            if ($tmp) {
                imagealphablending($tmp, false);
                imagesavealpha($tmp, true);
                imagecopyresampled($tmp, $src, 0, 0, (int) (($width - $size) / 2), (int) (($height - $size) / 2), 400, 400, $size, $size);
                imagewebp($tmp, $fullPath, 80);
                imagedestroy($tmp);
            } else {
                // Fallback: บันทึกไฟล์ต้นฉบับ
                Storage::disk('public')->putFileAs($directory, $file, $filename);
            }

            imagedestroy($src);

            // ลบรูปโปรไฟล์เก่า (ถ้ามี)
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            // บันทึก Path และเคลียร์รหัสเดิมเพื่อรอ Background Worker สกัดใหม่
            $user->update([
                'profile_photo'      => $relativePath,
                'face_descriptor'    => null,
                'face_descriptor_js' => null,
            ]);

            // ส่งงานสกัด Vector (512D + 128D) เข้าสู่ Background Queue
            ExtractFaceBiometricsJob::dispatch($user->id, $relativePath, true)->onQueue('ai');

            Log::info("ProfilePhotoController: Photo uploaded for user #{$user->id}, dispatched ExtractFaceBiometricsJob");

            return back()->with('success', 'อัปโหลดรูปโปรไฟล์เรียบร้อยแล้ว (ระบบกำลังสกัดรหัสใบหน้าผ่าน Background Queue)');
        } catch (\Throwable $e) {
            Log::error('ProfilePhotoController upload error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการอัปโหลด: ' . $e->getMessage()]);
        }
    }

    /**
     * ลบรูปโปรไฟล์
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return back()->withErrors(['error' => 'ไม่สามารถระบุตัวตนของผู้ใช้']);
        }

        try {
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $user->update([
                'profile_photo'      => null,
                'face_descriptor'    => null,
                'face_descriptor_js' => null,
            ]);

            return back()->with('success', 'ลบรูปโปรไฟล์เรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            Log::error('ProfilePhotoController delete error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการลบรูป: ' . $e->getMessage()]);
        }
    }

    /**
     * บันทึก Face Descriptor ของ JS (128-d)
     */
    public function saveJsDescriptor(Request $request): JsonResponse
    {
        $request->validate([
            'descriptor'   => 'required|array',
            'descriptor.*' => 'numeric',
        ]);

        $user = $request->user();
        if ($user) {
            $user->update(['face_descriptor_js' => $request->descriptor]);
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 401);
    }
}
