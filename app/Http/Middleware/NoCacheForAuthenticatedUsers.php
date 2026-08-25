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
 * เพจสาธารณะยัง cache ได้ตามปกติ — ใส่ header เฉพาะเมื่อผู้ใช้ล็อกอินแล้วเท่านั้น
 */
class NoCacheForAuthenticatedUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (auth()->check()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
