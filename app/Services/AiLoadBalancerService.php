<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * AI Load Balancer & Client Router Service
 * จัดการกระจายคำขอ (Round-Robin), Circuit Breaker แยกตาม Node, Health Check, และ Automatic Failover
 */
class AiLoadBalancerService
{
    /**
     * ดึงรายการ Node ทั้งหมดที่ตั้งค่าไว้ (ไดนามิกตาม config ปัจจุบัน)
     *
     * @return array<int, string>
     */
    public function getNodes(): array
    {
        $configuredUrls = config('services.ai_server.urls');
        if (is_array($configuredUrls) && !empty($configuredUrls)) {
            return array_values(array_filter(array_map('trim', $configuredUrls)));
        }

        $singleUrl = (string) config('services.ai_server.url', '');
        return !empty($singleUrl) ? [$singleUrl] : ['http://127.0.0.1:8001'];
    }

    public function getApiKey(): string
    {
        return (string) config('services.ai_server.key', '');
    }

    public function getTimeout(): int
    {
        return (int) config('services.ai_server.timeout', 6);
    }

    public function getRetries(): int
    {
        return (int) config('services.ai_server.retry', 2);
    }

    public function getCircuitBreakerThreshold(): int
    {
        return (int) config('services.ai_server.circuit_breaker_threshold', 3);
    }

    public function getCircuitBreakerCooldown(): int
    {
        return (int) config('services.ai_server.circuit_breaker_cooldown', 30);
    }

    /**
     * ดึงรายการ Node ที่พร้อมทำงาน (Circuit Breaker ยังไม่ Trip)
     *
     * @return array<int, string>
     */
    public function getHealthyNodes(): array
    {
        $nodes = $this->getNodes();
        if (empty($nodes)) {
            return [];
        }

        $healthy = array_values(array_filter($nodes, fn(string $node) => !$this->isCircuitOpen($node)));

        // หากทุก Node ติด Circuit Breaker ให้ fallback คืน Node ทั้งหมดเพื่อลองใหม่อัตโนมัติ (Half-Open)
        return !empty($healthy) ? $healthy : $nodes;
    }

    /**
     * เลือก Node ถัดไปโดยใช้ Round-Robin
     */
    public function getNextNode(): ?string
    {
        $healthyNodes = $this->getHealthyNodes();
        if (empty($healthyNodes)) {
            return null;
        }

        if (count($healthyNodes) === 1) {
            return $healthyNodes[0];
        }

        $index = (int) Cache::increment('ai_lb_rr_index');
        $selectedIndex = $index % count($healthyNodes);

        return $healthyNodes[$selectedIndex];
    }

    /**
     * ดำเนินการ HTTP request ไปยัง AI Server พร้อมระบบกระจายโหลดและ Automatic Failover
     *
     * @template T
     * @param  callable(string $nodeUrl, string $apiKey, int $timeout): T $callback
     * @param  int|null $maxAttempts
     * @return array{result: T, node_used: string, attempts: int, failovers: int}
     * @throws RuntimeException
     */
    public function executeWithFailover(callable $callback, ?int $maxAttempts = null): array
    {
        $allNodes = $this->getNodes();
        $healthyNodes = $this->getHealthyNodes();

        if (empty($allNodes)) {
            throw new RuntimeException('No AI Server nodes configured or available');
        }

        $attempts = 0;
        $failovers = 0;
        $limit = $maxAttempts ?? min(count($allNodes), max(2, $this->getRetries()));
        $triedNodes = [];
        $lastException = null;

        while ($attempts < $limit) {
            $attempts++;

            // เลือก node ที่ยังไม่เคยลองใน request นี้
            $available = array_values(array_diff($healthyNodes, $triedNodes));
            if (empty($available)) {
                $available = array_values(array_diff($allNodes, $triedNodes));
            }
            if (empty($available)) {
                $available = $allNodes;
            }

            $rrIdx = (int) Cache::increment('ai_lb_rr_index');
            $nodeUrl = $available[$rrIdx % count($available)];
            $triedNodes[] = $nodeUrl;

            try {
                $result = $callback($nodeUrl, $this->getApiKey(), $this->getTimeout());

                // หากสำเร็จ รีเซ็ต Circuit Breaker และตัวนับข้อผิดพลาดของ Node นี้
                $this->recordSuccess($nodeUrl);

                return [
                    'result'    => $result,
                    'node_used' => $nodeUrl,
                    'attempts'  => $attempts,
                    'failovers' => $failovers,
                ];
            } catch (Throwable $e) {
                $failovers++;
                $lastException = $e;
                $this->recordFailure($nodeUrl, $e->getMessage());

                Log::warning("AI Load Balancer: Node {$nodeUrl} failed (attempt {$attempts}/{$limit}): {$e->getMessage()}. Failing over to next available node...");
            }
        }

        Log::error("AI Load Balancer: All {$attempts} attempts failed across configured AI nodes.");
        throw new RuntimeException(
            "AI Server cluster unavailable after {$attempts} attempts: " . ($lastException?->getMessage() ?? 'Unknown error'),
            0,
            $lastException
        );
    }

    /**
     * ตรวจสอบว่า Circuit Breaker ของ Node นี้เปิดอยู่หรือไม่ (Open State)
     */
    public function isCircuitOpen(string $nodeUrl): bool
    {
        $key = 'ai_circuit_open_' . md5($nodeUrl);
        return (bool) Cache::has($key);
    }

    /**
     * บันทึกความสำเร็จและรีเซ็ตสถานะความล้มเหลว
     */
    public function recordSuccess(string $nodeUrl): void
    {
        $hash = md5($nodeUrl);
        Cache::forget("ai_node_failures_{$hash}");
        Cache::forget("ai_circuit_open_{$hash}");
    }

    /**
     * บันทึกความล้มเหลวและสั่ง Trip Circuit Breaker เมื่อล้มเหลวติดต่อกันเกินเกณฑ์
     */
    public function recordFailure(string $nodeUrl, string $reason = ''): void
    {
        $hash = md5($nodeUrl);
        $failKey = "ai_node_failures_{$hash}";
        $circuitKey = "ai_circuit_open_{$hash}";

        $failures = (int) Cache::increment($failKey);
        Cache::put($failKey, $failures, 120);

        if ($failures >= $this->getCircuitBreakerThreshold()) {
            Cache::put($circuitKey, time(), $this->getCircuitBreakerCooldown());
            Log::alert("🚨 AI Circuit Breaker TRIPPED for Node: {$nodeUrl} after {$failures} consecutive failures. Cooldown: {$this->getCircuitBreakerCooldown()}s. Reason: {$reason}");
        }
    }

    /**
     * ตรวจสอบสถานะการทำงานของ Node ที่ระบุ
     *
     * @return array<string, mixed>
     */
    public function checkNodeHealth(string $nodeUrl): array
    {
        $start = microtime(true);
        $isOpen = $this->isCircuitOpen($nodeUrl);

        try {
            $httpReq = Http::timeout(3);
            $key = $this->getApiKey();
            if (!empty($key)) {
                $httpReq = $httpReq->withHeaders(['X-API-Key' => $key]);
            }

            $response = $httpReq->get(rtrim($nodeUrl, '/') . '/health');
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                if ($isOpen) {
                    $this->recordSuccess($nodeUrl);
                }

                return [
                    'url'          => $nodeUrl,
                    'available'    => true,
                    'status'       => $data['status'] ?? 'healthy',
                    'models'       => $data['models'] ?? [],
                    'latency_ms'   => $latencyMs,
                    'circuit_open' => false,
                    'checked_at'   => time(),
                ];
            }

            return [
                'url'          => $nodeUrl,
                'available'    => false,
                'status'       => 'unhealthy',
                'http_status'  => $response->status(),
                'latency_ms'   => $latencyMs,
                'circuit_open' => $isOpen,
                'checked_at'   => time(),
            ];
        } catch (Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            return [
                'url'          => $nodeUrl,
                'available'    => false,
                'status'       => 'down',
                'error'        => $e->getMessage(),
                'latency_ms'   => $latencyMs,
                'circuit_open' => $isOpen,
                'checked_at'   => time(),
            ];
        }
    }

    /**
     * ตรวจสอบสถานะของทุก Node พร้อมแคช 10 วินาที
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkAllNodesHealth(): array
    {
        return Cache::remember('ai_lb_all_nodes_health', 10, function (): array {
            $results = [];
            foreach ($this->getNodes() as $node) {
                $results[] = $this->checkNodeHealth($node);
            }
            return $results;
        });
    }
}
