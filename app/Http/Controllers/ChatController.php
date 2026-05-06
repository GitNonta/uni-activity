<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\JobListing;
use App\Services\SocketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MongoDB\BSON\UTCDateTime;

class ChatController extends Controller
{
    /** แสดงหน้าแชทของนักศึกษาสำหรับประกาศงานนั้น */
    public function show(int $jobId)
    {
        $job = JobListing::findOrFail($jobId);
        $messages = ChatMessage::where('job_id', $jobId)
            ->where(function ($q) {
                $q->where('sender_id', Auth::id())
                  ->orWhere('sender_role', 'admin');
            })
            ->orderBy('created_at', 'asc')
            ->get();

        // Mark admin messages as read immediately when student opens chat
        ChatMessage::where('job_id', $jobId)
            ->where('student_id', Auth::id())
            ->where('sender_role', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Emit read status to admin in real-time
        $adminIds = ChatMessage::where('job_id', $jobId)
            ->where('sender_role', 'admin')
            ->distinct()
            ->pluck('sender_id')
            ->filter()
            ->values()
            ->all();

        foreach ($adminIds as $adminId) {
            SocketService::emit('chat:admin:' . $adminId, 'chat:read', ['job_id' => $jobId]);
        }

        return view('chat.show', compact('job', 'messages'));
    }

    /** นักศึกษาส่งข้อความ + ไฟล์แนบ */
    public function send(Request $request, int $jobId)
    {
        $request->validate([
            'message'       => 'nullable|string|max:2000',
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,zip,txt',
        ]);

        if (empty($request->message) && empty($request->file('attachments'))) {
            return response()->json(['error' => 'กรุณาพิมพ์ข้อความหรือแนบไฟล์'], 422);
        }

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('chat', $filename, 'public');
                $attachments[] = [
                    'filename'      => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getMimeType(),
                    'size'          => $file->getSize(),
                    'url'           => Storage::url($path),
                ];
            }
        }

        $msg = ChatMessage::create([
            'job_id'      => $jobId,
            'sender_id'   => Auth::id(),
            'sender_role' => 'student',
            'sender_name'  => Auth::user()->full_name ?? Auth::user()->name ?? 'นักศึกษา',
            'sender_photo' => Auth::user()->profile_photo ? asset('storage/' . Auth::user()->profile_photo) : null,
            'student_id'   => Auth::id(),
            'message'     => $request->message ?? '',
            'attachments' => $attachments,
        ]);

        $formatted = $this->formatMessage($msg);

        SocketService::emit('chat:admin:'  . $jobId,              'chat:message', $formatted);
        SocketService::emit('chat:thread:' . $jobId . ':' . Auth::id(), 'chat:message', $formatted);
        SocketService::emit('chat:student:' . Auth::id(),          'chat:message', $formatted);

        return response()->json(['success' => true, 'message' => $formatted]);
    }

    /** ประวัติข้อความสำหรับ floating widget (JSON) */
    public function messages(int $jobId)
    {
        $userId = Auth::id();

        $messages = ChatMessage::where('job_id', $jobId)
            ->where(function ($q) use ($userId) {
                $q->where('student_id', $userId)
                  ->orWhere(function ($q2) use ($userId) {
                      $q2->where('sender_role', 'student')
                         ->where('sender_id', $userId);
                  });
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($m) => $this->formatMessage($m));

        return response()->json(['messages' => $messages]);
    }

    /** รายการ threads ของนักศึกษา (สำหรับ floating widget) */
    public function myThreads()
    {
        $userId = Auth::id();

        try {
            $rawThreads = ChatMessage::raw(function ($collection) use ($userId) {
                return $collection->aggregate([
                    ['$match' => ['$or' => [
                        ['student_id' => $userId],
                        ['sender_role' => 'student', 'sender_id' => $userId],
                    ]]],

                    ['$sort'  => ['created_at' => -1]],
                    ['$group' => [
                        '_id'              => '$job_id',
                        'last_message'     => ['$first' => '$message'],
                        'last_sender_role' => ['$first' => '$sender_role'],
                        'last_read_at'     => ['$first' => '$read_at'],
                        'last_time'        => ['$first' => '$created_at'],
                        'attach_count'     => ['$sum' => ['$cond' => [
                            'if'   => ['$isArray' => '$attachments'],
                            'then' => ['$size'    => '$attachments'],
                            'else' => 0,
                        ]]],
                        'unread'           => ['$sum' => [
                            '$cond' => [
                                'if'   => ['$and' => [
                                    ['$eq' => ['$sender_role', 'admin']],
                                    ['$eq' => ['$read_at',     null]],
                                ]],
                                'then' => 1,
                                'else' => 0,
                            ],
                        ]],
                    ]],
                    ['$sort' => ['last_time' => -1]],
                ]);
            });

            $arrRaw = [];
            foreach ($rawThreads as $item) { $arrRaw[] = $item; }
        } catch (\Throwable $e) {
            Log::error('myThreads aggregate error: ' . $e->getMessage());
            return response()->json(['threads' => [], 'total_unread' => 0]);
        }

        $arr    = collect($arrRaw);
        $jobIds = $arr->pluck('_id')->unique()->filter()->values()->all();
        $jobs   = \App\Models\JobListing::whereIn('id', $jobIds)->get()->keyBy('id');

        $threads = $arr->map(function ($t) use ($jobs) {
            $job = $jobs->get($t['_id']);

            $raw  = $t['last_time'] ?? null;
            $time = null;
            if ($raw instanceof UTCDateTime) {
                $time = \Carbon\Carbon::parse($raw->toDateTime());
            } elseif ($raw !== null) {
                try { $time = \Carbon\Carbon::parse($raw); } catch (\Throwable $_) {}
            }

            $lastMsg = $t['last_message'] ?? '';
            if (!$lastMsg && (($t['attach_count'] ?? 0) > 0)) {
                $lastMsg = '📎 ไฟล์แนบ';
            }

            $readAtRaw = $t['last_read_at'] ?? null;
            $readAt    = $readAtRaw instanceof UTCDateTime
                ? $readAtRaw->toDateTime()->format('c')
                : ($readAtRaw ? (string) $readAtRaw : null);

            return [
                'job_id'           => (int) ($t['_id'] ?? 0),
                'job_title'        => $job?->title ?? "งาน #{$t['_id']}",
                'last_message'     => $lastMsg,
                'last_sender_role' => $t['last_sender_role'] ?? 'student',
                'last_read_at'     => $readAt,
                'last_time'        => $time?->toISOString(),
                'last_time_human'  => $time?->diffForHumans(),
                'unread'           => (int) ($t['unread'] ?? 0),
            ];
        })->values();

        $totalUnread = $threads->sum('unread');

        return response()->json(['threads' => $threads, 'total_unread' => $totalUnread]);
    }

    /** Mark ข้อความทั้งหมดของ job นี้ว่าอ่านแล้ว (นักศึกษาเปิดหน้าแชท) */
    public function markRead(int $jobId)
    {
        $userId = Auth::id();

        ChatMessage::where('job_id', $jobId)
            ->where('student_id', $userId)
            ->where('sender_role', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        SocketService::emit('chat:student:' . $userId, 'chat:read', ['job_id' => $jobId]);

        return response()->json(['success' => true]);
    }

    /** Check if any admin who replied to this job is currently online */
    public function adminOnlineStatus(int $jobId)
    {
        // Get list of admin IDs who have replied to this job
        $adminIds = ChatMessage::where('job_id', $jobId)
            ->where('sender_role', 'admin')
            ->distinct()
            ->pluck('sender_id')
            ->filter()
            ->values()
            ->all();

        if (empty($adminIds)) {
            return response()->json(['is_online' => false]);
        }

        // Check if any of these admins were active in last 2 minutes
        $online = \App\Models\User::whereIn('id', $adminIds)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', now()->subMinutes(2))
            ->exists();

        return response()->json(['is_online' => $online]);
    }

    /** Format สำหรับส่งไป Socket.io */
    private function formatMessage(ChatMessage $msg): array
    {
        $readAt = $msg->read_at;
        if ($readAt instanceof UTCDateTime) {
            $readAt = \Carbon\Carbon::parse($readAt->toDateTime())->toISOString();
        } elseif ($readAt instanceof \Carbon\Carbon) {
            $readAt = $readAt->toISOString();
        } else {
            $readAt = $readAt ? (string) $readAt : null;
        }

        return [
            'id'           => (string) $msg->_id,
            'job_id'       => $msg->job_id,
            'sender_id'    => $msg->sender_id,
            'sender_role'  => $msg->sender_role,
            'sender_name'  => $msg->sender_name ?? 'ผู้ใช้',
            'sender_photo' => $msg->sender_photo,
            'message'      => $msg->message,
            'attachments'  => $msg->attachments ?? [],
            'created_at'   => $msg->created_at?->toISOString(),
            'read_at'      => $readAt,
        ];
    }
}
