<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ClusterHealthService;
use Illuminate\Console\Command;

class ClusterStatusCommand extends Command
{
    protected $signature   = 'cluster:status {--json : แสดงผลลัพธ์เป็น JSON สำหรับ Automation Probes}';
    protected $description = 'แสดงสถานะการทำงานองค์รวมของ Uni-Activity Cluster (App, Octane, DB, Redis, AI Cluster, Queues)';

    public function handle(ClusterHealthService $clusterHealthService): int
    {
        $status = $clusterHealthService->getFullClusterStatus();

        if ($this->option('json')) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>==================================================================</>');
        $this->line('<fg=cyan;options=bold>   UNI-ACTIVITY DISTRIBUTED CLUSTER CONTROL PLANE               </>');
        $this->line('<fg=cyan;options=bold>==================================================================</>');
        $this->line(" <fg=gray>Node ID:</> <fg=yellow>{$status['app']['node_id']}</> | <fg=gray>Env:</> <fg=green>{$status['app']['env']}</> | <fg=gray>Octane Server:</> <fg=magenta>{$status['app']['octane_server']}</>");
        $this->newLine();

        // 1. Core Services Table
        $dbStatusColor = $status['database']['status'] === 'HEALTHY' ? 'green' : 'red';
        $redisStatusColor = $status['redis']['status'] === 'HEALTHY' ? 'green' : 'red';

        $this->info('1. Core Infrastructure & Databases');
        $this->table(
            ['Component', 'Connection / Target', 'Status', 'Latency', 'Extra Details'],
            [
                [
                    'PostgreSQL 16',
                    $status['database']['connection'] . '://' . ($status['database']['database'] ?? 'N/A'),
                    "<fg={$dbStatusColor}>{$status['database']['status']}</>",
                    isset($status['database']['latency_ms']) ? "{$status['database']['latency_ms']} ms" : 'N/A',
                    'Primary ACID Core Database',
                ],
                [
                    'Redis / Dragonfly',
                    "{$status['redis']['host']}:{$status['redis']['port']}",
                    "<fg={$redisStatusColor}>{$status['redis']['status']}</>",
                    isset($status['redis']['latency_ms']) ? "{$status['redis']['latency_ms']} ms" : 'N/A',
                    $status['redis']['auth_enabled'] ? 'Auth Protected (requirepass)' : 'No Auth',
                ],
                [
                    'Laravel Reverb',
                    "{$status['broadcasting']['scheme']}://{$status['broadcasting']['host']}:{$status['broadcasting']['port']}",
                    '<fg=green>READY</>',
                    '—',
                    "App ID: {$status['broadcasting']['app_id']}",
                ],
            ]
        );

        $this->newLine();

        // 2. Distributed AI Cluster
        $this->info("2. Distributed AI Face Recognition Cluster (State: <fg=green>{$status['ai_cluster']['cluster_state']}</>)");
        $aiRows = [];
        foreach ($status['ai_cluster']['nodes'] as $node) {
            $color = $node['status'] === 'HEALTHY' ? 'green' : ($node['status'] === 'DEGRADED' ? 'yellow' : 'red');
            $cbColor = $node['circuit_breaker'] === 'CLOSED' ? 'green' : 'red';
            $aiRows[] = [
                $node['id'],
                $node['url'],
                "<fg={$color}>{$node['status']}</>",
                "<fg={$cbColor}>{$node['circuit_breaker']}</>",
                isset($node['latency_ms']) ? "{$node['latency_ms']} ms" : 'N/A',
                isset($node['models']) ? implode(', ', $node['models']) : ($node['error'] ?? 'N/A'),
            ];
        }
        $this->table(['Node ID', 'Endpoint', 'Status', 'Circuit Breaker', 'Latency', 'Models / Info'], $aiRows);

        $this->newLine();

        // 3. Priority Queue Workers
        $this->info("3. Redis Priority Queues (Total Pending: <fg=yellow>{$status['queues']['total_pending']}</>, Failed: <fg=red>{$status['queues']['failed_jobs']}</>)");
        $queueRows = [];
        foreach ($status['queues']['channels'] as $channel => $depth) {
            $queueRows[] = [
                $channel,
                $depth > 0 ? "<fg=yellow>{$depth}</>" : '<fg=green>0</>',
                $channel === 'ai' ? 'High (InsightFace Extraction)' : ($channel === 'notifications' ? 'Medium (LINE Reminders)' : 'Standard'),
            ];
        }
        $this->table(['Queue Channel', 'Pending Jobs', 'Priority Level'], $queueRows);

        $this->newLine();

        // 4. Security Posture
        $gradeColor = $status['security']['grade'] === 'A+' ? 'green' : 'yellow';
        $this->line("<fg=gray>Security Posture Score:</> <fg={$gradeColor};options=bold>{$status['security']['score']}/100 (Grade {$status['security']['grade']})</> | <fg=gray>Debug Mode:</> " . ($status['security']['app_debug_safe'] ? '<fg=green>SAFE (false)</>' : '<fg=red>UNSAFE (true)</>'));
        $this->newLine();

        return self::SUCCESS;
    }
}
