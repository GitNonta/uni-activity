<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\JobListing;
use App\Models\User;
use App\Services\SocketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminInboxController extends Controller
{
    /** รายการกล่องข้อความ - กลุ่มตาม job + student */
    public function index()
    {
        // Aggregate: distinct (job_id, student_id) threads, sorted by latest message
        $rawThreads = ChatMessage::raw(function ($collection) {
            return $collection->aggregate([
                ['$sort' => ['created_at' => -1]],
                ['$group' => [
                    '_id'          => ['job_id' => '$job_id', 'student_id' => '$student_id'],
                    'last_message' => ['$first' => '$message'],
                    'last_time'    => ['$first' => '$created_at'],
                    'sender_name'  => ['$first' => [
                        '$cond' => [
                            'if'   => ['$eq' => ['$sender_role', 'student']],
                            'then' => '$sender_name',
                            'else' => '$$REMOVE',
                        ],
                    ]],
                    'unread' => ['$sum' => [
                        '$cond' => [
                            'if'   => ['$and' => [
                                ['$eq'  => ['$sender_role', 'student']],
                                ['$eq'  => ['$read_at', null]],
                            ]],
                            'then' => 1,
                            'else' => 0,
                        ],
                    ]],
                ]],
                ['$match' => ['_id.student_id' => ['$ne' => null]]],
                ['$sort'  => ['last_time' => -1]],
            ]);
        });

        // Batch-load MySQL data to avoid N+1
        $threadsArr = [];
        foreach ($rawThreads as $t) {
            $threadsArr[] = $t;
        }
        $threads = collect($threadsArr);
        $jobIds       = $threads->pluck('_id.job_id')->unique()->filter()->values()->all();
        $studentIds   = $threads->pluck('_id.student_id')->unique()->filter()->values()->all();

        $jobs     = JobListing::whereIn('id', $jobIds)->get()->keyBy('id');
        $students = User::whereIn('id', $studentIds)->get()->keyBy('id');

        $result = $threads->map(function ($t) use ($jobs, $students) {
            $jobId     = $t['_id']['job_id']     ?? null;
            $studentId = $t['_id']['student_id'] ?? null;
            $job       = $jobs->get($jobId);
            $student   = $students->get($studentId);

            return [
                'job_id'       => $jobId,
                'student_id'   => $studentId,
                'job_title'    => $job?->title    ?? "งาน #{$jobId}",
                'student_name'  => $student?->full_name ?? ($t['sender_name'] ?? 'นักศึกษา'),
                'student_photo' => $student?->profile_photo ? asset('storage/' . $student->profile_photo) : null,
                'last_message' => $t['last_message'] ?? '',
                'last_time'    => isset($t['last_time'])
                    ? \Carbon\Carbon::parse($t['last_time']->toDateTime())
                    : null,
                'unread'       => (int) ($t['unread'] ?? 0),
            ];
        });

        return view('admin.inbox.index', ['threads' => $result]);
    }

    /** แสดงการสนทนาระหว่าง admin กับ student คนหนึ่งสำหรับงานหนึ่ง */
    public function show(int $jobId, int $studentId)
    {
        $job     = JobListing::findOrFail($jobId);
        $student = User::findOrFail($studentId);

        $messages = ChatMessage::where('job_id', $jobId)
            ->where(function ($q) use ($studentId) {
                $q->where('student_id', $studentId)
                  ->orWhere(function ($q2) use ($studentId) {
                      $q2->where('sender_role', 'student')
                         ->where('sender_id', $studentId);
                  });
            })
            ->orderBy('created_at', 'asc')
            ->get();

        // Mark student messages as read immediately when admin opens chat
        ChatMessage::where('job_id', $jobId)
            ->where('student_id', $studentId)
            ->where('sender_role', 'student')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Also match old messages without student_id field
        ChatMessage::where('job_id', $jobId)
            ->where('sender_id', $studentId)
            ->where('sender_role', 'student')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Emit read status to student in real-time
        SocketService::emit('chat:student:' . $studentId, 'chat:read', ['job_id' => $jobId]);

        return view('admin.inbox.show', compact('job', 'student', 'messages'));
    }

    /** Admin ส่งข้อความในการสนทนานั้น */
    public function send(Request $request, int $jobId, int $studentId)
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
                $path     = $file->storeAs('chat', $filename, 'public');
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
            'sender_role' => 'admin',
            'sender_name'  => Auth::user()->full_name ?? Auth::user()->name ?? 'ผู้ดูแล',
            'sender_photo' => Auth::user()->profile_photo ? asset('storage/' . Auth::user()->profile_photo) : null,
            'student_id'   => $studentId,
            'message'     => $request->message ?? '',
            'attachments' => $attachments,
        ]);

        $formatted = $this->formatMessage($msg);

        // Notify the student's chat view
        SocketService::emit('chat:student:' . $studentId, 'chat:message', $formatted);
        // Notify the thread room (admin inbox show page, and student's thread join)
        SocketService::emit('chat:thread:' . $jobId . ':' . $studentId, 'chat:message', $formatted);

        return response()->json(['success' => true, 'message' => $formatted]);
    }

    /** Mark ข้อความจาก student ว่าอ่านแล้ว */
    public function markRead(int $jobId, int $studentId)
    {
        ChatMessage::where('job_id', $jobId)
            ->where('student_id', $studentId)
            ->where('sender_role', 'student')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Also match old messages without student_id field
        ChatMessage::where('job_id', $jobId)
            ->where('sender_id', $studentId)
            ->where('sender_role', 'student')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    private function formatMessage(ChatMessage $msg): array
    {
        return [
            'id'          => (string) $msg->_id,
            'job_id'      => $msg->job_id,
            'sender_id'   => $msg->sender_id,
            'sender_role' => $msg->sender_role,
            'sender_name'  => $msg->sender_name ?? 'ผู้ดูแล',
            'sender_photo' => $msg->sender_photo,
            'student_id'   => $msg->student_id,
            'message'     => $msg->message,
            'attachments' => $msg->attachments ?? [],
            'created_at'  => $msg->created_at?->toISOString(),
        ];
    }
}
