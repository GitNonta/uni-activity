<?php

use App\Console\Commands\SendActivityReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ส่ง LINE reminder กิจกรรมพรุ่งนี้ ทุกวันเวลา 07:00
Schedule::command(SendActivityReminders::class)
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/reminders.log'));

