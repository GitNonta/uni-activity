<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EditMessageRequest;
use App\Http\Requests\SendMessageRequest;
use App\Models\Message;
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

        return view('chat.show', [
            'job'      => $data['job'],
            'messages' => $data['messages'],
            'room'     => $data['room'],
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
    public function messages(int $jobId): JsonResponse
    {
        $result = $this->chatService->getRecentMessagesForJob(Auth::user(), $jobId);

        return response()->json([
            'messages' => $result['messages'],
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
     * แก้ไขข้อความของนักศึกษา
     */
    public function editMessage(EditMessageRequest $request, Message $message): JsonResponse
    {
        Gate::authorize('update', $message);

        $formatted = $this->chatService->editMessage($message, (string) $request->input('message'));

        return response()->json(['success' => true, 'message' => $formatted]);
    }
}
