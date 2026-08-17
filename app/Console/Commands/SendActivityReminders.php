<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendActivityReminderJob;
use App\Models\Activity;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendActivityReminders extends Command
{
    protected $signature   = 'reminders:send {--date= : วันที่ต้องการส่ง reminder (Y-m-d) ค่าเริ่มต้น: พรุ่งนี้}';
    protected $description = 'ส่ง LINE reminder ให้นักศึกษาที่ลงทะเบียนกิจกรรมและผูก LINE แล้วผ่าน Background Queue';

    public function handle(): int
    {
        $targetDate = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))
            : Carbon::tomorrow();

        $this->info("ส่ง reminder สำหรับกิจกรรมวันที่: {$targetDate->toDateString()} ผ่าน Background Queue");

        // ดึงกิจกรรมที่มีในวันนั้น
        $activities = Activity::whereDate('activity_date', $targetDate)
            ->where('status', '!=', 'cancelled')
            ->get();

        if ($activities->isEmpty()) {
            $this->info('ไม่มีกิจกรรมในวันที่ระบุ');
            return self::SUCCESS;
        }

        $dispatchedCount = 0;

        foreach ($activities as $activity) {
            // ดึงนักศึกษาที่ลงทะเบียนและผูก LINE แล้ว
            $registrations = Registration::where('activity_id', $activity->id)
                ->whereIn('status', ['registered', 'approved', 'waitlisted'])
                ->with(['user' => function ($q) {
                    $q->whereNotNull('line_user_id')
                      ->where('line_notify_enabled', true);
                }])
                ->get()
                ->filter(fn($r) => $r->user && $r->user->line_user_id);

            foreach ($registrations as $registration) {
                if ($registration->user_id) {
                    SendActivityReminderJob::dispatch($registration->user_id, $activity->id)
                        ->onQueue('notifications');
                    $dispatchedCount++;
                }
            }

            $this->line("  ✓ {$activity->title}: เข้าคิวส่ง reminder ให้ {$registrations->count()} คน");
        }

        Log::info('Activity reminders dispatched to queue', [
            'date'       => $targetDate->toDateString(),
            'activities' => $activities->count(),
            'dispatched' => $dispatchedCount,
        ]);

        $this->info("✅ นำส่ง reminder เข้า Queue สำเร็จ {$dispatchedCount} รายการ");

        return self::SUCCESS;
    }
}
