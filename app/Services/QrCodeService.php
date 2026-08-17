<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * เซอร์วิส QR Code
 * จัดการสร้างและตรวจสอบ Token, Dynamic Rolling QR, HMAC Signatures, และ One-Time Nonce Replay Guard
 */
class QrCodeService
{
    public const CURRENT_VERSION = 2;

    /**
     * สร้าง token สุ่ม 64 ตัวอักษรสำหรับกิจกรรม
     */
    public function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * สร้าง URL เช็คอินแบบดั้งเดิมจาก token
     */
    public function generateQrUrl(string $token): string
    {
        return url("/check-in/{$token}");
    }

    /**
     * สร้าง Dynamic Rolling QR Payload พร้อม HMAC Signature และ Nonce ป้องกัน Replay
     *
     * @param  Activity $activity
     * @param  bool     $isCheckout
     * @param  int      $ttlSeconds
     * @return array{
     *     qr_version: int,
     *     token: string,
     *     is_checkout: bool,
     *     nonce: string,
     *     issued_at: int,
     *     expires_at: int,
     *     signature: string,
     *     url: string,
     *     time_remaining: int
     * }
     */
    public function generateDynamicPayload(Activity $activity, bool $isCheckout = false, int $ttlSeconds = 30): array
    {
        $token = $isCheckout ? ($activity->qr_checkout_token ?? $activity->qr_token) : $activity->qr_token;
        $nonce = Str::random(16);
        $issuedAt = time();
        $expiresAt = $issuedAt + $ttlSeconds;

        $signature = $this->createSignature($activity->id, $token, $nonce, $issuedAt, $expiresAt);

        $queryParams = http_build_query([
            'v'   => self::CURRENT_VERSION,
            'n'   => $nonce,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'sig' => $signature,
        ]);

        $url = url("/check-in/{$token}?" . $queryParams);

        return [
            'qr_version'     => self::CURRENT_VERSION,
            'token'          => $token,
            'is_checkout'    => $isCheckout,
            'nonce'          => $nonce,
            'issued_at'      => $issuedAt,
            'expires_at'     => $expiresAt,
            'signature'      => $signature,
            'url'            => $url,
            'time_remaining' => max(0, $expiresAt - time()),
        ];
    }

    /**
     * ตรวจสอบความถูกต้องของ Dynamic QR Payload และดักจับ Nonce Replay
     *
     * @param  int|null             $activityId
     * @param  string               $token
     * @param  array<string, mixed> $params
     * @param  int                  $graceSeconds
     * @return array{
     *     is_dynamic: bool,
     *     is_valid: bool,
     *     is_replay: bool,
     *     is_expired: bool,
     *     qr_version: int,
     *     nonce: ?string,
     *     issued_at: ?int,
     *     expires_at: ?int,
     *     used_at: int,
     *     message?: string
     * }
     */
    public function verifyDynamicPayload(
        ?int $activityId,
        string $token,
        array $params = [],
        int $graceSeconds = 15
    ): array {
        $now = time();

        // หากไม่มีพารามิเตอร์ไดนามิก (สแกนผ่าน Static QR แบบดั้งเดิม)
        if (empty($params['sig']) || empty($params['n'])) {
            return [
                'is_dynamic' => false,
                'is_valid'   => true,
                'is_replay'  => false,
                'is_expired' => false,
                'qr_version' => 1,
                'nonce'      => null,
                'issued_at'  => null,
                'expires_at' => null,
                'used_at'    => $now,
            ];
        }

        $version   = (int) ($params['v'] ?? 1);
        $nonce     = (string) $params['n'];
        $issuedAt  = (int) ($params['iat'] ?? 0);
        $expiresAt = (int) ($params['exp'] ?? 0);
        $signature = (string) $params['sig'];

        // 1. ตรวจสอบอายุของ Token (พร้อม Grace Period ป้องกัน Clock Drift)
        $isExpired = ($now > ($expiresAt + $graceSeconds)) || ($now < ($issuedAt - $graceSeconds));
        if ($isExpired) {
            return [
                'is_dynamic' => true,
                'is_valid'   => false,
                'is_replay'  => false,
                'is_expired' => true,
                'qr_version' => $version,
                'nonce'      => $nonce,
                'issued_at'  => $issuedAt,
                'expires_at' => $expiresAt,
                'used_at'    => $now,
                'message'    => 'QR Code หมดอายุแล้ว กรุณาสแกนรหัสใหม่จากหน้าจอ',
            ];
        }

        // 2. ตรวจสอบ HMAC Signature
        $expectedSignature = $this->createSignature($activityId ?? 0, $token, $nonce, $issuedAt, $expiresAt);
        $isValidSignature = hash_equals($expectedSignature, $signature);

        if (!$isValidSignature) {
            return [
                'is_dynamic' => true,
                'is_valid'   => false,
                'is_replay'  => false,
                'is_expired' => false,
                'qr_version' => $version,
                'nonce'      => $nonce,
                'issued_at'  => $issuedAt,
                'expires_at' => $expiresAt,
                'used_at'    => $now,
                'message'    => 'ลายเซ็นดิจิทัลของ QR Code ไม่ถูกต้อง',
            ];
        }

        // 3. ตรวจสอบและบริโภค One-Time Nonce (Atomic Lock ใน Redis) เพื่อป้องกันการส่งต่อภาพ QR (Replay Guard)
        $nonceCacheKey = "qr_nonce_used:{$nonce}";
        $isReplay = !Cache::add($nonceCacheKey, $now, 300);

        return [
            'is_dynamic' => true,
            'is_valid'   => !$isReplay,
            'is_replay'  => $isReplay,
            'is_expired' => false,
            'qr_version' => $version,
            'nonce'      => $nonce,
            'issued_at'  => $issuedAt,
            'expires_at' => $expiresAt,
            'used_at'    => $now,
            'message'    => $isReplay ? 'QR Code นี้ถูกใช้งานไปแล้ว (Replay detected)' : null,
        ];
    }

    /**
     * สร้างลายเซ็นดิจิทัล HMAC-SHA256
     */
    protected function createSignature(int $activityId, string $token, string $nonce, int $issuedAt, int $expiresAt): string
    {
        $appKey = (string) config('app.key', 'secret-key-fallback');
        $payload = "{$activityId}:{$token}:{$nonce}:{$issuedAt}:{$expiresAt}";

        return hash_hmac('sha256', $payload, $appKey);
    }
}
