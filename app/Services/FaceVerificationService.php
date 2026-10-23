<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * เซอร์วิสตรวจสอบใบหน้าและอัตลักษณ์ผ่าน Python AI Cluster (Server-Authoritative)
 * ผสานระบบ AI Load Balancer, Circuit Breaker, Health Checking, และ Automatic Failover
 */
class FaceVerificationService
{
    protected ?string $aiServerUrl;
    protected ?string $aiServerKey;
    protected int $fallbackThreshold = 3;
    protected int $timeoutSeconds = 8;
    protected AiLoadBalancerService $loadBalancer;

    public function __construct(?AiLoadBalancerService $loadBalancer = null)
    {
        $this->loadBalancer = $loadBalancer ?? app(AiLoadBalancerService::class);
        $this->aiServerUrl  = (string) config('services.ai_server.url');
        $this->aiServerKey  = (string) config('services.ai_server.key');
    }

    /**
     * ดำเนินการตรวจสอบใบหน้าและ Liveness Anti-Spoofing บน Server (Authoritative Decision)
     *
     * @param  User   $user       ผู้ใช้ที่ต้องการตรวจสอบอัตลักษณ์
     * @param  string $imageData  ภาพถ่าย Base64 จากกล้องของอุปกรณ์
     * @param  array<string, mixed>  $options    ตัวเลือกเพิ่มเติม
     * @return array<string, mixed>
     */
    public function verifyFace(User $user, string $imageData, array $options = []): array
    {
        $startTime = microtime(true);

        // 1. ตรวจสอบว่าผู้ใช้มีเวกเตอร์ใบหน้า (512D) ในระบบหรือไม่
        if (empty($user->face_descriptor)) {
            return $this->createErrorResponse('User has no registered face profile', $startTime);
        }

        // 2. แปลงและตรวจสอบขนาดไฟล์ภาพ (จำกัดไม่เกิน 2MB)
        $imageDecoded = $this->decodeBase64Image($imageData);
        if (!$imageDecoded) {
            return $this->createErrorResponse('Invalid image data or file too large', $startTime);
        }

        try {
            // 4. เส้นทางหลัก: face_api (ncnn native บน S2) — ถ้าล่มจะ fallback ไป AI cluster
            $result   = null;
            $nodeUsed = '';
            $faceApi  = app(FaceExtractApiService::class);
            if ($faceApi->isEnabled()) {
                try {
                    $faceApiResult = $faceApi->verify(
                        $imageDecoded,
                        (array) $user->face_descriptor,
                        (bool) ($options['check_liveness'] ?? true)
                    );
                    $result   = $faceApiResult;
                    $nodeUsed = (string) ($faceApiResult['node'] ?? 'face-api');

                    // node นี้ใช้ liveness แบบ heuristic (ไม่มี anti-spoof model) — บันทึกไว้เพื่อ audit
                    if (($faceApiResult['liveness_mode'] ?? '') === 'heuristic') {
                        Log::info('Face verify via face_api (heuristic liveness)', [
                            'user_id'       => $user->id,
                            'is_match'      => $faceApiResult['is_match'] ?? null,
                            'similarity'    => $faceApiResult['similarity'] ?? null,
                            'liveness_pass' => $faceApiResult['liveness_passed'] ?? null,
                            'rejection'     => $faceApiResult['liveness_checks']['rejection_reason'] ?? null,
                        ]);
                    }
                } catch (Throwable $e) {
                    Log::warning("face_api verify failed for user {$user->id}, falling back to AI cluster: " . $e->getMessage());
                }
            }

            if ($result === null) {
                // AI Load Balancer (เดิม) พร้อมระบบกระจายโหลดและ Automatic Failover
                $lbResponse = $this->loadBalancer->executeWithFailover(
                    function (string $nodeUrl, string $apiKey, int $timeout) use ($imageDecoded, $user): array {
                        $httpRequest = Http::timeout($timeout);
                        $fwdHeaders = ['X-API-Key' => $apiKey];
                        /* UI build attribution: lets the AI node log which scan-page
                           build produced each frame (stale cached pages are then
                           visible in the node log instead of being silent). */
                        if ($uiBuild = (string) request()->header('X-Scan-UI')) {
                            $fwdHeaders['X-Scan-UI'] = $uiBuild;
                        }
                        $httpRequest = $httpRequest->withHeaders($fwdHeaders);

                        $response = $httpRequest
                            ->attach('image', $imageDecoded, 'frame.jpg')
                            ->post(rtrim($nodeUrl, '/') . '/verify', [
                                'known_embedding' => json_encode($user->face_descriptor),
                                'check_liveness'  => 'true',
                            ]);

                        if (!$response->successful()) {
                            throw new \RuntimeException("Node {$nodeUrl} returned HTTP {$response->status()}: " . $response->body());
                        }

                        return $response->json();
                    }
                );

                $result   = $lbResponse['result'];
                $nodeUsed = $lbResponse['node_used'];
            }

            $processingTime = (int) round((microtime(true) - $startTime) * 1000);

            if ($processingTime > 4000) {
                Log::warning("Slow Face Verification: {$processingTime}ms for user {$user->id} on node {$nodeUsed}");
            }

            $isMatch        = (bool) ($result['is_match'] ?? false);
            $score          = (float) ($result['score_percentage'] ?? 0.0);
            $livenessPassed = (bool) ($result['liveness_passed'] ?? ($result['liveness']['passed'] ?? true));
            $rejection      = $this->classifyRejection($result, $isMatch);

            return array_merge($result, [
                'status'           => 'success',
                'success'          => true,
                'is_match'         => $isMatch,
                'score_percentage' => $score,
                'liveness_passed'  => $livenessPassed,
                'rejection_reason' => $rejection['reason'],
                'rejection_kind'   => $rejection['kind'],
                'rejection_message'=> $rejection['message'],
                'processing_ms'    => $processingTime,
                'server_ms'        => $result['processing_ms'] ?? 0,
                'network_ms'       => max(0, $processingTime - ((int) ($result['processing_ms'] ?? 0))),
                'node_used'        => $nodeUsed,
                'liveness_mode'    => $result['liveness_mode'] ?? null,
                'failovers'        => $lbResponse['failovers'] ?? 0,
            ]);
        } catch (Throwable $e) {
            Log::error("Python Face Verification cluster error for user {$user->id}: " . $e->getMessage());

            return $this->createErrorResponse('AI Server cluster connection failed: ' . $e->getMessage(), $startTime, [
                'error_type' => 'server_unavailable',
                'exception'  => true,
            ]);
        }
    }

    /**
     * ตรวจสอบสถานะการเชื่อมต่อ AI Server Cluster (พร้อม Cache 10 วินาที)
     *
     * @return array<string, mixed>
     */
    public function checkServerHealth(): array
    {
        $allHealth = $this->loadBalancer->checkAllNodesHealth();

        // face_api (ncnn native) แสดงรวมในรายการ node เดียวกันเพื่อให้ dashboard เห็นทั้งไปป์ไลน์
        $faceApi = app(FaceExtractApiService::class);
        foreach ($faceApi->healthAll() as $faceHealth) {
            $allHealth[] = [
                'url'          => (string) ($faceHealth['url'] ?? ''),
                'available'    => (bool) ($faceHealth['available'] ?? false),
                'status'       => ($faceHealth['available'] ?? false) ? 'healthy' : 'down',
                'models'       => $faceHealth['models'] ?? [],
                'backend'      => $faceHealth['backend'] ?? null,
                'pipeline'     => $faceHealth['pipeline'] ?? null,
                'latency_ms'   => $faceHealth['latency_ms'] ?? null,
                'circuit_open' => false,
                'error'        => $faceHealth['error'] ?? null,
                'checked_at'   => time(),
            ];
        }

        $availableNodes = array_filter($allHealth, fn(array $n) => ($n['available'] ?? false) === true);

        if (!empty($availableNodes)) {
            $first = reset($availableNodes);
            return [
                'available'        => true,
                'status'           => 'healthy',
                'nodes_healthy'    => count($availableNodes),
                'total_nodes'      => count($allHealth),
                'models'           => $first['models'] ?? ['insightface' => true, 'yolov8' => true, 'liveness' => true],
                'pipeline'         => 'insightface+scrfd+yolov8',
                'response_time_ms' => $first['latency_ms'] ?? 0,
                'checked_at'       => time(),
                'nodes'            => $allHealth,
            ];
        }

        return [
            'available'     => false,
            'status'        => 'unavailable',
            'nodes_healthy' => 0,
            'total_nodes'   => count($allHealth),
            'checked_at'    => time(),
            'nodes'         => $allHealth,
        ];
    }

    /**
     * ตรวจสอบและดึงข้อมูล Face Encodings (512D + 128D) อัตโนมัติจากภาพโปรไฟล์ผ่าน Load Balancer
     */
    public function ensureFaceEncodings(User $user): void
    {
        if (($user->face_descriptor && $user->face_descriptor_js) || empty($user->profile_photo)) {
            return;
        }

        $photoPath = storage_path('app/public/' . $user->profile_photo);
        if (!file_exists($photoPath)) {
            return;
        }

        try {
            $imageBytes = file_get_contents($photoPath);
            if (!$imageBytes) {
                return;
            }

            // เส้นทางหลัก: face_api (ncnn native) — ถ้าล้มเหลวจึงกลับไปใช้ AI cluster เดิม
            $aiResult = null;
            $nodeUsed = '';
            $faceApi  = app(FaceExtractApiService::class);
            if ($faceApi->isEnabled()) {
                try {
                    $aiResult = $faceApi->extract($imageBytes, basename($photoPath));
                    $nodeUsed = (string) ($aiResult['node'] ?? 'face-api');
                } catch (Throwable $e) {
                    Log::warning("face_api extract failed for user {$user->id}, falling back to AI cluster: " . $e->getMessage());
                }
            }

            if ($aiResult === null) {
                $lbResponse = $this->loadBalancer->executeWithFailover(
                    function (string $nodeUrl, string $apiKey, int $timeout) use ($imageBytes, $photoPath): array {
                    $httpReq = Http::timeout($timeout);
                    if (!empty($apiKey)) {
                        $httpReq = $httpReq->withHeaders(['X-API-Key' => $apiKey]);
                    }

                    $response = $httpReq
                        ->attach('image', $imageBytes, basename($photoPath))
                        ->post(rtrim($nodeUrl, '/') . '/extract');

                    if (!$response->successful()) {
                        throw new \RuntimeException("Extract failed on {$nodeUrl} with HTTP {$response->status()}");
                    }

                        return $response->json();
                    }
                );
                $aiResult = $lbResponse['result'];
                $nodeUsed = $lbResponse['node_used'];
            }

            $updateData = [];

            if (!$user->face_descriptor && !empty($aiResult['embedding_512d'])) {
                $updateData['face_descriptor'] = $aiResult['embedding_512d'];
            } elseif (!$user->face_descriptor && !empty($aiResult['embedding'])) {
                $updateData['face_descriptor'] = $aiResult['embedding'];
            }

            if (!$user->face_descriptor_js && !empty($aiResult['embedding_128d'])) {
                $updateData['face_descriptor_js'] = $aiResult['embedding_128d'];
            }

            if (!empty($updateData)) {
                $user->update($updateData);
                Log::info("FaceVerificationService: Encodings auto-extracted for user {$user->id} on {$nodeUsed}");
            }
        } catch (Throwable $e) {
            Log::warning("Auto-extraction failed for user {$user->id}: " . $e->getMessage());
        }
    }

    /**
     * ดึงข้อมูล Metrics และประสิทธิภาพของระบบ AI Cluster
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        $health = $this->checkServerHealth();

        return [
            'ai_cluster'          => $health,
            'system'              => [
                'memory_usage' => memory_get_usage(true),
                'peak_memory'  => memory_get_peak_usage(true),
                'timestamp'    => time(),
            ],
            'timeout_seconds'     => $this->timeoutSeconds,
            'fallback_threshold'  => $this->fallbackThreshold,
        ];
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
     * แปลง rejection ของ liveness/คุณภาพภาพให้ผู้เรียกใช้แยกได้ว่า
     * "ภาพใช้ไม่ได้ ให้ถ่ายใหม่" (quality) หรือ "สงสัยปลอม/ไม่ใช่ตัวจริง" (spoof)
     *
     * @param  array<string, mixed> $result
     * @return array{reason: ?string, kind: ?string, message: ?string}
     */
    private function classifyRejection(array $result, bool $isMatch): array
    {
        $reason = (string) ($result['liveness_checks']['rejection_reason']
            ?? $result['depth_liveness_checks']['rejection_reason']
            ?? '');

        if ($reason === '') {
            if ($isMatch) {
                return ['reason' => null, 'kind' => null, 'message' => null];
            }

            $reason = (string) ($result['status'] ?? '') === 'no_face' ? 'no_face' : 'face_mismatch';
        }

        return match ($reason) {
            'face_too_small'      => ['reason' => $reason, 'kind' => 'quality', 'message' => 'ใบหน้าเล็กเกินไปในภาพ กรุณาถ่ายให้ใกล้ขึ้นและให้ใบหน้าอยู่กลางภาพ'],
            'image_blurry'        => ['reason' => $reason, 'kind' => 'quality', 'message' => 'ภาพสั่นหรือเบลอ กรุณาถือกล้องให้นิ่งแล้วถ่ายใหม่'],
            'bad_exposure'        => ['reason' => $reason, 'kind' => 'quality', 'message' => 'ภาพสว่างหรือมืดเกินไป กรุณาหาแสงที่พอเหมาะแล้วถ่ายใหม่'],
            'low_color_variance'  => ['reason' => $reason, 'kind' => 'quality', 'message' => 'ภาพขาดรายละเอียดสี (อาจเกิดจากแสงจ้า) กรุณาถ่ายใหม่ในที่แสงปกติ'],
            'no_face'             => ['reason' => $reason, 'kind' => 'quality', 'message' => 'ไม่พบใบหน้าในภาพ กรุณาจัดใบหน้าให้อยู่ในกรอบและถ่ายใหม่'],
            'face_mismatch'       => ['reason' => $reason, 'kind' => 'mismatch', 'message' => 'ใบหน้าไม่ตรงกับข้อมูลที่ลงทะเบียนไว้'],
            'screen_moire_detected' => ['reason' => $reason, 'kind' => 'spoof', 'message' => 'ตรวจพบการใช้หน้าจอดิจิทัล (Screen Replay)'],
            'glass_glare_detected'  => ['reason' => $reason, 'kind' => 'spoof', 'message' => 'ตรวจพบแสงสะท้อนจอกระจกหรือแผ่นฟิล์ม'],
            'color_gamut_rejected'  => ['reason' => $reason, 'kind' => 'spoof', 'message' => 'ตรวจพบภาพถ่ายหรือภาพพิมพ์ (Photo Attack)'],
            default                 => ['reason' => $reason, 'kind' => 'spoof', 'message' => 'ตรวจพบรูปถ่ายหรือภาพปลอมบนหน้าจอ'],
        };
    }

    /**
     * สร้างโครงสร้าง Error Response มาตรฐาน
     *
     * @param array<string, mixed> $additional
     * @return array<string, mixed>
     */
    private function createErrorResponse(string $message, float $startTime, array $additional = []): array
    {
        return array_merge([
            'status'           => 'error',
            'success'          => false,
            'is_match'         => false,
            'score_percentage' => 0.0,
            'liveness_passed'  => false,
            'message'          => $message,
            'processing_ms'    => (int) round((microtime(true) - $startTime) * 1000),
            'timestamp'        => time(),
        ], $additional);
    }
}