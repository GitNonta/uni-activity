<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Centralized Face Verification Service
 * Manages the balance between Python AI Server and JavaScript processing
 */
class FaceVerificationService
{
    protected $aiServerUrl;
    protected $fallbackThreshold = 3;
    protected $timeoutSeconds = 8;

    public function __construct()
    {
        $this->aiServerUrl = config('services.ai_server.url');
    }

    /**
     * Smart face verification with automatic fallback
     */
    public function verifyFace(User $user, string $imageData, array $options = []): array
    {
        $startTime = microtime(true);
        $mode = $options['mode'] ?? 'hybrid';
        $priority = $options['priority'] ?? 'speed';

        // Check user has face descriptors
        if (!$user->face_descriptor) {
            return $this->createErrorResponse('User has no face profile', $startTime);
        }

        // Determine verification strategy
        switch ($mode) {
            case 'python':
                return $this->pythonVerification($user, $imageData, $startTime);
                
            case 'js':
                return $this->javascriptVerificationSupport($user, $startTime);
                
            case 'hybrid':
            default:
                return $this->hybridVerification($user, $imageData, $startTime, $priority);
        }
    }

    /**
     * Python AI Server verification
     */
    private function pythonVerification(User $user, string $imageData, float $startTime): array
    {
        if (empty($this->aiServerUrl)) {
            return $this->createErrorResponse('AI Server not configured', $startTime, [
                'fallback_recommended' => true,
                'error_type' => 'configuration'
            ]);
        }

        // Check server health first
        $health = $this->checkServerHealth();
        if (!$health['available']) {
            return $this->createErrorResponse('AI Server unavailable', $startTime, [
                'fallback_recommended' => true,
                'health_status' => $health,
                'error_type' => 'server_unavailable'
            ]);
        }

        // Check consecutive failures
        $failureKey = "ai_server_failures_{$user->id}";
        $consecutiveFailures = Cache::get($failureKey, 0);
        
        if ($consecutiveFailures >= $this->fallbackThreshold) {
            return $this->createErrorResponse('Too many recent failures', $startTime, [
                'fallback_recommended' => true,
                'consecutive_failures' => $consecutiveFailures,
                'error_type' => 'failure_threshold'
            ]);
        }

        // Process image
        $imageDecoded = $this->decodeBase64Image($imageData);
        if (!$imageDecoded) {
            return $this->createErrorResponse('Invalid image data', $startTime);
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->attach('image', $imageDecoded, 'frame.jpg')
                ->post(rtrim($this->aiServerUrl, '/') . '/verify', [
                    'known_embedding' => json_encode($user->face_descriptor),
                    'check_liveness' => 'true',
                ]);

            $processingTime = round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                // Reset failure count on success
                Cache::forget($failureKey);
                
                $result = $response->json();
                
                // Log slow requests
                if ($processingTime > 4000) {
                    Log::warning("Slow Python face verification: {$processingTime}ms for user {$user->id}");
                }

                return array_merge($result, [
                    'success' => true,
                    'mode' => 'python',
                    'processing_ms' => $processingTime,
                    'server_ms' => $result['processing_ms'] ?? 0,
                    'network_ms' => $processingTime - ($result['processing_ms'] ?? 0),
                    'health_status' => $health
                ]);
            } else {
                // Record failure
                $this->recordFailure($failureKey);
                
                return $this->createErrorResponse('AI Server processing error', $startTime, [
                    'http_status' => $response->status(),
                    'fallback_recommended' => true,
                    'error_type' => 'server_error'
                ]);
            }

        } catch (\Exception $e) {
            // Record failure
            $this->recordFailure($failureKey);
            
            Log::error("Python face verification error for user {$user->id}: " . $e->getMessage());

            return $this->createErrorResponse('AI Server connection failed', $startTime, [
                'error_type' => get_class($e),
                'fallback_recommended' => true,
                'exception' => true
            ]);
        }
    }

    /**
     * JavaScript verification support (returns descriptor for client processing)
     */
    private function javascriptVerificationSupport(User $user, float $startTime): array
    {
        $descriptor128d = $user->face_descriptor_js;

        if (!$descriptor128d) {
            // Try to generate from 512D
            if ($user->face_descriptor && is_array($user->face_descriptor)) {
                $descriptor128d = array_slice($user->face_descriptor, 0, 128);
                
                // Save for future use
                $user->update(['face_descriptor_js' => $descriptor128d]);
                
                Log::info("Generated 128D descriptor for user {$user->id} from 512D");
            } else {
                return $this->createErrorResponse('No face descriptors available', $startTime);
            }
        }

        return [
            'success' => true,
            'mode' => 'js',
            'descriptor_128d' => $descriptor128d,
            'processing_ms' => round((microtime(true) - $startTime) * 1000),
            'thresholds' => [
                'distance' => 0.5,
                'confidence' => 0.7
            ],
            'instructions' => 'Use faceapi.euclideanDistance for comparison'
        ];
    }

    /**
     * Hybrid verification strategy
     */
    private function hybridVerification(User $user, string $imageData, float $startTime, string $priority): array
    {
        if ($priority === 'speed') {
            // Try JS first, fallback to Python if needed
            $jsResponse = $this->javascriptVerificationSupport($user, $startTime);
            
            if ($jsResponse['success']) {
                $jsResponse['fallback_python_available'] = !empty($this->aiServerUrl);
                return $jsResponse;
            }
        }

        // Use Python (accuracy priority or JS failed)
        return $this->pythonVerification($user, $imageData, $startTime);
    }

    /**
     * Check AI Server health with caching
     */
    private function checkServerHealth(): array
    {
        $cacheKey = 'ai_server_health_status';
        
        // Return cached status if available
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        if (empty($this->aiServerUrl)) {
            $status = [
                'available' => false,
                'error' => 'AI Server URL not configured',
                'checked_at' => time()
            ];
        } else {
            try {
                $response = Http::timeout(3)->get(rtrim($this->aiServerUrl, '/') . '/health');
                
                if ($response->successful()) {
                    $healthData = $response->json();
                    $status = [
                        'available' => true,
                        'status' => $healthData['status'] ?? 'unknown',
                        'models' => $healthData['models'] ?? [],
                        'pipeline' => $healthData['pipeline'] ?? 'unknown',
                        'response_time_ms' => round($response->transferStats?->getTotalTime() * 1000),
                        'checked_at' => time()
                    ];
                } else {
                    $status = [
                        'available' => false,
                        'error' => 'HTTP ' . $response->status(),
                        'checked_at' => time()
                    ];
                }
            } catch (\Exception $e) {
                $status = [
                    'available' => false,
                    'error' => $e->getMessage(),
                    'exception_type' => get_class($e),
                    'checked_at' => time()
                ];
            }
        }

        // Cache for 30 seconds
        Cache::put($cacheKey, $status, 30);
        
        return $status;
    }

    /**
     * Record failure for tracking
     */
    private function recordFailure(string $failureKey): void
    {
        $failures = Cache::get($failureKey, 0) + 1;
        Cache::put($failureKey, $failures, 300); // 5 minutes
        
        Log::warning("AI Server failure recorded. Count: {$failures}");
    }

    /**
     * Decode base64 image data
     */
    private function decodeBase64Image(string $imageData): ?string
    {
        // Remove data URI prefix if present
        $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
        
        // Decode
        $decoded = base64_decode(str_replace(' ', '+', $imageData));
        
        // Validate size (max 2MB)
        if (!$decoded || strlen($decoded) > 2000000) {
            return null;
        }
        
        return $decoded;
    }

    /**
     * Create standardized error response
     */
    private function createErrorResponse(string $message, float $startTime, array $additional = []): array
    {
        return array_merge([
            'success' => false,
            'message' => $message,
            'processing_ms' => round((microtime(true) - $startTime) * 1000),
            'timestamp' => time()
        ], $additional);
    }

    /**
     * Get performance metrics
     */
    public function getMetrics(): array
    {
        $health = $this->checkServerHealth();
        
        return [
            'ai_server' => $health,
            'system' => [
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'timestamp' => time()
            ],
            'rate_limits' => [
                'python' => 10,
                'js' => 30,
                'hybrid' => 20
            ],
            'fallback_threshold' => $this->fallbackThreshold,
            'timeout_seconds' => $this->timeoutSeconds
        ];
    }
}