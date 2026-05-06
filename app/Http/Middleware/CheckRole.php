<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * มิดเดิลแวร์ตรวจสอบบทบาทผู้ใช้
 * ใช้งาน: middleware('role:staff') หรือ middleware('role:student,staff')
 */
class CheckRole
{
    /** ตรวจสอบว่าผู้ใช้มีบทบาทตรงตามที่กำหนด ถ้าไม่ใช่จะแสดง 403 */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        }

        $userRole = $request->user()->role;

        // ถ้า role ที่กำหนดมี 'staff' ให้ admin เข้าได้ด้วยเสมอ
        if (in_array('staff', $roles)) {
            $roles[] = 'admin';
        }

        if (!in_array($userRole, $roles)) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        }

        return $next($request);
    }
}
