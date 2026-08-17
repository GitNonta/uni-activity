<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ClusterHealthService;
use Illuminate\Console\Command;

class ClusterHealthCommand extends Command
{
    protected $signature   = 'cluster:health {--json : Return machine-readable JSON}';
    protected $description = 'Health probe command for automated monitoring agents and load balancers';

    public function handle(ClusterHealthService $clusterHealthService): int
    {
        $status = $clusterHealthService->getFullClusterStatus();

        $isDbHealthy = ($status['database']['status'] ?? '') === 'HEALTHY';
        $isRedisHealthy = in_array($status['redis']['status'] ?? '', ['HEALTHY', 'DEGRADED'], true);
        $isAiHealthy = ($status['ai_cluster']['cluster_state'] ?? '') !== 'CRITICAL';

        $isSystemHealthy = $isDbHealthy && $isRedisHealthy && $isAiHealthy;

        if ($this->option('json')) {
            $this->line(json_encode([
                'healthy'    => $isSystemHealthy,
                'timestamp'  => time(),
                'components' => [
                    'database' => $status['database']['status'] ?? 'UNKNOWN',
                    'redis'    => $status['redis']['status'] ?? 'UNKNOWN',
                    'ai'       => $status['ai_cluster']['cluster_state'] ?? 'UNKNOWN',
                ],
            ]));
            return $isSystemHealthy ? self::SUCCESS : self::FAILURE;
        }

        if ($isSystemHealthy) {
            $this->info('✓ All core cluster systems are healthy.');
            return self::SUCCESS;
        }

        $this->error('✗ One or more critical cluster systems are unhealthy:');
        if (!$isDbHealthy) {
            $this->error("  - Database: {$status['database']['status']}");
        }
        if (!$isRedisHealthy) {
            $this->error("  - Redis: {$status['redis']['status']}");
        }
        if (!$isAiHealthy) {
            $this->error("  - AI Cluster: {$status['ai_cluster']['cluster_state']}");
        }

        return self::FAILURE;
    }
}
