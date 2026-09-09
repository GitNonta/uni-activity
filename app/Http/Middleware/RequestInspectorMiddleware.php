<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestInspectorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('HEAD') || $request->is('up') || $request->is('health*')) {
            return $next($request);
        }

        $startTime = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->sendTelemetry($request, $response, $duration);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $startTime = $request->attributes->get('inspector_start_time');
        $duration = $startTime ? round((microtime(true) - (float) $startTime) * 1000, 2) : 0;
        $this->sendTelemetry($request, $response, $duration);
    }

    protected function sendTelemetry(Request $request, Response $response, float $duration): void
    {
        if ($request->attributes->get('inspector_logged') === true) {
            return;
        }
        $request->attributes->set('inspector_logged', true);

        if ($request->isMethod('HEAD') || $request->is('up') || $request->is('health*')) {
            return;
        }

        try {
            // Extract sanitized input payload (omitting sensitive keys)
            $inputs = $request->except([
                'password', 'password_confirmation', 'current_password', 
                'new_password', '_token', 'api_key', 'token'
            ]);

            $rawContent = (string) $request->getContent();
            $bodyStr = '';

            if ($request->isMethod('GET')) {
                // For GET requests, parameters belong in URL query, not body
                if (!empty($rawContent)) {
                    $bodyStr = strlen($rawContent) > 2048 ? substr($rawContent, 0, 2048) . '... (truncated)' : $rawContent;
                }
            } else {
                if (!empty($inputs)) {
                    $bodyStr = (string) json_encode($inputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                } elseif (!empty($rawContent)) {
                    $bodyStr = strlen($rawContent) > 2048 ? substr($rawContent, 0, 2048) . '... (truncated)' : $rawContent;
                }
            }

            // Extract relevant request headers
            $headers = [];
            $allowedHeaders = [
                'host', 'user-agent', 'accept', 'content-type', 'referer',
                'x-requested-with', 'x-forwarded-for', 'x-forwarded-proto', 'x-real-ip',
                'cf-connecting-ip', 'cf-ipcountry', 'cf-ipcity', 'cf-region', 'cf-ray', 'cf-visitor',
                'accept-language', 'accept-encoding'
            ];
            foreach ($request->headers->all() as $k => $v) {
                if (in_array(strtolower($k), $allowedHeaders, true)) {
                    $headers[$k] = is_array($v) ? implode(', ', $v) : (string) $v;
                }
            }
            if ($request->hasHeader('Authorization')) {
                $headers['Authorization'] = 'Bearer [PROTECTED]';
            }

            // Real Client IP resolution (prioritize Cloudflare & Reverse Proxy headers)
            $clientIp = null;
            $cfConnectingIp = trim((string) $request->header('cf-connecting-ip'));
            if (!empty($cfConnectingIp) && filter_var($cfConnectingIp, FILTER_VALIDATE_IP)) {
                $clientIp = $cfConnectingIp;
            }

            if (!$clientIp) {
                // Check X-Forwarded-For chain, searching for first non-internal public IP
                $rawFwd = (string) $request->header('x-forwarded-for');
                if (!empty($rawFwd)) {
                    $parts = array_map('trim', explode(',', $rawFwd));
                    // 1. Try first valid public IP
                    foreach ($parts as $p) {
                        if (filter_var($p, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                            $clientIp = $p;
                            break;
                        }
                    }
                    // 2. Fallback to first valid IP in list
                    if (!$clientIp) {
                        foreach ($parts as $p) {
                            if (filter_var($p, FILTER_VALIDATE_IP)) {
                                $clientIp = $p;
                                break;
                            }
                        }
                    }
                }
            }

            if (!$clientIp) {
                $realIp = trim((string) $request->header('x-real-ip'));
                if (!empty($realIp) && filter_var($realIp, FILTER_VALIDATE_IP)) {
                    $clientIp = $realIp;
                }
            }

            if (!$clientIp) {
                $clientIp = (string) ($request->ip() ?: '127.0.0.1');
            }

            $cfCountry = strtoupper((string) $request->header('cf-ipcountry', ''));
            $cfCity = (string) $request->header('cf-ipcity', '');
            $cfRegion = (string) $request->header('cf-region', '');
            $cfRay = (string) $request->header('cf-ray', '');

            // Response metadata
            $responseHeaders = [
                'Content-Type' => (string) $response->headers->get('content-type', 'text/html'),
                'Content-Length' => (string) $response->headers->get('content-length', (string) strlen((string) $response->getContent())),
            ];

            $responseBody = '';
            $contentType = strtolower((string) $response->headers->get('content-type', ''));
            if (str_contains($contentType, 'application/json')) {
                $content = (string) $response->getContent();
                $responseBody = strlen($content) > 2048 ? substr($content, 0, 2048) . '...' : $content;
            }

            // User Identity Resolution (Who is using our website)
            $user = $request->user();
            $userInfo = [
                'is_authenticated' => $user !== null,
                'id' => $user?->id,
                'name' => $user ? ($user->full_name ?: ($user->english_name ?: ($user->name ?? 'User #' . $user->id))) : 'Guest Visitor (ผู้เยี่ยมชม)',
                'role' => $user ? ($user->role ?? (isset($user->is_admin) && $user->is_admin ? 'admin' : 'student')) : 'guest',
                'student_id' => $user?->student_id,
                'email' => $user?->email,
                'faculty' => $user?->faculty,
                'department' => $user?->department,
            ];

            // Route & Action Resolution (What they are doing & Which section)
            $route = $request->route();
            $routeName = (string) ($route?->getName() ?: '');
            $actionName = (string) ($route?->getActionName() ?: '');
            $controller = '';
            if ($actionName && str_contains($actionName, '@')) {
                $parts = explode('@', class_basename($actionName));
                $controller = $parts[0] . '@' . ($parts[1] ?? '');
            } elseif ($actionName) {
                $controller = class_basename($actionName);
            }

            [$actionTitle, $sectionTitle] = $this->resolveActionAndSection($request, $routeName, $controller);

            // Public Entry URL (Preserve Cloudflare Tunnel / Reverse Proxy host & HTTPS scheme)
            $fwdHost = $request->header('x-forwarded-host') ?: $request->header('host');
            $fwdProto = $request->header('x-forwarded-proto') ?: ($request->isSecure() ? 'https' : 'http');
            if ($request->hasHeader('cf-ray') || str_contains((string) $fwdHost, 'trycloudflare.com')) {
                $fwdProto = 'https';
            }
            $isHttps = strtolower((string) $fwdProto) === 'https';
            $fullEntryUrl = $fwdHost ? "{$fwdProto}://{$fwdHost}" . $request->getRequestUri() : $request->fullUrl();
            $refererUrl = (string) ($request->header('referer') ?: '');

            $data = [
                'method' => $request->method(),
                'protocol' => strtoupper((string) $fwdProto),
                'is_https' => $isHttps,
                'url' => $fullEntryUrl,
                'path' => $request->path(),
                'referer' => $refererUrl,
                'ip' => $clientIp,
                'country' => $cfCountry,
                'city' => $cfCity,
                'region' => $cfRegion,
                'ray' => $cfRay,
                'user' => $userInfo,
                'action' => $actionTitle,
                'section' => $sectionTitle,
                'route' => $routeName,
                'controller' => $controller,
                'duration' => $duration,
                'status' => $response->getStatusCode(),
                'time' => now()->toIso8601String(),
                'request' => [
                    'headers' => $headers,
                    'body' => $bodyStr ?: '',
                    'query' => $request->query(),
                ],
                'response' => [
                    'headers' => $responseHeaders,
                    'body' => $responseBody,
                ],
            ];

            $payload = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (strlen($payload) > 60000) {
                $data['request']['body'] = '(Payload exceeds UDP limit)';
                $data['response']['body'] = '';
                $payload = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket) {
                socket_set_nonblock($socket);
                socket_sendto($socket, $payload, strlen($payload), 0, '127.0.0.1', 9998);
                socket_close($socket);
            }
        } catch (\Throwable $e) {
            // Silence exceptions to avoid disrupting user response
        }
    }

    /**
     * Resolve human-readable action description and system module/section.
     *
     * @return array{0: string, 1: string} [ActionTitle, SectionTitle]
     */
    protected function resolveActionAndSection(Request $request, string $routeName, string $controller): array
    {
        $path = trim($request->path(), '/');
        $method = strtoupper($request->method());

        // 1. Determine System Section
        $section = 'General / Public (ส่วนทั่วไป)';
        if (str_starts_with($path, 'admin') || str_starts_with($routeName, 'admin.')) {
            $section = 'Admin Management (ส่วนผู้ดูแลระบบ)';
        } elseif (str_starts_with($path, 'student') || str_starts_with($routeName, 'student.')) {
            $section = 'Student Portal (ส่วนนักศึกษา)';
        } elseif (str_starts_with($path, 'activities') || str_starts_with($routeName, 'activities.')) {
            $section = 'Activities Hub (ส่วนกิจกรรม)';
        } elseif (str_contains($path, 'scanner') || str_contains($path, 'checkin') || str_contains($path, 'face') || str_contains($routeName, 'scanner') || str_contains($routeName, 'checkin')) {
            $section = 'AI Face Scanner & Attendance (ส่วนสแกนใบหน้าและเช็คชื่อ)';
        } elseif (str_starts_with($path, 'login') || str_starts_with($path, 'logout') || str_starts_with($path, 'password') || str_starts_with($path, 'forgot-password') || str_contains($routeName, 'login') || str_contains($routeName, 'password')) {
            $section = 'Authentication & Security (ระบบเข้าสู่ระบบ)';
        } elseif (str_starts_with($path, 'chat') || str_starts_with($path, 'inbox') || str_contains($routeName, 'chat')) {
            $section = 'Chat & Communication (ส่วนแชทและข้อความ)';
        } elseif (str_starts_with($path, 'api/')) {
            $section = 'API Service (บริการ API)';
        } elseif ($path === 'health' || $path === 'up' || str_starts_with($path, 'debug')) {
            $section = 'System Health (ระบบตรวจสอบความพร้อม)';
        }

        // 2. Determine Human-readable Action
        $knownActions = [
            'login' => 'เข้าสู่ระบบ (Login)',
            'logout' => 'ออกจากระบบ (Logout)',
            'staff.login' => 'เข้าสู่ระบบเจ้าหน้าที่ (Staff Login)',
            'staff.login.post' => 'ยืนยันการเข้าสู่ระบบเจ้าหน้าที่ (Submit Staff Login)',
            'student.login' => 'เข้าสู่ระบบนักศึกษา (Student Login)',
            'student.login.post' => 'ยืนยันการเข้าสู่ระบบนักศึกษา (Submit Student Login)',
            'activities.index' => 'ดูรายการกิจกรรมทั้งหมด (Browse Activities)',
            'activities.show' => 'ดูรายละเอียดกิจกรรม (View Activity Details)',
            'activities.register' => 'ลงทะเบียนเข้าร่วมกิจกรรม (Register for Activity)',
            'admin.dashboard' => 'ดูแดชบอร์ดสถิติผู้ดูแล (Admin Dashboard)',
            'admin.activities.index' => 'จัดการรายการกิจกรรม (Manage Activities)',
            'admin.activities.create' => 'เปิดหน้าสร้างกิจกรรมใหม่ (Create Activity Page)',
            'admin.activities.store' => 'บันทึกสร้างกิจกรรมใหม่ (Save New Activity)',
            'admin.profile.edit' => 'แก้ไขข้อมูลส่วนตัวผู้ดูแล (Edit Admin Profile)',
            'admin.profile.update' => 'บันทึกข้อมูลส่วนตัวผู้ดูแล (Update Admin Profile)',
            'profile.edit' => 'แก้ไขข้อมูลส่วนตัว (Edit Profile)',
            'profile.update' => 'บันทึกข้อมูลส่วนตัว (Update Profile)',
            'student.scanner' => 'เปิดหน้าจอแสกนใบหน้า (Open Face Scanner)',
            'check-in.scan' => 'สแกนใบหน้าเช็คชื่อเข้ากิจกรรม (Face Scan Check-in)',
            'check-in.store' => 'บันทึกการเช็คชื่อเข้ากิจกรรม (Record Attendance)',
            'password.request' => 'ขอรีเซ็ตรหัสผ่าน (Request Password Reset OTP)',
            'password.email' => 'ส่งรหัส OTP ทางอีเมล (Send Reset OTP)',
            'password.reset' => 'ตั้งรหัสผ่านใหม่ (Set New Password)',
            'password.update' => 'บันทึกรหัสผ่านใหม่ (Save New Password)',
            'excel.export' => 'ส่งออกข้อมูลรายงาน Excel (Export Excel Report)',
            'health' => 'ตรวจสอบความพร้อมของระบบ (System Health Check)',
            'up' => 'ตรวจสอบสถานะ Uptime (Uptime Check)',
        ];

        if (isset($knownActions[$routeName])) {
            return [$knownActions[$routeName], $section];
        }

        // Action heuristics based on path and HTTP method
        if ($path === '' || $path === '/') {
            $action = 'เข้าสู่หน้าหลัก (Visit Homepage)';
        } elseif ($path === 'broadcasting/auth') {
            $action = 'ยืนยันตัวตน Real-time WebSocket (Authenticate Real-time Channel)';
            $section = 'Chat & Communication (ส่วนแชทและข้อความ)';
        } elseif (str_starts_with($path, 'admin/inbox/unread-count')) {
            $action = 'ตรวจนับข้อความที่ยังไม่ได้อ่าน (Check Unread Messages)';
            $section = 'Chat & Communication (ส่วนแชทและข้อความ)';
        } elseif (str_starts_with($path, 'admin/inbox')) {
            $action = 'เปิดกล่องข้อความผู้ดูแล (Open Admin Inbox)';
            $section = 'Chat & Communication (ส่วนแชทและข้อความ)';
        } elseif (str_starts_with($path, 'admin/jobs')) {
            $action = 'ตรวจสอบงานคิวเบื้องหลัง (Monitor Background Jobs)';
            $section = 'Admin Management (ส่วนผู้ดูแลระบบ)';
        } elseif (str_starts_with($path, 'api/user')) {
            $action = 'ดึงข้อมูลโปรไฟล์ผู้ใช้ผ่าน API (Fetch User Profile API)';
            $section = 'API Service (บริการ API)';
        } elseif (str_starts_with($path, 'api/cluster/metrics')) {
            $action = 'ดึงข้อมูลสถานะคลัสเตอร์ผ่าน API (Fetch Cluster Telemetry API)';
            $section = 'API Service (บริการ API)';
        } elseif (str_starts_with($path, 'api/v1/activities')) {
            $action = 'ดึงข้อมูลรายการกิจกรรมผ่าน API (Fetch Activities API)';
            $section = 'API Service (บริการ API)';
        } elseif (str_starts_with($path, 'activities/') && $method === 'GET') {
            $action = 'ดูรายละเอียดกิจกรรม (View Activity Details)';
        } elseif (str_starts_with($path, 'activities') && $method === 'GET') {
            $action = 'ดูรายการกิจกรรม (Browse Activities)';
        } elseif (str_starts_with($path, 'admin/activities') && $method === 'GET') {
            $action = 'ดูรายการจัดการกิจกรรม (Manage Activities)';
        } elseif (str_starts_with($path, 'admin') && $method === 'GET') {
            $action = 'เข้าถึงแดชบอร์ด/จัดการระบบ (Admin Panel Access)';
        } elseif (str_starts_with($path, 'login') && $method === 'POST') {
            $action = 'ดำเนินการเข้าสู่ระบบ (Submit Login Credentials)';
        } elseif (str_starts_with($path, 'logout')) {
            $action = 'ออกจากระบบ (Logout Session)';
        } elseif ($method === 'POST') {
            $action = 'ส่งข้อมูล / บันทึกรายการใหม่ (Submit Data)';
        } elseif ($method === 'PUT' || $method === 'PATCH') {
            $action = 'อัปเดต / แก้ไขข้อมูล (Update Record)';
        } elseif ($method === 'DELETE') {
            $action = 'ลบข้อมูล (Delete Record)';
        } else {
            $action = 'เข้าชมหน้าเว็บ / เรียกดูข้อมูล (Browse Page: /' . $path . ')';
        }

        return [$action, $section];
    }
}

