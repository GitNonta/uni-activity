<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Room;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * คอนโทรลเลอร์กล่องข้อความ Admin/Staff (Thin Controller)
 */
class AdminInboxController extends Controller
{
    public function __construct(
        protected readonly ChatService $chatService
    ) {}

    /**
     * แสดงรายการห้องสนทนาทั้งหมดของ Admin/Staff
     */
    public function index(): View
    {
        $threads = $this->chatService->getAdminThreads(Auth::user());

        return view('admin.inbox.index', ['threads' => $threads]);
    }

    /**
     * JSON endpoint — จำนวนข้อความที่ยังไม่ได้อ่านของ admin/staff
     */
    public function unreadCount(): JsonResponse
    {
        $total = $this->chatService->getAdminUnreadCount(Auth::user());

        return response()->json(['unread' => $total]);
    }

    /**
     * แสดงหน้าต่างสนทนาระหว่าง Admin/Staff กับ Student
     */
    public function show(int $jobId, int $studentId): View
    {
        $data = $this->chatService->getOrCreateRoomForAdmin(Auth::user(), $jobId, $studentId);

        return view('admin.inbox.show', [
            'job'      => $data['job'],
            'student'  => $data['student'],
            'messages' => $data['messages'],
            'room'     => $data['room'],
        ]);
    }

    /**
     * Admin/Staff ส่งข้อความถึง Student
     */
    public function send(\App\Http\Requests\SendMessageRequest $request, int $jobId, int $studentId): JsonResponse
    {
        if (empty($request->message) && empty($request->file('attachments'))) {
            return response()->json(['error' => 'กรุณาพิมพ์ข้อความหรือแนบไฟล์'], 422);
        }

        $formatted = $this->chatService->sendAdminMessage(
            Auth::user(),
            $jobId,
            $studentId,
            $request->filled('message') ? (string) $request->input('message') : null,
            $request->file('attachments') ?? []
        );

        return response()->json(['success' => true, 'message' => $formatted]);
    }

    /**
     * Mark ข้อความจาก student ว่าอ่านแล้ว
     */
    public function markRead(int $jobId, int $studentId): JsonResponse
    {
        $this->chatService->markAsRead(Auth::user(), $jobId, $studentId);

        return response()->json(['success' => true]);
    }

    /**
     * ลบข้อความในห้องแชท
     */
    public function deleteMessage(Message $message): JsonResponse
    {
        $message->loadMissing(['room.job', 'room.users']);
        Gate::authorize('delete', $message);

        $student = $message->room?->users()->where('users.role', 'student')->first();
        $this->chatService->deleteMessage($message, (int) ($student?->id ?? 0));

        return response()->json(['success' => true]);
    }

    /**
     * ดึงเนื้อหาล่าสุดของข้อความ (ใช้ก่อนเปิดหน้าจอแก้ไข)
     */
    public function showMessage(Message $message): JsonResponse
    {
        $message->loadMissing(['room.users']);
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
     * แก้ไขข้อความในห้องแชท
     */
    public function editMessage(\App\Http\Requests\EditMessageRequest $request, Message $message): JsonResponse
    {
        $message->loadMissing(['room.job', 'room.users']);
        Gate::authorize('update', $message);

        $formatted = $this->chatService->editMessage($message, (string) $request->input('message'));

        return response()->json(['success' => true, 'message' => $formatted]);
    }

    /**
     * ลบห้องสนทนาทั้งหมด
     */
    public function deleteChat(int|string $jobId, int|string $userId): JsonResponse
    {
        $numericJobId = (int) $jobId;
        $numericUserId = (int) $userId;

        $roomQuery = Room::whereHas('users', fn($q) => $q->where('users.id', $numericUserId));
        if ($numericJobId === 0) {
            $roomQuery->whereNull('job_id')
                ->whereHas('users', fn($q) => $q->where('users.id', Auth::id()));
        } else {
            $roomQuery->where('job_id', $numericJobId);
        }
        $room = $roomQuery->firstOrFail();

        $room->loadMissing(['users', 'job']);
        Gate::authorize('delete', $room);

        $this->chatService->deleteRoom($room, $numericUserId);

        return response()->json(['success' => true]);
    }
}
