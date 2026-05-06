<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * เซอร์วิส QR Code
 * สร้าง token และ URL สำหรับเช็คอินผ่าน QR Code
 */
class QrCodeService
{
    /** สร้าง token สุ่ม 64 ตัวอักษรสำหรับกิจกรรม */
    public function generateToken(): string
    {
        return Str::random(64);
    }

    /** สร้าง URL เช็คอินจาก token สำหรับใช้ทำ QR Code */
    public function generateQrUrl(string $token): string
    {
        return url("/check-in/{$token}");
    }
}
