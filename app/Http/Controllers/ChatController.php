<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Room;
use App\Models\JobListing;
use App\Models\User;
use App\Repositories\ChatRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function __construct(protected ChatRepository $chatRepository) {}
    /** แสดงหน้าแชทของนักศึกษาสำหรับประกาศงานนั้น */
    public function show(int $jobId)
    {
        $job = JobListing::findOrFail($jobId);
        $userId = Auth::id();

        // Get or create room for this student and job
        $room = Room::where('job_id', $jobId)
            ->whereHas('users', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->first();

        if (!$room) {
            $adminIds = User::where('role', 'admin')->pluck('id')->toArray();
            $room = $this->chatRepository->createRoom(
                array_merge([$userId], $adminIds),
                'direct',
                "Chat for Job #$jobId",
                $jobId
            );
        }

        $messages = $this->chatRepository->getRecentMessages($room);

        // Mark messages as read
        $room->users()->updateExistingPivot($userId, ['last_read_at' => now()]);

        return view('chat.show', compact('job', 'messages', 'room'));
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

        $userId = Auth::id();
        $room = Room::where('job_id', $jobId)
            ->whereHas('users', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->first();

        if (!$room) {
            $job = JobListing::findOrFail($jobId);
            $room = $this->chatRepository->createRoom([$userId, $job->created_by], 'direct', $job->title, $jobId);
        }

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

        $body = $request->message ?? '';
        $msg = $this->chatRepository->sendMessage(
            $room, 
            Auth::user(), 
            $body, 
            'text',
            $attachments
        );

        $formatted = $this->formatMessage($msg);

        return response()->json(['success' => true, 'message' => $formatted]);
    }

    /** ประวัติข้อความสำหรับ floating widget (JSON) */
    public function messages(int $jobId)
    {
        $userId = Auth::id();

        $room = Room::where('job_id', $jobId)
            ->whereHas('users', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->first();

        if (!$room) {
            return response()->json(['messages' => []]);
        }

        $messages = $this->chatRepository->getRecentMessages($room)
            ->map(fn($m) => $this->formatMessage($m));

        return response()->json(['messages' => $messages]);
    }

    /** รายการ threads ของนักศึกษา (สำหรับ floating widget) */
    public function myThreads()
    {
        $userId = Auth::id();

        $rooms = Room::whereHas('users', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->with(['messages' => function ($q) {
                $q->latest()->limit(1);
            }, 'users' => function($q) use ($userId) {
                $q->where('users.id', $userId);
            }, 'job'])
            ->get();

        $threads = $rooms->map(function ($room) use ($userId) {
            $lastMsg = $room->messages->first();
            $job = $room->job;
            $me = $room->users->where('id', $userId)->first();
            
            // Calculate unread using eager loaded pivot
            $unread = $room->messages()
                ->where('user_id', '!=', $userId)
                ->where('created_at', '>', $me->pivot->last_read_at ?? '1970-01-01')
                ->count();

            return [
                'job_id'           => $room->job_id,
                'job_title'        => $job?->title ?? "งาน #{$room->job_id}",
                'last_message'     => $lastMsg?->body ?? '',
                'last_sender_role' => $lastMsg?->user_id === $userId ? 'self' : 'other',
                'last_time'        => $lastMsg?->created_at?->toISOString(),
                'last_time_human'  => $lastMsg?->created_at?->diffForHumans(),
                'unread'           => $unread,
                'thread_room'      => 'chat.room.' . $room->id,
                'thread_token'     => null, // Tokens no longer needed for Reverb private channels
            ];
        })->sortByDesc('last_time')->values();

        $totalUnread = $threads->sum('unread');

        return response()->json(['threads' => $threads, 'total_unread' => $totalUnread]);
    }

    /** Mark ข้อความทั้งหมดของ job นี้ว่าอ่านแล้ว (นักศึกษาเปิดหน้าแชท) */
    public function markRead(int $jobId)
    {
        $userId = Auth::id();
        $room = Room::where('job_id', $jobId)
            ->whereHas('users', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->first();

        if ($room) {
            $room->users()->updateExistingPivot($userId, ['last_read_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    /** Check if any admin who replied to this job is currently online */
    public function adminOnlineStatus(int $jobId)
    {
        $room = Room::where('job_id', $jobId)->first();
        if (!$room) return response()->json(['is_online' => false]);

        $adminIds = $room->users()
            ->whereIn('users.role', ['admin', 'staff'])
            ->pluck('users.id')
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

    public function deleteMessage($id)
    {
        $message = Message::findOrFail($id);
        
        if ($message->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $roomId = $message->room_id;
        $message->delete();
        
        $studentId = Auth::id(); // since this is student side
        broadcast(new \App\Events\MessageDeleted($id, $roomId, $studentId));
        
        return response()->json(['success' => true]);
    }

    public function editMessage(Request $request, $id)
    {
        $request->validate(['message' => 'required|string|max:2000']);
        $message = Message::findOrFail($id);
        
        if ($message->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message->body = $request->message;
        $message->save();

        broadcast(new \App\Events\MessageEdited($message));

        return response()->json(['success' => true, 'message' => $this->formatMessage($message)]);
    }

    /** Format สำหรับส่งไป Socket.io */
    private function formatMessage(Message $msg): array
    {
        $user = $msg->user;
        
        return [
            'id'      => $msg->id,
            'room_id' => $msg->room_id,
            'message' => $msg->body,
            'user'    => [
                'id'    => $msg->user_id,
                'name'  => $user?->full_name ?? 'ผู้ใช้',
                'role'  => $user?->role ?? 'system',
                'photo' => $user?->profile_photo ? asset('storage/' . $user->profile_photo) : null,
            ],
            'attachments' => $msg->attachments ?? [],
            'created_at'  => $msg->created_at?->toISOString(),
        ];
    }
}
