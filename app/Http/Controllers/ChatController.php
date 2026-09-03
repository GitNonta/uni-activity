<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EditMessageRequest;
use App\Http\Requests\SendMessageRequest;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * คอนโทรลเลอร์แชทสำหรับนักศึกษา (Thin Controller)
 */
class ChatController extends Controller
{
    public function __construct(
        protected readonly ChatService $chatService
    ) {}

    /**
     * แสดงหน้าแชทของนักศึกษาสำหรับประกาศงานนั้น
     */
    public function show(int $jobId): View
    {
        $data = $this->chatService->getOrCreateRoomForJob(Auth::user(), $jobId);
        $staffUser = $jobId > 0 
            ? ($data['job']?->creator ?? $data['room']->users->where('id', '!=', Auth::id())->first() ?? User::whereIn('role', ['admin', 'staff'])->orderBy('id')->first())
            : ($data['room']->users->where('id', '!=', Auth::id())->first() ?? User::whereIn('role', ['admin', 'staff'])->orderBy('id')->first());

        return view('chat.show', [
            'job'        => $data['job'],
            'messages'   => $data['messages'],
            'room'       => $data['room'],
            'staffUser'  => $staffUser,
            'jobDeleted' => $data['room']->isJobDeleted(),
        ]);
    }

    /**
     * นักศึกษาส่งข้อความ + ไฟล์แนบ
     */
    public function send(SendMessageRequest $request, int $jobId): JsonResponse
    {
        if (empty($request->message) && empty($request->file('attachments'))) {
            return response()->json(['error' => 'กรุณาพิมพ์ข้อความหรือแนบไฟล์'], 422);
        }

        $formatted = $this->chatService->sendMessage(
            Auth::user(),
            $jobId,
            $request->filled('message') ? (string) $request->input('message') : null,
            $request->file('attachments') ?? []
        );

        return response()->json(['success' => true, 'message' => $formatted]);
    }

    /**
     * ประวัติข้อความสำหรับ floating widget (JSON)
     */
    public function messages(Request $request, int $jobId): JsonResponse
    {
        $result = $this->chatService->getRecentMessagesForJob(Auth::user(), $jobId);

        $messages = collect($result['messages']);

        // Incremental polling support: only messages newer than the client's watermark
        $after = (string) $request->query('after', '');
        if ($after !== '') {
            $messages = $messages->filter(
                fn ($m) => !empty($m['created_at'])
                    && \Carbon\Carbon::parse($m['created_at'])->gt(\Carbon\Carbon::parse($after))
            )->values();
        }

        return response()->json([
            'messages' => $messages,
            'room_id'  => $result['room_id'],
        ]);
    }

    /**
     * รายการ threads ของนักศึกษา (สำหรับ floating widget)
     */
    public function myThreads(): JsonResponse
    {
        $result = $this->chatService->getStudentThreads(Auth::user());

        return response()->json([
            'threads'      => $result['threads'],
            'total_unread' => $result['total_unread'],
        ]);
    }

    /**
     * Mark ข้อความทั้งหมดของ job นี้ว่าอ่านแล้ว
     */
    public function markRead(int $jobId): JsonResponse
    {
        $this->chatService->markAsRead(Auth::user(), $jobId);

        return response()->json(['success' => true]);
    }

    /**
     * สถานะการอ่านล่าสุดของฝ่ายตรงข้าม (fallback เมื่อ WebSocket event หลุด)
     */
    public function readStatus(int $jobId): JsonResponse
    {
        $data = $this->chatService->getOrCreateRoomForJob(Auth::user(), $jobId);
        $room = $data['room'];

        $otherPivot = \Illuminate\Support\Facades\DB::table('room_user')
            ->where('room_id', $room->id)
            ->where('user_id', '!=', Auth::id())
            ->first();

        return response()->json([
            'success'     => true,
            'other_read_at' => $otherPivot?->last_read_at
                ? \Carbon\Carbon::parse($otherPivot->last_read_at)->toISOString()
                : null,
        ]);
    }

    /**
     * ตรวจสอบว่ามี Admin online หรือไม่
     */
    public function adminOnlineStatus(int $jobId): JsonResponse
    {
        $online = $this->chatService->checkAdminOnlineStatus(Auth::user(), $jobId);

        return response()->json(['is_online' => $online]);
    }

    /**
     * ลบข้อความของนักศึกษา
     */
    public function deleteMessage(Message $message): JsonResponse
    {
        Gate::authorize('delete', $message);

        $this->chatService->deleteMessage($message, (int) Auth::id());

        return response()->json(['success' => true]);
    }

    /**
     * ดึงเนื้อหาล่าสุดของข้อความ (ใช้ก่อนเปิดหน้าจอแก้ไข)
     */
    public function showMessage(Message $message): JsonResponse
    {
        Gate::authorize('view', $message);

        return response()->json([
            'success' => true,
            'message' => [
                'id'        => $message->id,
                'body'      => $message->body,
                'is_edited' => (bool) $message->is_edited,
            ],
        ]);
    }

    /**
     * แก้ไขข้อความของนักศึกษา
     */
    public function editMessage(EditMessageRequest $request, Message $message): JsonResponse
    {
        Gate::authorize('update', $message);

        $formatted = $this->chatService->editMessage($message, (string) $request->input('message'));

        return response()->json(['success' => true, 'message' => $formatted]);
    }
}
