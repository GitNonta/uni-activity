<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LineController extends Controller
{
    public function __construct(
        private readonly LineService $lineService
    ) {}

    /** Redirect ไปหน้า LINE Login */
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(32);
        $request->session()->put('line_oauth_state', $state);

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => config('services.line.login_channel_id'),
            'redirect_uri'  => route('line.callback'),
            'state'         => $state,
            'scope'         => 'profile openid',
        ]);

        return redirect("https://access.line.me/oauth2/v2.1/authorize?{$params}");
    }

    /** รับ Callback จาก LINE Login และบันทึก LINE User ID */
    public function callback(Request $request): RedirectResponse
    {
        // ตรวจสอบ state
        if ($request->input('state') !== $request->session()->pull('line_oauth_state')) {
            return redirect()->route('student.profile')
                ->with('error', 'ผูก LINE ไม่สำเร็จ: ข้อมูลไม่ถูกต้อง (invalid state)');
        }

        if ($request->has('error')) {
            return redirect()->route('student.profile')
                ->with('error', 'ยกเลิกการผูก LINE');
        }

        $code = $request->input('code');
        if (!$code) {
            return redirect()->route('student.profile')
                ->with('error', 'ผูก LINE ไม่สำเร็จ: ไม่มี authorization code');
        }

        // แลก code เป็น Access Token
        $tokenData = $this->lineService->exchangeToken($code, route('line.callback'));
        if (!$tokenData || empty($tokenData['access_token'])) {
            return redirect()->route('student.profile')
                ->with('error', 'ผูก LINE ไม่สำเร็จ: ไม่สามารถรับ access token ได้');
        }

        // ดึงข้อมูล Profile จาก LINE
        $profile = $this->lineService->getLineProfile($tokenData['access_token']);
        if (!$profile || empty($profile['userId'])) {
            return redirect()->route('student.profile')
                ->with('error', 'ผูก LINE ไม่สำเร็จ: ไม่สามารถดึงข้อมูลโปรไฟล์ LINE ได้');
        }

        $lineUserId     = $profile['userId'];
        $lineDisplayName = $profile['displayName'] ?? null;

        // ตรวจสอบว่า LINE User ID นี้ถูกผูกกับบัญชีอื่นแล้วหรือยัง
        $existingUser = User::where('line_user_id', $lineUserId)
            ->where('id', '!=', Auth::id())
            ->first();

        if ($existingUser) {
            return redirect()->route('student.profile')
                ->with('error', 'บัญชี LINE นี้ถูกผูกกับผู้ใช้คนอื่นแล้ว กรุณาใช้บัญชี LINE อื่น');
        }

        // บันทึก LINE User ID
        /** @var User $user */
        $user = Auth::user();
        $user->update([
            'line_user_id'      => $lineUserId,
            'line_display_name' => $lineDisplayName,
            'line_notify_enabled' => true,
        ]);

        Log::info('User linked LINE account', [
            'user_id'      => $user->id,
            'line_user_id' => $lineUserId,
        ]);

        // ส่งข้อความต้อนรับ
        $this->lineService->pushMessage($lineUserId, [[
            'type' => 'text',
            'text' => "✅ ผูกบัญชีสำเร็จ!\n\nสวัสดี {$lineDisplayName} 👋\nตอนนี้คุณจะได้รับการแจ้งเตือนกิจกรรม ประกาศ และข่าวสารจาก UNI Activity ผ่าน LINE แล้ว",
        ]]);

        return redirect()->route('student.profile')
            ->with('success', "ผูกบัญชี LINE สำเร็จ! เชื่อมต่อกับ {$lineDisplayName} แล้ว");
    }

    /** ยกเลิกการผูก LINE */
    public function unlink(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->line_user_id) {
            return redirect()->route('student.profile')
                ->with('error', 'ยังไม่ได้ผูกบัญชี LINE');
        }

        $displayName = $user->line_display_name;
        $user->update([
            'line_user_id'        => null,
            'line_display_name'   => null,
            'line_notify_enabled' => false,
        ]);

        Log::info('User unlinked LINE account', ['user_id' => $user->id]);

        return redirect()->route('student.profile')
            ->with('success', "ยกเลิกการผูกบัญชี LINE ({$displayName}) สำเร็จ");
    }

    /** สลับเปิด/ปิดการแจ้งเตือน LINE */
    public function toggleNotify(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->line_user_id) {
            return redirect()->route('student.profile')
                ->with('error', 'กรุณาผูกบัญชี LINE ก่อน');
        }

        $user->update([
            'line_notify_enabled' => !$user->line_notify_enabled,
        ]);

        $status = $user->line_notify_enabled ? 'เปิด' : 'ปิด';
        return redirect()->route('student.profile')
            ->with('success', "{$status}การแจ้งเตือนผ่าน LINE แล้ว");
    }

    /** LINE Webhook endpoint (รับ events จาก LINE) */
    public function webhook(Request $request): Response
    {
        // Verify signature
        $signature = $request->header('X-Line-Signature', '');
        $body      = $request->getContent();
        $hash      = base64_encode(hash_hmac('sha256', $body, config('services.line.channel_secret'), true));

        if (!hash_equals($hash, $signature)) {
            Log::warning('LINE webhook invalid signature');
            return response('Forbidden', 403);
        }

        $events = $request->input('events', []);

        foreach ($events as $event) {
            // รองรับ Follow event (เมื่อผู้ใช้ Add Friend)
            if ($event['type'] === 'follow') {
                $lineUserId = $event['source']['userId'] ?? null;
                if ($lineUserId) {
                    $this->lineService->pushMessage($lineUserId, [[
                        'type' => 'text',
                        'text' => "👋 สวัสดีครับ!\n\nขอบคุณที่ Follow UNI Activity\n\nกรุณาเข้าสู่ระบบที่เว็บไซต์และผูกบัญชี LINE เพื่อรับการแจ้งเตือนกิจกรรมและประกาศต่างๆ ได้เลยครับ 🎓",
                    ]]);
                }
            }
        }

        return response('OK', 200);
    }
}
