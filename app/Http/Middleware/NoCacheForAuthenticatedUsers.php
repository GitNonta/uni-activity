<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ป้องกัน browser cache เก็บหน้าที่ต้องล็อกอิน (โดยเฉพาะมือถือ)
 * ทำให้ UI/JS เวอร์ชันเก่าค้างและปุ่มบางอย่าง "ไม่ทำงาน" หลัง deploy
 *
 * ขยายให้ครอบหน้า auth (guest) ด้วย: /login, /register, /forgot-password,
 * /reset-password, /admin/login — เพราะหน้าพวกนี้แคช `no-cache` ธรรมดา
 * ทำให้ผู้ใช้ยังเห็น UI เวอร์ชันเก่าหลังอัปเดต (ต้อง hard refresh จึงเห็นใหม่)
 */
class NoCacheForAuthenticatedUsers
{
    /** หน้า auth ที่ต้องไม่เก็บแคชแม้ยังไม่ล็อกอิน */
    protected array $noStorePaths = [
        'login',
        'register',
        'forgot-password',
        'reset-password',
        'admin/login',
        'admin/forgot-password',
        'admin/reset-password',
        'login/verify-otp',
        'login/resend-otp',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isAuthPage = $request->is($this->noStorePaths);

        if (auth()->check() || $isAuthPage) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
