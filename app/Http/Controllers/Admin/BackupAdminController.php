<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\BackupRepository;
use App\Services\BackupService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use function App\Helpers\log_action;

class BackupAdminController extends Controller
{
    public function __construct(
        private readonly BackupRepository $backupRepo,
        private readonly BackupService $backupService
    ) {}

    public function index(): View
    {
        $backups = $this->backupRepo->getAllBackups();
        $totalSize = $this->backupRepo->getTotalSize();
        $formattedTotalSize = $this->backupRepo->formatBytes($totalSize);
        $latestBackup = $this->backupRepo->getLatestBackup();

        $scheduleInfo = [
            'daily_db'     => 'ทุกวัน เวลา 01:00 น.',
            'weekly_full'  => 'ทุกวันอาทิตย์ เวลา 02:00 น.',
            'daily_clean'  => 'ทุกวัน เวลา 03:00 น.',
            'retention_days' => config('backup.retention.keep_days', 14),
            'keep_minimum' => config('backup.retention.keep_minimum_count', 5),
        ];

        return view('admin.backups.index', compact(
            'backups',
            'totalSize',
            'formattedTotalSize',
            'latestBackup',
            'scheduleInfo'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'type' => 'required|string|in:full,db,files,biometrics',
        ]);

        $type = (string) $request->input('type');

        try {
            $result = $this->backupService->runBackup($type, true);

            log_action(
                action: 'create',
                modelType: null,
                modelId: null,
                description: "สร้างไฟล์สำรองข้อมูลระบบ [{$type}]: {$result['filename']} ({$result['formatted_size']})"
            );

            return redirect()
                ->route('admin.backups.index')
                ->with('success', "สร้างการสำรองข้อมูล [{$type}] สำเร็จ: {$result['filename']} ({$result['formatted_size']})");
        } catch (Exception $e) {
            return redirect()
                ->route('admin.backups.index')
                ->with('error', "การสำรองข้อมูลล้มเหลว: " . $e->getMessage());
        }
    }

    public function download(string $filename): BinaryFileResponse|RedirectResponse
    {
        $backup = $this->backupRepo->find($filename);

        if (!$backup) {
            return redirect()
                ->route('admin.backups.index')
                ->with('error', 'ไม่พบไฟล์สำรองข้อมูลที่ระบุ');
        }

        log_action(
            action: 'download',
            modelType: null,
            modelId: null,
            description: "ดาวน์โหลดไฟล์สำรองข้อมูลระบบ: {$backup['filename']} ({$backup['formatted_size']})"
        );

        return response()->download(
            file: (string) $backup['path'],
            name: (string) $backup['filename'],
            headers: [
                'Content-Type' => 'application/zip',
            ]
        );
    }

    public function destroy(string $filename): RedirectResponse
    {
        $backup = $this->backupRepo->find($filename);

        if (!$backup) {
            return redirect()
                ->route('admin.backups.index')
                ->with('error', 'ไม่พบไฟล์สำรองข้อมูลที่ต้องการลบ');
        }

        $this->backupRepo->delete($filename);

        log_action(
            action: 'delete',
            modelType: null,
            modelId: null,
            description: "ลบไฟล์สำรองข้อมูลระบบ: {$filename}"
        );

        return redirect()
            ->route('admin.backups.index')
            ->with('success', "ลบไฟล์สำรองข้อมูล {$filename} เรียบร้อยแล้ว");
    }

    public function clean(Request $request): RedirectResponse
    {
        $deleted = $this->backupService->cleanOldBackups();

        log_action(
            action: 'delete',
            modelType: null,
            modelId: null,
            description: "ดำเนินการล้างไฟล์สำรองข้อมูลเก่าตาม Retention Policy (ลบ " . count($deleted) . " ไฟล์)"
        );

        $count = count($deleted);
        $msg = $count > 0 
            ? "ทำความสะอาดไฟล์เก่าสำเร็จ ลบไปทั้งหมด {$count} ไฟล์"
            : "ไม่มีไฟล์สำรองข้อมูลเก่าที่เกินกำหนดระยะเวลาจัดเก็บ";

        return redirect()
            ->route('admin.backups.index')
            ->with('success', $msg);
    }
}
