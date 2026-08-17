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
     * Display a listing of failed queue jobs.
     */
    public function index(Request $request): View
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

        $failedJobs = $query->paginate(15)->withQueryString();

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

        return view('admin.system.failed-jobs', compact('failedJobs', 'queues', 'totalFailed'));
    }

    /**
     * Fetch single failed job details and full exception stack trace for modal.
     */
    public function show(string $uuid): JsonResponse
    {
        $job = DB::table('failed_jobs')->where('uuid', $uuid)->first();

        if (!$job) {
            return response()->json(['error' => 'Failed job not found'], 404);
        }

        $payload = json_decode((string) $job->payload, true) ?? [];

        return response()->json([
            'id'           => $job->id,
            'uuid'         => $job->uuid,
            'connection'   => $job->connection,
            'queue'        => $job->queue,
            'display_name' => $payload['displayName'] ?? ($payload['data']['commandName'] ?? 'Unknown Job'),
            'attempts'     => $payload['attempts'] ?? 1,
            'failed_at'    => (string) $job->failed_at,
            'exception'    => $job->exception,
            'payload'      => $payload,
        ]);
    }

    /**
     * Retry a specific failed job.
     */
    public function retry(string $id): RedirectResponse
    {
        $job = DB::table('failed_jobs')->where('id', $id)->orWhere('uuid', $id)->first();

        if (!$job) {
            return back()->with('error', 'ไม่พบงานที่ล้มเหลวตามรหัสที่ระบุ');
        }

        $exitCode = Artisan::call('queue:retry', ['id' => [(string) $job->id]]);

        log_action('retry_failed_job', 'failed_jobs', (int) $job->id, "Retried failed queue job UUID: {$job->uuid}");

        if ($exitCode === 0) {
            return back()->with('success', "ส่งงาน '{$job->queue}' (ID: {$job->id}) กลับเข้าคิวเพื่อลองใหม่อีกครั้งเรียบร้อยแล้ว");
        }

        return back()->with('error', 'ไม่สามารถส่งงานกลับเข้าคิวได้ กรุณาตรวจสอบสถานะคิว');
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAll(): RedirectResponse
    {
        $count = DB::table('failed_jobs')->count();

        if ($count === 0) {
            return back()->with('info', 'ไม่มีรายการงานที่ล้มเหลวค้างอยู่ในระบบ');
        }

        Artisan::call('queue:retry', ['id' => ['all']]);

        log_action('retry_all_failed_jobs', 'failed_jobs', null, "Retried all {$count} failed queue jobs");

        return back()->with('success', "ส่งงานที่ล้มเหลวทั้งหมด ({$count} รายการ) กลับเข้าคิวเพื่อลองใหม่เรียบร้อยแล้ว");
    }

    /**
     * Delete/forget a specific failed job.
     */
    public function destroy(string $id): RedirectResponse
    {
        $job = DB::table('failed_jobs')->where('id', $id)->orWhere('uuid', $id)->first();

        if (!$job) {
            return back()->with('error', 'ไม่พบงานที่ล้มเหลวตามรหัสที่ระบุ');
        }

        Artisan::call('queue:forget', ['id' => (string) $job->id]);

        log_action('delete_failed_job', 'failed_jobs', (int) $job->id, "Deleted failed job UUID: {$job->uuid}");

        return back()->with('success', "ลบรายการงานที่ล้มเหลว ID: {$job->id} ออกจากระบบเรียบร้อยแล้ว");
    }

    /**
     * Delete/flush all failed jobs.
     */
    public function flush(): RedirectResponse
    {
        $count = DB::table('failed_jobs')->count();

        if ($count === 0) {
            return back()->with('info', 'ไม่มีรายการงานที่ล้มเหลวค้างอยู่ในระบบ');
        }

        Artisan::call('queue:flush');

        log_action('flush_failed_jobs', 'failed_jobs', null, "Flushed all {$count} failed queue jobs");

        return back()->with('success', "ล้างรายการงานที่ล้มเหลวทั้งหมด ({$count} รายการ) เรียบร้อยแล้ว");
    }
}

function StrLimit(string $value, int $limit = 100, string $end = '...'): string
{
    return mb_strwidth($value, 'UTF-8') <= $limit ? $value : rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
}
