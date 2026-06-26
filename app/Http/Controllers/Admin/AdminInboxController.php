<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Room;
use App\Models\JobListing;
use App\Models\User;
use App\Repositories\ChatRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminInboxController extends Controller
{
    public function __construct(protected ChatRepository $chatRepository) {}
    public function index()
    {
        $currentUserId = Auth::id();
        $rooms = Room::with(['messages' => function($q) {
                $q->latest()->limit(1);
            }, 'users' => function($q) use ($currentUserId) {
                // Load students AND the current admin in one query
                $q->where(function($sub) use ($currentUserId) {
                    $sub->where('users.role', 'student')->orWhere('users.id', $currentUserId);
                });
            }, 'job'])
            ->orderByDesc(
                Message::select('created_at')
                    ->whereColumn('room_id', 'rooms.id')
                    ->latest()
                    ->limit(1)
            )
            ->get();

        $result = $rooms->map(function ($room) use ($currentUserId) {
            $lastMsg = $room->messages->first();
            $student = $room->users->where('role', 'student')->first();
            $me = $room->users->where('id', $currentUserId)->first();
            
            return [
                'job_id'       => $room->job_id,
                'room_id'      => $room->id,
                'student_id'   => $student?->id,
                'job_title'    => $room->job?->title ?? "งาน #{$room->job_id}",
                'student_name'  => $student?->full_name ?? 'นักศึกษา',
                'student_photo' => $student?->profile_photo ? asset('storage/' . $student->profile_photo) : null,
                'last_message' => $lastMsg?->body ?? '',
                'last_time'    => $lastMsg?->created_at,
                'unread'       => $room->messages()
                    ->where('user_id', '!=', $currentUserId)
                    ->where('created_at', '>', $me?->pivot?->last_read_at ?? '1970-01-01')
                    ->count(),
            ];
        })->filter(fn($t) => !empty($t['student_id']))->values();

        return view('admin.inbox.index', ['threads' => $result]);
    }

    public function show(int $jobId, int $studentId)
    {
        $job     = JobListing::findOrFail($jobId);
        $student = User::findOrFail($studentId);

        $room = Room::where('job_id', $jobId)
            ->whereHas('users', function ($q) use ($studentId) {
                $q->where('users.id', $studentId);
            })
            ->firstOrFail();

        $messages = $this->chatRepository->getRecentMessages($room);

        // Mark messages as read for admin
        $room->users()->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);

        return view('admin.inbox.show', compact('job', 'student', 'messages', 'room'));
    }

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

        $room = Room::where('job_id', $jobId)
            ->whereHas('users', function ($q) use ($studentId) {
                $q->where('users.id', $studentId);
            })
            ->firstOrFail();

        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('chat/attachments', 'public');
                $attachments[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path'          => $path,
                    'url'           => asset('storage/' . $path),
                    'mime_type'     => $file->getMimeType(),
                    'size'          => $file->getSize(),
                ];
            }
        }

        $msg = $this->chatRepository->sendMessage(
            $room, 
            Auth::user(), 
            $request->message ?? '', 
            count($attachments) > 0 && empty($request->message) ? 'file' : 'text',
            $attachments
        );

        $formatted = $this->formatMessage($msg);

        return response()->json(['success' => true, 'message' => $formatted]);
    }

    /** Mark ข้อความจาก student ว่าอ่านแล้ว */
    public function markRead(int $jobId, int $studentId)
    {
        $room = Room::where('job_id', $jobId)
            ->whereHas('users', function ($q) use ($studentId) {
                $q->where('users.id', $studentId);
            })
            ->first();

        if ($room) {
            $room->users()->updateExistingPivot(Auth::id(), ['last_read_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    private function formatMessage(Message $msg): array
    {
        $user = $msg->user;

        return [
            'id'      => $msg->id,
            'room_id' => $msg->room_id,
            'message' => $msg->body,
            'user'    => [
                'id'    => $msg->user_id,
                'name'  => $user?->full_name ?? 'ผู้ดูแล',
                'role'  => $user?->role ?? 'system',
                'photo' => $user?->profile_photo ? asset('storage/' . $user->profile_photo) : null,
            ],
            'attachments' => $msg->attachments ?? [],
            'created_at'  => $msg->created_at?->toISOString(),
        ];
    }
}
