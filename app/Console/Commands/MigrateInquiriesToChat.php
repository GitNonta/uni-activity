<?php

namespace App\Console\Commands;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateInquiriesToChat extends Command
{
    protected $signature = 'migrate:inquiries-to-chat';
    protected $description = 'Migrate job_inquiries from MySQL to MongoDB chat_messages';

    public function handle()
    {
        $this->info('เริ่มการย้ายข้อมูล job_inquiries → chat_messages...');

        // Load all job_inquiries
        $inquiries = DB::table('job_inquiries')
            ->orderBy('id')
            ->get();

        $this->info("พบ {$inquiries->count()} รายการ");

        $countQuestion = 0;
        $countAnswer = 0;

        foreach ($inquiries as $inquiry) {
            $student = User::find($inquiry->user_id);
            $studentName = $student?->full_name ?? 'นักศึกษา';
            $studentPhoto = $student?->profile_photo ?? null;

            // 1. Insert student question
            $questionMsg = ChatMessage::create([
                'job_id'       => $inquiry->job_listing_id,
                'sender_id'    => $inquiry->user_id,
                'sender_role'  => 'student',
                'sender_name'  => $studentName,
                'sender_photo' => $studentPhoto,
                'student_id'   => $inquiry->user_id,
                'message'      => $inquiry->question,
                'attachments'  => [],
                'read_at'      => null, // admin อ่านโดยปริยาย (ไม่มีสถานะอ่านในระบบเก่า)
                'created_at'   => $inquiry->created_at,
                'updated_at'   => $inquiry->created_at,
            ]);
            $countQuestion++;

            // 2. Insert admin answer (if exists)
            if (!empty($inquiry->answer)) {
                $admin = $inquiry->answered_by ? User::find($inquiry->answered_by) : null;
                $adminName = $admin?->full_name ?? 'ผู้ดูแล';
                $adminPhoto = $admin?->profile_photo ?? null;

                ChatMessage::create([
                    'job_id'       => $inquiry->job_listing_id,
                    'sender_id'    => $inquiry->answered_by ?? 0,
                    'sender_role'  => 'admin',
                    'sender_name'  => $adminName,
                    'sender_photo' => $adminPhoto,
                    'student_id'   => $inquiry->user_id,
                    'message'      => $inquiry->answer,
                    'attachments'  => [],
                    'read_at'      => $inquiry->read_at, // student อ่านแล้วเมื่อไหร่
                    'created_at'   => $inquiry->answered_at ?? $inquiry->updated_at,
                    'updated_at'   => $inquiry->answered_at ?? $inquiry->updated_at,
                ]);
                $countAnswer++;
            }
        }

        $this->info("เสร็จสิ้น: {$countQuestion} คำถาม, {$countAnswer} คำตอบ");
        return 0;
    }
}
