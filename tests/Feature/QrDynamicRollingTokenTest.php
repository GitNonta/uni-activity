<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class QrDynamicRollingTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function createActivity(): Activity
    {
        $category = ActivityCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'General', 'color' => '#000000', 'min_hours_required' => 0]
        );

        $creator = User::firstOrCreate(
            ['email' => 'admin_qr@pkru.ac.th'],
            ['role' => 'admin', 'full_name' => 'Admin', 'password' => bcrypt('password')]
        );

        return Activity::create([
            'title'             => 'Rolling QR Activity',
            'location'          => 'Main Hall',
            'activity_date'     => now()->format('Y-m-d'),
            'start_time'        => '09:00',
            'end_time'          => '12:00',
            'activity_hours'    => 3,
            'max_participants'  => 50,
            'register_open_at'  => now()->subDay(),
            'register_close_at' => now()->addDays(2),
            'checkin_open_at'   => now()->subHour(),
            'checkin_close_at'  => now()->addHours(3),
            'category_id'       => $category->id,
            'scope'             => 'university',
            'status'            => 'open',
            'created_by'        => $creator->id,
            'qr_token'          => Str::random(32),
            'qr_checkout_token' => Str::random(32),
        ]);
    }

    public function test_qr_service_generates_dynamic_rolling_payload(): void
    {
        $service = new QrCodeService();
        $activity = $this->createActivity();

        $payload = $service->generateDynamicPayload($activity, false, 30);

        $this->assertEquals(2, $payload['qr_version']);
        $this->assertEquals($activity->qr_token, $payload['token']);
        $this->assertNotEmpty($payload['nonce']);
        $this->assertNotEmpty($payload['signature']);
        $this->assertGreaterThan(time(), $payload['expires_at']);
        $this->assertStringContainsString('v=2', $payload['url']);
        $this->assertStringContainsString('n=', $payload['url']);
    }

    public function test_qr_service_verifies_valid_dynamic_qr_payload(): void
    {
        $service = new QrCodeService();
        $activity = $this->createActivity();

        $payload = $service->generateDynamicPayload($activity, false, 30);

        $verification = $service->verifyDynamicPayload($activity->id, $payload['token'], [
            'v'   => $payload['qr_version'],
            'n'   => $payload['nonce'],
            'iat' => $payload['issued_at'],
            'exp' => $payload['expires_at'],
            'sig' => $payload['signature'],
        ]);

        $this->assertTrue($verification['is_dynamic']);
        $this->assertTrue($verification['is_valid']);
        $this->assertFalse($verification['is_replay']);
        $this->assertFalse($verification['is_expired']);
    }

    public function test_qr_service_detects_and_prevents_nonce_replay_attacks(): void
    {
        $service = new QrCodeService();
        $activity = $this->createActivity();

        $payload = $service->generateDynamicPayload($activity, false, 30);

        $params = [
            'v'   => $payload['qr_version'],
            'n'   => $payload['nonce'],
            'iat' => $payload['issued_at'],
            'exp' => $payload['expires_at'],
            'sig' => $payload['signature'],
        ];

        // First verification succeeds
        $first = $service->verifyDynamicPayload($activity->id, $payload['token'], $params);
        $this->assertTrue($first['is_valid']);
        $this->assertFalse($first['is_replay']);

        // Second verification with the exact same nonce (Replay from LINE screenshot) is detected and flagged
        $second = $service->verifyDynamicPayload($activity->id, $payload['token'], $params);
        $this->assertFalse($second['is_valid']);
        $this->assertTrue($second['is_replay']);
    }

    public function test_qr_service_detects_expired_rolling_tokens(): void
    {
        $service = new QrCodeService();
        $activity = $this->createActivity();

        $expiredIat = time() - 100;
        $expiredExp = time() - 70;
        $nonce = Str::random(16);

        // Sign with expired timestamps
        $appKey = (string) config('app.key', 'secret-key-fallback');
        $sig = hash_hmac('sha256', "{$activity->id}:{$activity->qr_token}:{$nonce}:{$expiredIat}:{$expiredExp}", $appKey);

        $verification = $service->verifyDynamicPayload($activity->id, $activity->qr_token, [
            'v'   => 2,
            'n'   => $nonce,
            'iat' => $expiredIat,
            'exp' => $expiredExp,
            'sig' => $sig,
        ]);

        $this->assertTrue($verification['is_dynamic']);
        $this->assertFalse($verification['is_valid']);
        $this->assertTrue($verification['is_expired']);
    }

    public function test_qr_service_maintains_backward_compatibility_with_static_qr(): void
    {
        $service = new QrCodeService();
        $activity = $this->createActivity();

        $verification = $service->verifyDynamicPayload($activity->id, $activity->qr_token, []);

        $this->assertFalse($verification['is_dynamic']);
        $this->assertTrue($verification['is_valid']);
        $this->assertFalse($verification['is_replay']);
    }
}
