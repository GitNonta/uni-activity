<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ClusterHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClusterMonitoringController extends Controller
{
    public function __construct(
        protected readonly ClusterHealthService $clusterHealthService
    ) {}

    /**
     * นำทางไปยังแผงควบคุมศูนย์กลางบนพอร์ต 9999 (Monitor UI Dashboard)
     */
    public function index(Request $request)
    {
        $host = $request->getHost();
        $targetUrl = (filter_var($host, FILTER_VALIDATE_IP) || $host === 'localhost')
            ? 'http://' . $host . ':9999/#cluster'
            : 'http://192.168.1.222:9999/#cluster';

        return redirect()->away($targetUrl);
    }

    /**
     * Endpoint คืนค่า Real-time Metrics สำหรับ Live AJAX Polling
     */
    public function metrics(Request $request): JsonResponse
    {
        $status = $this->clusterHealthService->getFullClusterStatus();

        return response()->json([
            'status' => 'ok',
            'data'   => $status,
        ]);
    }
}
