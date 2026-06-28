<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Registration;
use App\Models\User;
use App\Services\LineService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendActivityReminders extends Command
{
    protected $signature   = 'reminders:send {--date= : วันที่ต้องการส่ง reminder (Y-m-d) ค่าเริ่มต้น: พรุ่งนี้}';
    protected $description = 'ส่ง LINE reminder ให้นักศึกษาที่ลงทะเบียนกิจกรรมและผูก LINE แล้ว';

    public function __construct(
        private readonly LineService $lineService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $targetDate = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::tomorrow();

        $this->info("ส่ง reminder สำหรับกิจกรรมวันที่: {$targetDate->toDateString()}");

        // ดึงกิจกรรมที่มีในวันนั้น
        $activities = Activity::whereDate('activity_date', $targetDate)
            ->where('status', '!=', 'cancelled')
            ->get();

        if ($activities->isEmpty()) {
            $this->info('ไม่มีกิจกรรมในวันที่ระบุ');
            return self::SUCCESS;
        }

        $sentCount = 0;

        foreach ($activities as $activity) {
            // ดึงนักศึกษาที่ลงทะเบียนและผูก LINE แล้ว
            $registrations = Registration::where('activity_id', $activity->id)
                ->whereIn('status', ['registered', 'waitlisted'])
                ->with(['user' => function ($q) {
                    $q->whereNotNull('line_user_id')
                      ->where('line_notify_enabled', true);
                }])
                ->get()
                ->filter(fn($r) => $r->user && $r->user->line_user_id);

            foreach ($registrations as $registration) {
                $user    = $registration->user;
                $message = $this->lineService->buildReminderMessage($activity, $user->full_name ?? 'นักศึกษา');

                $success = $this->lineService->pushMessage($user->line_user_id, [$message]);

                if ($success) {
                    $sentCount++;
                }
            }

            $this->line("  ✓ {$activity->title}: ส่งให้ {$registrations->count()} คน");
        }

        Log::info("Activity reminders sent", [
            'date'       => $targetDate->toDateString(),
            'activities' => $activities->count(),
            'sent'       => $sentCount,
        ]);

        $this->info("✅ ส่ง reminder สำเร็จ {$sentCount} ข้อความ");

        return self::SUCCESS;
    }
}
