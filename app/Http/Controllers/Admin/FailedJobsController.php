<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use function App\Helpers\log_action;

class FailedJobsController extends Controller
{
    /**
     * Display a listing of failed queue jobs or redirect to Monitor UI.
     */
    public function index(Request $request)
    {
        $query = DB::table('failed_jobs')->orderByDesc('failed_at');

        if ($request->filled('queue')) {
            $query->where('queue', (string) $request->input('queue'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search): void {
                $q->where('payload', 'like', $search)
                  ->orWhere('exception', 'like', $search)
                  ->orWhere('uuid', 'like', $search);
            });
        }

        $failedJobs = $query->paginate(25)->withQueryString();

        // Decode job display names
        $failedJobs->getCollection()->transform(function ($job) {
            $payload = json_decode((string) $job->payload, true) ?? [];
            $displayName = $payload['displayName'] ?? ($payload['data']['commandName'] ?? 'Unknown Job');
            $job->display_name = class_basename($displayName);
            $job->full_class = $displayName;
            $job->exception_summary = StrLimit(explode("\n", (string) $job->exception)[0] ?? '', 120);
            return $job;
        });

        $queues = DB::table('failed_jobs')
            ->select('queue')
            ->distinct()
            ->pluck('queue')
            ->toArray();

        $totalFailed = DB::table('failed_jobs')->count();

        // If requested via API or JSON header, return structured JSON
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'ok',
                'data'   => [
                    'failed_jobs'  => $failedJobs->items(),
                    'total'        => $failedJobs->total(),
                    'current_page' => $failedJobs->currentPage(),
                    'last_page'    => $failedJobs->lastPage(),
                    'per_page'     => $failedJobs->perPage(),
                    'queues'       => $queues,
                    'total_failed' => $totalFailed,
                ],
            ]);
        }

        // Web access: redirect directly to Monitor Dashboard on port 9999
        $host = $request->getHost();
        $targetUrl = (filter_var($host, FILTER_VALIDATE_IP) || $host === 'localhost')
            ? 'http://' . $host . ':9999/#failed-jobs'
            : 'http://192.168.1.222:9999/#failed-jobs';

        return redirect()->away($targetUrl);
    }

    /**
     * Fetch single failed job details and full exception stack trace for modal.
     */
    public function show(string $uuid): JsonResponse
    {
        $job = DB::table('failed_jobs')->where('uuid', $uuid)->orWhere('id', $uuid)->first();

        if (!$job) {
            return response()->json(['status' => 'error', 'message' => 'Failed job not found'], 404);
        }

        $payload = json_decode((string) $job->payload, true) ?? [];

        return response()->json([
            'status' => 'ok',
            'data'   => [
                'id'           => $job->id,
                'uuid'         => $job->uuid,
                'connection'   => $job->connection,
                'queue'        => $job->queue,
                'display_name' => $payload['displayName'] ?? ($payload['data']['commandName'] ?? 'Unknown Job'),
                'attempts'     => $payload['attempts'] ?? 1,
                'failed_at'    => (string) $job->failed_at,
                'exception'    => $job->exception,
                'payload'      => $payload,
            ]
        ]);
    }

    /**
     * Retry a specific failed job.
     */
    public function retry(Request $request, string $id)
    {
        $job = DB::table('failed_jobs')->where('id', $id)->orWhere('uuid', $id)->first();

        if (!$job) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['status' => 'error', 'message' => 'ไม่พบงานที่ล้มเหลวตามรหัสที่ระบุ'], 404);
            }
            return back()->with('error', 'ไม่พบงานที่ล้มเหลวตามรหัสที่ระบุ');
        }

        try {
            Artisan::call('queue:retry', ['id' => [(string) ($job->uuid ?? $job->id)]]);
        } catch (\Throwable) {
            Artisan::call('queue:retry', ['id' => [(string) $job->id]]);
        }

        log_action('retry_failed_job', 'failed_jobs', (int) $job->id, "Retried failed queue job UUID: {$job->uuid}");

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'status'  => 'ok',
                'message' => "ส่งงาน '{$job->queue}' (ID: {$job->id}) กลับเข้าคิวเพื่อลองใหม่อีกครั้งเรียบร้อยแล้ว",
            ]);
        }

        return back()->with('success', "ส่งงาน '{$job->queue}' (ID: {$job->id}) กลับเข้าคิวเพื่อลองใหม่อีกครั้งเรียบร้อยแล้ว");
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAll(Request $request)
    {
        $count = DB::table('failed_jobs')->count();

        if ($count === 0) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['status' => 'ok', 'message' => 'ไม่มีรายการงานที่ล้มเหลวค้างอยู่ในระบบ']);
            }
            return back()->with('info', 'ไม่มีรายการงานที่ล้มเหลวค้างอยู่ในระบบ');
        }

        try {
            Artisan::call('queue:retry', ['id' => ['all']]);
        } catch (\Throwable) {}

        log_action('retry_all_failed_jobs', 'failed_jobs', null, "Retried all {$count} failed queue jobs");

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'status'  => 'ok',
                'message' => "ส่งงานที่ล้มเหลวทั้งหมด ({$count} รายการ) กลับเข้าคิวเพื่อลองใหม่เรียบร้อยแล้ว",
            ]);
        }

        return back()->with('success', "ส่งงานที่ล้มเหลวทั้งหมด ({$count} รายการ) กลับเข้าคิวเพื่อลองใหม่เรียบร้อยแล้ว");
    }

    /**
     * Delete/forget a specific failed job.
     */
    public function destroy(Request $request, string $id)
    {
        $job = DB::table('failed_jobs')->where('id', $id)->orWhere('uuid', $id)->first();

        if (!$job) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['status' => 'error', 'message' => 'ไม่พบงานที่ล้มเหลวตามรหัสที่ระบุ'], 404);
            }
            return back()->with('error', 'ไม่พบงานที่ล้มเหลวตามรหัสที่ระบุ');
        }

        DB::table('failed_jobs')->where('id', $job->id)->delete();

        try {
            Artisan::call('queue:forget', ['id' => (string) ($job->uuid ?? $job->id)]);
        } catch (\Throwable) {}

        log_action('delete_failed_job', 'failed_jobs', (int) $job->id, "Deleted failed job UUID: {$job->uuid}");

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'status'  => 'ok',
                'message' => "ลบรายการงานที่ล้มเหลว ID: {$job->id} ออกจากระบบเรียบร้อยแล้ว",
            ]);
        }

        return back()->with('success', "ลบรายการงานที่ล้มเหลว ID: {$job->id} ออกจากระบบเรียบร้อยแล้ว");
    }

    /**
     * Delete/flush all failed jobs.
     */
    public function flush(Request $request)
    {
        $count = DB::table('failed_jobs')->count();

        if ($count === 0) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['status' => 'ok', 'message' => 'ไม่มีรายการงานที่ล้มเหลวค้างอยู่ในระบบ']);
            }
            return back()->with('info', 'ไม่มีรายการงานที่ล้มเหลวค้างอยู่ในระบบ');
        }

        DB::table('failed_jobs')->delete();

        try {
            Artisan::call('queue:flush');
        } catch (\Throwable) {}

        log_action('flush_failed_jobs', 'failed_jobs', null, "Flushed all {$count} failed queue jobs");

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'status'  => 'ok',
                'message' => "ล้างรายการงานที่ล้มเหลวทั้งหมด ({$count} รายการ) เรียบร้อยแล้ว",
            ]);
        }

        return back()->with('success', "ล้างรายการงานที่ล้มเหลวทั้งหมด ({$count} รายการ) เรียบร้อยแล้ว");
    }
}

function StrLimit(string $value, int $limit = 100, string $end = '...'): string
{
    return mb_strwidth($value, 'UTF-8') <= $limit ? $value : rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
}
