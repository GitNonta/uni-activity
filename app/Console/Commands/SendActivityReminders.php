<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ActivityNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendActivityReminders extends Command
{
    protected $signature   = 'reminders:send {--window= : บังคับประเภท window (24h หรือ 2h)}';
    protected $description = 'ส่ง Auto-Reminder ล่วงหน้า 24 ชม. และ 2 ชม. ก่อนกิจกรรมเริ่มเพื่อลด No-show rate (In-App + LINE)';

    public function handle(ActivityNotificationService $service): int
    {
        $window = $this->option('window');
        if ($window && !in_array($window, ['24h', '2h'], true)) {
            $this->error("Invalid window option. Allowed values: '24h', '2h'");
            return self::FAILURE;
        }

        $this->info("กำลังประมวลผล Auto-Reminder กิจกรรม (Window: " . ($window ?? 'Auto 24h & 2h') . ")...");

        $stats = $service->sendScheduledReminders($window);

        $this->info("✅ ประมวลผลเสร็จสิ้น:");
        $this->line("  • กิจกรรมที่ตรวจสอบ: {$stats['activities_processed']} กิจกรรม");
        $this->line("  • ส่งแจ้งเตือนล่วงหน้า 24 ชม. (1 วัน): {$stats['reminders_24h_sent']} ครั้ง");
        $this->line("  • ส่งแจ้งเตือนล่วงหน้า 2 ชม.: {$stats['reminders_2h_sent']} ครั้ง");

        Log::info('Activity Auto-Reminders command finished', $stats);

        return self::SUCCESS;
    }
}
