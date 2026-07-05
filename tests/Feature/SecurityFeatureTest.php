<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\SecurityLog;
use App\Models\User;
use App\Services\DeviceFingerprintService;
use App\Services\SecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature Tests: Security — Multi-Account & Suspicious Check-in Detection
 */
class SecurityFeatureTest extends TestCase
{
    use RefreshDatabase;

    // ─── DeviceFingerprintService ─────────────────────────────────────

    public function test_fingerprint_generates_consistent_hash(): void
    {
        $service = new DeviceFingerprintService();
        $request = Request::create('/login', 'POST', [], [], [], [
            'REMOTE_ADDR'     => '192.168.1.1',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0)',
        ]);

        $fp1 = $service->generate($request);
        $fp2 = $service->generate($request);

        $this->assertSame($fp1, $fp2);
        $this->assertSame(64, strlen($fp1)); // SHA-256 = 64 chars
    }

    public function test_different_ip_produces_different_fingerprint(): void
    {
        $service = new DeviceFingerprintService();

        $req1 = Request::create('/login', 'POST', [], [], [], ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_USER_AGENT' => 'Chrome']);
        $req2 = Request::create('/login', 'POST', [], [], [], ['REMOTE_ADDR' => '10.0.0.2', 'HTTP_USER_AGENT' => 'Chrome']);

        $this->assertNotSame($service->generate($req1), $service->generate($req2));
    }

    // ─── Multi-Account Detection ──────────────────────────────────────

    public function test_multi_account_login_is_detected_and_logged(): void
    {
        Cache::flush();

        $userA = User::factory()->create(['role' => 'student']);
        $userB = User::factory()->create(['role' => 'student']);

        $request = Request::create('/login', 'POST', [], [], [], [
            'REMOTE_ADDR'     => '192.168.1.100',
            'HTTP_USER_AGENT' => 'TestBrowser/1.0',
        ]);

        /** @var SecurityService $secService */
        $secService = app(SecurityService::class);

        // User A login → ไม่มี multi-account ยัง
        $result = $secService->checkAndLogMultiAccountLogin($request, $userA->id);
        $this->assertFalse($result);
        $this->assertDatabaseCount('security_logs', 0);

        // User B login จาก IP/Device เดียวกัน → ตรวจพบ
        $result = $secService->checkAndLogMultiAccountLogin($request, $userB->id);
        $this->assertTrue($result);
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'multi_account_login',
            'ip_address' => '192.168.1.100',
        ]);
    }

    public function test_same_user_login_does_not_trigger_multi_account(): void
    {
        Cache::flush();

        $user = User::factory()->create(['role' => 'student']);
        $request = Request::create('/login', 'POST', [], [], [], [
            'REMOTE_ADDR'     => '192.168.1.50',
            'HTTP_USER_AGENT' => 'SameBrowser/2.0',
        ]);

        /** @var SecurityService $secService */
        $secService = app(SecurityService::class);

        // Login user A 2 ครั้ง ไม่ควร trigger
        $secService->checkAndLogMultiAccountLogin($request, $user->id);
        $result = $secService->checkAndLogMultiAccountLogin($request, $user->id);

        $this->assertFalse($result);
        $this->assertDatabaseCount('security_logs', 0);
    }

    // ─── Suspicious Check-in Detection ───────────────────────────────

    public function test_same_device_checkin_for_different_users_flags_suspicious(): void
    {
        $activity = Activity::factory()->create([
            'checkin_open_at'  => now()->subHour(),
            'checkin_close_at' => now()->addHour(),
        ]);
        $userA = User::factory()->create(['role' => 'student']);
        $userB = User::factory()->create(['role' => 'student']);

        $fingerprint = 'abc123fingerprint000000000000000000000000000000000000000000000000';

        // UserA เช็คอินก่อน
        Attendance::factory()->create([
            'user_id'           => $userA->id,
            'activity_id'       => $activity->id,
            'device_fingerprint'=> $fingerprint,
            'checked_in_at'     => now()->subMinutes(3),
        ]);

        /** @var DeviceFingerprintService $fpService */
        $fpService = app(DeviceFingerprintService::class);

        // UserB ใช้ fingerprint เดียวกัน → น่าสงสัย
        $otherUsers = $fpService->detectSuspiciousCheckin($fingerprint, $activity->id, $userB->id);

        $this->assertNotNull($otherUsers);
        $this->assertContains($userA->id, $otherUsers);
    }

    public function test_different_device_checkin_is_not_flagged(): void
    {
        $activity = Activity::factory()->create();
        $userA = User::factory()->create(['role' => 'student']);
        $userB = User::factory()->create(['role' => 'student']);

        Attendance::factory()->create([
            'user_id'           => $userA->id,
            'activity_id'       => $activity->id,
            'device_fingerprint'=> 'device_fingerprint_A' . str_repeat('0', 44),
            'checked_in_at'     => now()->subMinutes(3),
        ]);

        /** @var DeviceFingerprintService $fpService */
        $fpService = app(DeviceFingerprintService::class);

        // UserB ใช้ fingerprint ต่างกัน → ไม่น่าสงสัย
        $result = $fpService->detectSuspiciousCheckin(
            fingerprint:   'device_fingerprint_B' . str_repeat('0', 44),
            activityId:    $activity->id,
            currentUserId: $userB->id,
        );

        $this->assertNull($result);
    }

    // ─── SecurityLog Model ────────────────────────────────────────────

    public function test_security_log_can_be_created(): void
    {
        $user = User::factory()->create();

        SecurityLog::create([
            'user_id'    => $user->id,
            'event_type' => 'multi_account_login',
            'ip_address' => '1.2.3.4',
        ]);

        $this->assertDatabaseHas('security_logs', [
            'user_id'    => $user->id,
            'event_type' => 'multi_account_login',
            'is_reviewed'=> false,
        ]);
    }

    public function test_security_log_event_type_label_is_correct(): void
    {
        $log = new SecurityLog(['event_type' => 'multi_account_login']);
        $this->assertStringContainsString('Login', $log->event_type_label);

        $log2 = new SecurityLog(['event_type' => 'suspicious_checkin']);
        $this->assertStringContainsString('เช็คอิน', $log2->event_type_label);
    }

    // ─── Login Records Fingerprint ────────────────────────────────────

    public function test_user_login_ip_and_fingerprint_are_recorded(): void
    {
        $user = User::factory()->create([
            'role'      => 'student',
            'last_login_ip'          => null,
            'last_device_fingerprint'=> null,
        ]);

        $user->update([
            'last_login_ip'           => '172.16.0.1',
            'last_login_at'           => now(),
            'last_device_fingerprint' => hash('sha256', 'test'),
        ]);

        $this->assertNotNull($user->fresh()->last_login_ip);
        $this->assertNotNull($user->fresh()->last_device_fingerprint);
        $this->assertSame('172.16.0.1', $user->fresh()->last_login_ip);
    }

    // ─── Admin Security Log View ──────────────────────────────────────

    public function test_admin_can_view_security_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        SecurityLog::factory()->create(['event_type' => 'multi_account_login']);

        $response = $this->actingAs($admin)->get(route('admin.security-logs.index'));

        $response->assertOk();
        $response->assertSee('Security Logs');
    }

    public function test_student_cannot_access_security_logs(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->get(route('admin.security-logs.index'));

        // Middleware ของ admin redirects student ไปหน้า activities (302)
        // หรือ 403 ขึ้นอยู่กับ middleware config — ทั้งสองแบบถือว่าปฏิเสธการเข้าถึง
        $this->assertTrue(
            $response->status() === 403 || $response->status() === 302,
            "Student should not access security logs, got status: " . $response->status()
        );
    }
}
