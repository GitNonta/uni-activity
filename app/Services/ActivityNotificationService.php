<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\ActivityBroadcastSent;
use App\Models\Activity;
use App\Models\ActivityBroadcast;
use App\Models\ActivityReminderLog;
use App\Models\AdminAuditLog;
use App\Models\Notification;
use App\Models\Registration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service สำหรับระบบบรอดแคสต์ข้อความด่วน และ Auto-Reminder กิจกรรม (24h & 2h)
 */
class ActivityNotificationService
{
    public function __construct(
        private readonly LineService $lineService
    ) {}

    /**
     * ส่งข้อความบรอดแคสต์เฉพาะกลุ่มนักศึกษาที่ลงทะเบียนในกิจกรรม
     *
     * @param array{title: string, message: string, type: string, target_audience: string, channels: array<string>} $data
     */
    public function broadcastToActivity(Activity $activity, User $sender, array $data): ActivityBroadcast
    {
        $targetAudience = $data['target_audience'] ?? 'approved';
        $channels       = $data['channels'] ?? ['in_app', 'line'];
        $title          = trim($data['title']);
        $message        = trim($data['message']);
        $type           = $data['type'] ?? 'general';

        // 1. ดึงกลุ่มนักศึกษาเป้าหมาย
        $query = Registration::with('user')
            ->where('activity_id', $activity->id);

        if ($targetAudience === 'approved') {
            $query->whereIn('status', ['approved', 'confirmed', 'attended']);
        } elseif ($targetAudience === 'waitlisted') {
            $query->where('status', 'waitlisted');
        } else {
            // all
            $query->whereIn('status', ['registered', 'approved', 'waitlisted', 'confirmed', 'attended', 'pending']);
        }

        $registrations = $query->get();
        /** @var Collection<int, User> $users */
        $users = $registrations->pluck('user')->filter()->unique('id')->values();

        $recipientsCount = $users->count();
        $lineSentCount   = 0;

        DB::transaction(function () use (
            $activity,
            $sender,
            $data,
            $channels,
            $title,
            $message,
            $type,
            $targetAudience,
            $users,
            $recipientsCount,
            &$lineSentCount,
            &$broadcastRecord
        ): void {
            // 2. ส่ง In-App Notification (ถ้าเลือก in_app)
            if (in_array('in_app', $channels, true)) {
                foreach ($users as $student) {
                    Notification::create([
                        'user_id' => $student->id,
                        'title'   => "[ประกาศด่วน] {$title}",
                        'message' => $message,
                        'url'     => route('activities.show', $activity),
                        'type'    => 'activity_broadcast',
                        'is_read' => false,
                    ]);
                }
            }

            // 3. ส่ง LINE Notification (ถ้าเลือก line)
            if (in_array('line', $channels, true)) {
                $lineUserIds = $users
                    ->where('line_notify_enabled', true)
                    ->pluck('line_user_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                if (!empty($lineUserIds)) {
                    $flexMessage = $this->lineService->buildActivityBroadcastMessage(
                        $activity,
                        $title,
                        $message,
                        $type
                    );

                    $this->lineService->multicast($lineUserIds, [$flexMessage]);
                    $lineSentCount = count($lineUserIds);
                }
            }

            // 4. บันทึกข้อมูลประวัติการส่ง ActivityBroadcast
            $broadcastRecord = ActivityBroadcast::create([
                'activity_id'      => $activity->id,
                'sender_id'        => $sender->id,
                'title'            => $title,
                'message'          => $message,
                'type'             => $type,
                'target_audience'  => $targetAudience,
                'channels'         => $channels,
                'recipients_count' => $recipientsCount,
                'line_sent_count'  => $lineSentCount,
            ]);

            // 5. บันทึก Audit Log
            try {
                AdminAuditLog::create([
                    'user_id'    => $sender->id,
                    'action'     => 'activity_broadcast_sent',
                    'model_type' => Activity::class,
                    'model_id'   => $activity->id,
                    'new_data'   => [
                        'title'            => $title,
                        'type'             => $type,
                        'target_audience'  => $targetAudience,
                        'recipients_count' => $recipientsCount,
                        'line_sent_count'  => $lineSentCount,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to write AdminAuditLog for broadcast', ['error' => $e->getMessage()]);
            }
        });

        // 6. Broadcast Real-time WebSocket Event ผ่าน Reverb
        try {
            broadcast(new ActivityBroadcastSent($activity->id, [
                'id'           => (string) Str::uuid(),
                'activity_id'  => $activity->id,
                'title'        => $title,
                'message'      => $message,
                'type'         => $type,
                'sender_name'  => $sender->full_name ?? $sender->name ?? 'แอดมิน',
                'created_at'   => now()->toISOString(),
            ]));
        } catch (\Throwable $e) {
            Log::warning('Broadcasting ActivityBroadcastSent failed', ['error' => $e->getMessage()]);
        }

        return $broadcastRecord;
    }

    /**
     * คำนวณและประมวลผลการส่ง Auto-Reminder ล่วงหน้า 24 ชม. และ 2 ชม. ก่อนเริ่มกิจกรรม
     *
     * @return array{reminders_24h_sent: int, reminders_2h_sent: int, activities_processed: int}
     */
    public function sendScheduledReminders(?string $forcedWindow = null): array
    {
        $now = Carbon::now();
        $activities = Activity::where('status', '!=', 'cancelled')
            ->whereDate('activity_date', '>=', $now->copy()->subDay()->toDateString())
            ->whereDate('activity_date', '<=', $now->copy()->addDays(2)->toDateString())
            ->get();

        $count24h = 0;
        $count2h  = 0;
        $processedActivities = 0;

        foreach ($activities as $act) {
            if (!$act->activity_date) {
                continue;
            }

            $dateStr = $act->activity_date->format('Y-m-d');
            $timeStr = $act->start_time ? substr($act->start_time, 0, 5) : '09:00';
            $startDateTime = Carbon::parse("{$dateStr} {$timeStr}:00");

            // กิจกรรมที่ผ่านไปแล้ว ไม่ต้องส่งเตือน
            if ($startDateTime->isPast()) {
                continue;
            }

            $diffInMinutes = $now->diffInMinutes($startDateTime, false); // ค่าบวก = ยังไม่ถึงเวลา

            // ตรวจสอบช่วงเวลา 24 ชั่วโมงก่อนเริ่ม (ประมาณ 20 - 26 ชม.)
            $eligible24h = ($diffInMinutes >= 1200 && $diffInMinutes <= 1560); // 20 ชม. ถึง 26 ชม.
            // ตรวจสอบช่วงเวลา 2 ชั่วโมงก่อนเริ่ม (ประมาณ 45 - 150 นาที)
            $eligible2h  = ($diffInMinutes >= 45 && $diffInMinutes <= 150);   // 45 นาที ถึง 2.5 ชม.

            // 1. ส่ง 24h Reminder
            if (($forcedWindow === '24h' || ($forcedWindow === null && $eligible24h))) {
                $alreadySent = ActivityReminderLog::where('activity_id', $act->id)
                    ->where('reminder_type', '24h')
                    ->exists();

                if (!$alreadySent) {
                    $result = $this->sendActivityReminder($act, '24h');
                    if ($result['recipients_count'] > 0) {
                        $count24h++;
                    }
                }
            }

            // 2. ส่ง 2h Reminder
            if (($forcedWindow === '2h' || ($forcedWindow === null && $eligible2h))) {
                $alreadySent = ActivityReminderLog::where('activity_id', $act->id)
                    ->where('reminder_type', '2h')
                    ->exists();

                if (!$alreadySent) {
                    $result = $this->sendActivityReminder($act, '2h');
                    if ($result['recipients_count'] > 0) {
                        $count2h++;
                    }
                }
            }

            $processedActivities++;
        }

        return [
            'reminders_24h_sent'   => $count24h,
            'reminders_2h_sent'    => $count2h,
            'activities_processed' => $processedActivities,
        ];
    }

    /**
     * ส่ง Auto-Reminder สำหรับกิจกรรมเดียว (ใช้ได้ทั้งอัตโนมัติและแอดมินกดส่งเอง)
     *
     * @return array{recipients_count: int, line_sent_count: int}
     */
    public function sendActivityReminder(Activity $activity, string $window = '24h'): array
    {
        $registrations = Registration::with('user')
            ->where('activity_id', $activity->id)
            ->whereIn('status', ['registered', 'approved', 'waitlisted', 'confirmed', 'attended'])
            ->get();

        /** @var Collection<int, User> $users */
        $users = $registrations->pluck('user')->filter()->unique('id')->values();

        if ($users->isEmpty()) {
            // บันทึก log ว่าเช็คแล้วแต่ไม่มีผู้รับ
            ActivityReminderLog::updateOrCreate(
                ['activity_id' => $activity->id, 'reminder_type' => $window],
                ['sent_at' => now(), 'recipients_count' => 0, 'line_sent_count' => 0]
            );

            return ['recipients_count' => 0, 'line_sent_count' => 0];
        }

        $isTwoHours = $window === '2h';
        $title = $isTwoHours
            ? "เตือนความจำ: อีก 2 ชั่วโมง กิจกรรม {$activity->title} จะเริ่มแล้ว!"
            : "เตือนความจำ: พรุ่งนี้มีกิจกรรม {$activity->title}";

        $timeStr = $activity->start_time ? substr($activity->start_time, 0, 5) : '09:00';
        $message = "จัดที่ " . ($activity->location ?? 'สถานที่ตามที่กำหนด') . " เวลา {$timeStr} น. อย่าลืมมาร่วมกิจกรรมตามเวลาเพื่อรับชั่วโมงกิจกรรม {$activity->activity_hours} ชม.";

        // 1. สร้าง In-App Notification
        foreach ($users as $student) {
            Notification::create([
                'user_id' => $student->id,
                'title'   => $title,
                'message' => $message,
                'url'     => route('activities.show', $activity),
                'type'    => 'reminder',
                'is_read' => false,
            ]);
        }

        // 2. ส่ง LINE Notification
        $lineUserIds = $users
            ->where('line_notify_enabled', true)
            ->pluck('line_user_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $lineSentCount = 0;
        if (!empty($lineUserIds)) {
            // ใช้ Flex message เตือนความจำ
            $flex = $this->lineService->buildReminderMessage($activity, 'นักศึกษา', $window);
            $this->lineService->multicast($lineUserIds, [$flex]);
            $lineSentCount = count($lineUserIds);
        }

        // 3. บันทึก ActivityReminderLog เพื่อไม่ให้ส่งซ้ำ
        ActivityReminderLog::updateOrCreate(
            ['activity_id' => $activity->id, 'reminder_type' => $window],
            [
                'sent_at'          => now(),
                'recipients_count' => $users->count(),
                'line_sent_count'  => $lineSentCount,
            ]
        );

        Log::info("Auto-Reminder [{$window}] sent for activity #{$activity->id}", [
            'recipients' => $users->count(),
            'line_sent'  => $lineSentCount,
        ]);

        return [
            'recipients_count' => $users->count(),
            'line_sent_count'  => $lineSentCount,
        ];
    }

    /**
     * ดึงข้อมูลสถิติและการตั้งค่าบรอดแคสต์สำหรับกิจกรรม
     *
     * @return array<string, mixed>
     */
    public function getActivityBroadcastStats(Activity $activity): array
    {
        $registrations = Registration::with('user')
            ->where('activity_id', $activity->id)
            ->get();

        $totalRegistered = $registrations->count();
        $approvedCount   = $registrations->whereIn('status', ['approved', 'confirmed', 'attended'])->count();
        $waitlistedCount = $registrations->where('status', 'waitlisted')->count();

        $lineLinkedCount = $registrations->pluck('user')
            ->filter(fn($u) => $u && $u->line_user_id && $u->line_notify_enabled)
            ->unique('id')
            ->count();

        $reminder24h = ActivityReminderLog::where('activity_id', $activity->id)
            ->where('reminder_type', '24h')
            ->first();

        $reminder2h = ActivityReminderLog::where('activity_id', $activity->id)
            ->where('reminder_type', '2h')
            ->first();

        $broadcastHistory = ActivityBroadcast::with('sender')
            ->where('activity_id', $activity->id)
            ->orderByDesc('created_at')
            ->get();

        return [
            'total_registered'  => $totalRegistered,
            'approved_count'    => $approvedCount,
            'waitlisted_count'  => $waitlistedCount,
            'line_linked_count' => $lineLinkedCount,
            'reminder_24h'      => $reminder24h,
            'reminder_2h'       => $reminder2h,
            'broadcast_history' => $broadcastHistory,
        ];
    }
}
