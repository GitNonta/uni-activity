<?php

use App\Console\Commands\SendActivityReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ส่ง Auto-Reminder กิจกรรม (ล่วงหน้า 1 วัน และ 2 ชั่วโมง) ทุก 15 นาที
Schedule::command(SendActivityReminders::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/reminders.log'));

// ── Automated Database & Biometric Backup Routine ──────────────────────────────
// 1. สำรองข้อมูลฐานข้อมูลประจำวัน (Database Daily Dump) ทุกวันเวลา 01:00 น.
Schedule::command('backup:run --type=db')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/backups.log'));

// 2. สำรองข้อมูลเต็มรูปแบบ (Full Backup: Database + Files + Biometrics) ทุกวันอาทิตย์เวลา 02:00 น.
Schedule::command('backup:run --type=full')
    ->weeklyOn(0, '02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/backups.log'));

// 3. ทำความสะอาดและลบไฟล์สำรองข้อมูลเก่าตาม Retention Policy ทุกวันเวลา 03:00 น.
Schedule::command('backup:clean')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/backups.log'));


