<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * เซอร์วิสตรวจสอบใบหน้าและอัตลักษณ์ผ่าน Python AI Server (Server-Authoritative)
 * ทำหน้าที่เป็นศูนย์กลางการสื่อสารระหว่าง Laravel Gateway และ Python Neural Engine
 */
class FaceVerificationService
{
    protected ?string $aiServerUrl;
    protected ?string $aiServerKey;
    protected int $fallbackThreshold = 3;
    protected int $timeoutSeconds = 8;

    public function __construct()
    {
        $this->aiServerUrl = config('services.ai_server.url');
        $this->aiServerKey = config('services.ai_server.key');
    }

    /**
     * ดำเนินการตรวจสอบใบหน้าและ Liveness Anti-Spoofing บน Server (Authoritative Decision)
     *
     * @param  User   $user       ผู้ใช้ที่ต้องการตรวจสอบอัตลักษณ์
     * @param  string $imageData  ภาพถ่าย Base64 จากกล้องของอุปกรณ์
     * @param  array  $options    ตัวเลือกเพิ่มเติม (เช่น timeout, priority)
     * @return array{
     *     success: bool,
     *     is_match: bool,
     *     score_percentage: float,
     *     similarity?: float,
     *     distance?: float,
     *     liveness_passed: bool,
     *     liveness_score?: float,
     *     detector_used?: string,
     *     processing_ms: int,
     *     message?: string
     * }
     */
    public function verifyFace(User $user, string $imageData, array $options = []): array
    {
        $startTime = microtime(true);

        // 1. ตรวจสอบว่าผู้ใช้มีเวกเตอร์ใบหน้า (512D) ในระบบหรือไม่
        if (empty($user->face_descriptor)) {
            return $this->createErrorResponse('User has no registered face profile', $startTime);
        }

        // 2. ตรวจสอบการตั้งค่า AI Server
        if (empty($this->aiServerUrl)) {
            return $this->createErrorResponse('AI Server URL not configured', $startTime, [
                'error_type' => 'configuration',
            ]);
        }

        // 3. ตรวจสอบสถานะการทำงานของ AI Server (Health Check)
        $health = $this->checkServerHealth();
        if (!$health['available']) {
            return $this->createErrorResponse('AI Server unavailable', $startTime, [
                'health_status' => $health,
                'error_type'    => 'server_unavailable',
            ]);
        }

        // 4. ตรวจสอบจำนวนความล้มเหลวต่อเนื่อง (Circuit Breaker)
        $failureKey = "ai_server_failures_{$user->id}";
        $consecutiveFailures = (int) Cache::get($failureKey, 0);
        
        if ($consecutiveFailures >= $this->fallbackThreshold) {
            return $this->createErrorResponse('Too many recent failures', $startTime, [
                'consecutive_failures' => $consecutiveFailures,
                'error_type'           => 'failure_threshold',
            ]);
        }

        // 5. แปลงและตรวจสอบขนาดไฟล์ภาพ (จำกัดไม่เกิน 2MB)
        $imageDecoded = $this->decodeBase64Image($imageData);
        if (!$imageDecoded) {
            return $this->createErrorResponse('Invalid image data or file too large', $startTime);
        }

        try {
            $httpRequest = Http::timeout($this->timeoutSeconds);
            if (!empty($this->aiServerKey)) {
                $httpRequest = $httpRequest->withHeaders(['X-API-Key' => $this->aiServerKey]);
            }

            // 6. ส่งรูปภาพไปยัง Python AI Server (/verify) เพื่อทำ Detection, Liveness, Embedding, Similarity
            $response = $httpRequest
                ->attach('image', $imageDecoded, 'frame.jpg')
                ->post(rtrim($this->aiServerUrl, '/') . '/verify', [
                    'known_embedding' => json_encode($user->face_descriptor),
                    'check_liveness'  => 'true',
                ]);

            $processingTime = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                // รีเซ็ตตัวนับข้อผิดพลาดเมื่อสำเร็จ
                Cache::forget($failureKey);
                
                $result = $response->json();
                
                if ($processingTime > 4000) {
                    Log::warning("Slow Face Verification: {$processingTime}ms for user {$user->id}");
                }

                $isMatch        = (bool) ($result['is_match'] ?? false);
                $score          = (float) ($result['score_percentage'] ?? 0.0);
                $livenessPassed = (bool) ($result['liveness_passed'] ?? ($result['liveness']['passed'] ?? true));

                return array_merge($result, [
                    'success'          => true,
                    'is_match'         => $isMatch,
                    'score_percentage' => $score,
                    'liveness_passed'  => $livenessPassed,
                    'processing_ms'    => $processingTime,
                    'server_ms'        => $result['processing_ms'] ?? 0,
                    'network_ms'       => max(0, $processingTime - ((int) ($result['processing_ms'] ?? 0))),
                ]);
            }

            $this->recordFailure($failureKey);
            
            return $this->createErrorResponse('AI Server processing error', $startTime, [
                'http_status' => $response->status(),
                'error_type'  => 'server_error',
            ]);

        } catch (\Throwable $e) {
            $this->recordFailure($failureKey);
            
            Log::error("Python Face Verification error for user {$user->id}: " . $e->getMessage());

            return $this->createErrorResponse('AI Server connection failed', $startTime, [
                'error_type' => get_class($e),
                'exception'  => true,
            ]);
        }
    }

    /**
     * ตรวจสอบสถานะการเชื่อมต่อ AI Server พร้อมระบบแคช (30 วินาที)
     */
    public function checkServerHealth(): array
    {
        $cacheKey = 'ai_server_health_status';
        
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        if (empty($this->aiServerUrl)) {
            $status = [
                'available'  => false,
                'error'      => 'AI Server URL not configured',
                'checked_at' => time(),
            ];
        } else {
            try {
                $httpHealth = Http::timeout(3);
                if (!empty($this->aiServerKey)) {
                    $httpHealth = $httpHealth->withHeaders(['X-API-Key' => $this->aiServerKey]);
                }
                $response = $httpHealth->get(rtrim($this->aiServerUrl, '/') . '/health');
                
                if ($response->successful()) {
                    $healthData = $response->json();
                    $status = [
                        'available'        => true,
                        'status'           => $healthData['status'] ?? 'healthy',
                        'models'           => $healthData['models'] ?? [],
                        'pipeline'         => $healthData['pipeline'] ?? 'unknown',
                        'response_time_ms' => round(($response->transferStats?->getTransferTime() ?? 0) * 1000),
                        'checked_at'       => time(),
                    ];
                } else {
                    $status = [
                        'available'  => false,
                        'error'      => 'HTTP ' . $response->status(),
                        'checked_at' => time(),
                    ];
                }
            } catch (\Throwable $e) {
                $status = [
                    'available'      => false,
                    'error'          => $e->getMessage(),
                    'exception_type' => get_class($e),
                    'checked_at'     => time(),
                ];
            }
        }

        Cache::put($cacheKey, $status, 30);
        
        return $status;
    }

    /**
     * บันทึกความล้มเหลวของการเรียกใช้ AI Server
     */
    private function recordFailure(string $failureKey): void
    {
        $failures = ((int) Cache::get($failureKey, 0)) + 1;
        Cache::put($failureKey, $failures, 300);
        
        Log::warning("AI Server failure count for key {$failureKey}: {$failures}");
    }

    /**
     * แปลงรูปภาพ Base64 เป็น Raw Binary Data พร้อมตรวจสอบขนาด
     */
    private function decodeBase64Image(string $imageData): ?string
    {
        $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
        if ($imageData === null) {
            return null;
        }

        $decoded = base64_decode(str_replace(' ', '+', $imageData), true);
        
        if ($decoded === false || strlen($decoded) > 2097152) { // 2MB limit
            return null;
        }
        
        return $decoded;
    }

    /**
     * สร้างโครงสร้าง Error Response มาตรฐาน
     */
    private function createErrorResponse(string $message, float $startTime, array $additional = []): array
    {
        return array_merge([
            'success'          => false,
            'is_match'         => false,
            'score_percentage' => 0.0,
            'liveness_passed'  => false,
            'message'          => $message,
            'processing_ms'    => (int) round((microtime(true) - $startTime) * 1000),
            'timestamp'        => time(),
        ], $additional);
    }

    /**
     * ตรวจสอบและดึงข้อมูล Face Encodings (512D + 128D) อัตโนมัติจากภาพโปรไฟล์หากยังไม่มีในระบบ
     */
    public function ensureFaceEncodings(User $user): void
    {
        if (($user->face_descriptor && $user->face_descriptor_js) || empty($user->profile_photo)) {
            return;
        }

        $photoPath = storage_path('app/public/' . $user->profile_photo);
        if (!file_exists($photoPath) || empty($this->aiServerUrl)) {
            return;
        }

        try {
            Log::info("Auto-extracting missing face encodings for user {$user->id}");

            $httpReq = Http::timeout(10);
            if (!empty($this->aiServerKey)) {
                $httpReq = $httpReq->withHeaders(['X-API-Key' => $this->aiServerKey]);
            }

            $response = $httpReq
                ->attach('image', file_get_contents($photoPath), basename($photoPath))
                ->post(rtrim($this->aiServerUrl, '/') . '/extract');

            if ($response->successful()) {
                $aiResult = $response->json();
                $updateData = [];
                $extracted = [];

                if (!$user->face_descriptor && !empty($aiResult['embedding_512d'])) {
                    $updateData['face_descriptor'] = $aiResult['embedding_512d'];
                    $extracted[] = '512D';
                }
                if (!$user->face_descriptor_js && !empty($aiResult['embedding_128d'])) {
                    $updateData['face_descriptor_js'] = $aiResult['embedding_128d'];
                    $extracted[] = '128D';
                }
                if (!$user->face_descriptor && empty($updateData['face_descriptor']) && !empty($aiResult['embedding'])) {
                    $updateData['face_descriptor'] = $aiResult['embedding'];
                    $extracted[] = '512D (legacy)';
                }

                if (!empty($updateData)) {
                    $user->update($updateData);
                    Log::info("Auto-extracted " . implode(' + ', $extracted) . " for user {$user->id}");
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Auto-extraction failed for user {$user->id}: " . $e->getMessage());
        }
    }

    /**
     * ดึงข้อมูล Metrics และประสิทธิภาพของระบบ AI
     */
    public function getMetrics(): array
    {
        $health = $this->checkServerHealth();
        
        return [
            'ai_server' => $health,
            'system'    => [
                'memory_usage' => memory_get_usage(true),
                'peak_memory'  => memory_get_peak_usage(true),
                'timestamp'    => time(),
            ],
            'timeout_seconds'    => $this->timeoutSeconds,
            'fallback_threshold' => $this->fallbackThreshold,
        ];
    }
}