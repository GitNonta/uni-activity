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
     * หน้าจอแดชบอร์ดศูนย์กลางแสดงสถานะของคลัสเตอร์ทั้งหมด
     */
    public function index(Request $request): View
    {
        $status = $this->clusterHealthService->getFullClusterStatus();

        return view('admin.system.cluster', [
            'status' => $status,
        ]);
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
