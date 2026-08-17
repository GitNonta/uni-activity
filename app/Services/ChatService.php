<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\ChatDeleted;
use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessagesRead;
use App\Models\JobListing;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Repositories\ChatRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * เซอร์วิสจัดการระบบ Real-time Chat, ห้องสนทนา และข้อความ
 */
class ChatService
{
    public function __construct(
        private readonly ChatRepository $chatRepository
    ) {}

    /**
     * ค้นหาหรือสร้างห้องแชทสำหรับ Job หรือการติดต่อเจ้าหน้าที่
     *
     * @return array{room: Room, job: object, messages: Collection}
     */
    public function getOrCreateRoomForJob(User $user, int $jobId): array
    {
        $userId = $user->id;
        $defaultAdminId = User::where('role', 'admin')->orderBy('id')->value('id') ?? 1;

        if ($jobId < 0) {
            $otherUserId = abs($jobId);
            $otherUser = User::findOrFail($otherUserId);
            $job = (object)['id' => $jobId, 'title' => $otherUser->full_name];
        } else {
            $job = $jobId === 0
                ? (object)['id' => 0, 'title' => 'ติดต่อสอบถามเจ้าหน้าที่']
                : JobListing::findOrFail($jobId);
        }

        $room = $this->findRoom($userId, $jobId, $defaultAdminId);

        if (!$room) {
            if ($jobId < 0) {
                $otherUserId = abs($jobId);
                $room = $this->chatRepository->createRoom(
                    [$userId, $otherUserId],
                    'direct',
                    'ติดต่อสอบถามเจ้าหน้าที่',
                    null
                );
            } elseif ($jobId === 0) {
                $room = $this->chatRepository->createRoom(
                    [$userId, $defaultAdminId],
                    'direct',
                    'ติดต่อสอบถามเจ้าหน้าที่',
                    null
                );
            } else {
                $jobModel = JobListing::findOrFail($jobId);
                $room = $this->chatRepository->createRoom(
                    [$userId, $jobModel->created_by],
                    'direct',
                    $jobModel->title,
                    $jobId
                );
            }
        }

        $messages = $this->chatRepository->getRecentMessages($room);

        // ทำเครื่องหมายว่าอ่านแล้ว (เฉพาะเมื่อมีข้อความใหม่ที่ยังไม่ได้อ่าน)
        $this->markAsRead($user, $jobId);

        return [
            'room'     => $room,
            'job'      => $job,
            'messages' => $messages,
        ];
    }

    /**
     * ส่งข้อความพร้อมไฟล์แนบ (ถ้ามี)
     *
     * @param  array<UploadedFile>  $uploadedFiles
     */
    public function sendMessage(User $user, int $jobId, ?string $body = null, array $uploadedFiles = []): array
    {
        $userId = $user->id;
        $defaultAdminId = User::where('role', 'admin')->orderBy('id')->value('id') ?? 1;
        $room = $this->findRoom($userId, $jobId, $defaultAdminId);

        if (!$room) {
            if ($jobId < 0) {
                $otherUserId = abs($jobId);
                $room = $this->chatRepository->createRoom([$userId, $otherUserId], 'direct', 'ติดต่อสอบถามเจ้าหน้าที่', null);
            } elseif ($jobId === 0) {
                $room = $this->chatRepository->createRoom([$userId, $defaultAdminId], 'direct', 'ติดต่อสอบถามเจ้าหน้าที่', null);
            } else {
                $job = JobListing::findOrFail($jobId);
                $room = $this->chatRepository->createRoom([$userId, $job->created_by], 'direct', $job->title, $jobId);
            }
        }

        $attachments = [];
        foreach ($uploadedFiles as $file) {
            if ($file instanceof UploadedFile) {
                $path = $file->store('chat/attachments', 'public');
                $attachments[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path'          => $path,
                    'url'           => '/storage/' . $path,
                    'mime_type'     => $file->getMimeType(),
                    'size'          => $file->getSize(),
                ];
            }
        }

        $msg = $this->chatRepository->sendMessage(
            $room,
            $user,
            $body ?? '',
            'text',
            $attachments
        );

        return $this->formatMessage($msg);
    }

    /**
     * ดึงข้อความล่าสุดสำหรับ Job Room
     *
     * @return array{messages: Collection, room_id: int|null}
     */
    public function getRecentMessagesForJob(User $user, int $jobId): array
    {
        $defaultAdminId = User::where('role', 'admin')->orderBy('id')->value('id') ?? 1;
        $room = $this->findRoom($user->id, $jobId, $defaultAdminId);

        if (!$room) {
            return [
                'messages' => collect(),
                'room_id'  => null,
            ];
        }

        $messages = $this->chatRepository->getRecentMessages($room)
            ->map(fn(Message $m) => $this->formatMessage($m));

        return [
            'messages' => $messages,
            'room_id'  => $room->id,
        ];
    }

    /**
     * ดึงรายการ Threads แชททั้งหมดของนักศึกษา
     *
     * @return array{threads: Collection, total_unread: int}
     */
    public function getStudentThreads(User $user): array
    {
        $userId = $user->id;
        $defaultAdminId = User::where('role', 'admin')->orderBy('id')->value('id') ?? 1;

        $rooms = Room::whereHas('users', function ($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->with(['messages' => function ($q) {
                $q->latest()->limit(1);
            }, 'users', 'job'])
            ->get();

        $threads = $rooms->map(function (Room $room) use ($userId, $defaultAdminId) {
            $lastMsg = $room->messages->first();
            $job = $room->job;
            $me = $room->users->where('id', $userId)->first();

            $unread = $room->messages()
                ->where('user_id', '!=', $userId)
                ->where('created_at', '>', $me->pivot->last_read_at ?? '1970-01-01')
                ->count();

            $otherUser = $room->users->where('id', '!=', $userId)->first();
            $avatarUrl = null;
            if ($otherUser && $otherUser->profile_photo) {
                $avatarUrl = '/storage/' . $otherUser->profile_photo;
            }

            if ($room->job_id) {
                $jobId = $room->job_id;
                $jobTitle = $job?->title ?? "งาน #{$room->job_id}";
            } else {
                if ($otherUser && $otherUser->id !== $defaultAdminId) {
                    $jobId = -$otherUser->id;
                    $jobTitle = $otherUser->full_name;
                } else {
                    $jobId = 0;
                    $jobTitle = 'ติดต่อสอบถามเจ้าหน้าที่';
                }
            }

            $staffUser = $otherUser ?? $job?->creator ?? User::where('role', 'admin')->orderBy('id')->first();

            return [
                'job_id'           => $jobId,
                'job_title'        => $jobTitle,
                'avatar'           => $avatarUrl,
                'staff_last_seen'  => $staffUser?->last_seen_at?->toISOString(),
                'last_message'     => $lastMsg?->body ?? '',
                'last_sender_role' => $lastMsg?->user_id === $userId ? 'self' : 'other',
                'last_time'        => $lastMsg?->created_at?->toISOString(),
                'last_time_human'  => $lastMsg?->created_at?->diffForHumans(),
                'unread'           => $unread,
                'thread_room'      => 'chat.room.' . $room->id,
                'thread_token'     => null,
            ];
        })->sortByDesc('last_time')->values();

        return [
            'threads'      => $threads,
            'total_unread' => (int) $threads->sum('unread'),
        ];
    }

    /**
     * ดึงรายการ Threads ทั้งหมดของ Admin/Staff
     */
    public function getAdminThreads(User $admin): Collection
    {
        $currentUserId = $admin->id;

        $rooms = Room::with(['messages' => function ($q) {
                $q->latest()->limit(1);
            }, 'users' => function ($q) use ($currentUserId) {
                $q->where(function ($sub) use ($currentUserId) {
                    $sub->where('users.role', 'student')->orWhere('users.id', $currentUserId);
                });
            }, 'job'])
            ->where(function ($q) use ($admin, $currentUserId) {
                $q->where(function ($sub) use ($admin, $currentUserId) {
                    $sub->whereNotNull('job_id')
                        ->when($admin->isStaff(), function ($inner) use ($currentUserId) {
                            $inner->whereHas('job', fn($jq) => $jq->where('created_by', $currentUserId));
                        });
                })->orWhere(function ($sub) use ($currentUserId) {
                    $sub->whereNull('job_id')
                        ->whereHas('users', fn($uq) => $uq->where('users.id', $currentUserId));
                });
            })
            ->orderByDesc(
                Message::select('created_at')
                    ->whereColumn('room_id', 'rooms.id')
                    ->latest()
                    ->limit(1)
            )
            ->get();

        return $rooms->map(function (Room $room) use ($currentUserId) {
            $lastMsg = $room->messages->first();
            $student = $room->users->where('role', 'student')->first();
            $me = $room->users->where('id', $currentUserId)->first();

            return [
                'job_id'            => $room->job_id ?? 0,
                'room_id'           => $room->id,
                'student_id'        => $student?->id,
                'job_title'         => $room->job_id ? ($room->job?->title ?? "งาน #{$room->job_id}") : 'ติดต่อสอบถามเจ้าหน้าที่',
                'student_name'      => $student?->full_name ?? 'นักศึกษา',
                'student_photo'     => $student?->profile_photo ? '/storage/' . $student->profile_photo : null,
                'student_last_seen' => $student?->last_seen_at?->toISOString(),
                'last_message'      => $lastMsg?->body ?? '',
                'last_time'         => $lastMsg?->created_at,
                'unread'            => $room->messages()
                    ->where('user_id', '!=', $currentUserId)
                    ->where('created_at', '>', $me?->pivot?->last_read_at ?? '1970-01-01')
                    ->count(),
            ];
        })->filter(fn(array $t) => !empty($t['student_id']))->values();
    }

    /**
     * คำนวณจำนวนข้อความที่ยังไม่ได้อ่านของ Admin/Staff
     */
    public function getAdminUnreadCount(User $admin): int
    {
        $currentUserId = $admin->id;

        $rooms = Room::with(['messages' => fn($q) => $q->latest()->limit(1), 'users'])
            ->where(function ($q) use ($admin, $currentUserId) {
                $q->where(function ($sub) use ($admin, $currentUserId) {
                    $sub->whereNotNull('job_id')
                        ->when($admin->isStaff(), function ($inner) use ($currentUserId) {
                            $inner->whereHas('job', fn($jq) => $jq->where('created_by', $currentUserId));
                        });
                })->orWhere(function ($sub) use ($currentUserId) {
                    $sub->whereNull('job_id')
                        ->whereHas('users', fn($uq) => $uq->where('users.id', $currentUserId));
                });
            })
            ->get();

        return (int) $rooms->sum(function (Room $room) use ($currentUserId) {
            $me = $room->users->where('id', $currentUserId)->first();
            return $room->messages()
                ->where('user_id', '!=', $currentUserId)
                ->where('created_at', '>', $me?->pivot?->last_read_at ?? '1970-01-01')
                ->count();
        });
    }

    /**
     * ค้นหาหรือสร้างห้องแชทสำหรับ Admin/Staff และ Student
     *
     * @return array{room: Room, job: object, student: User, messages: Collection}
     */
    public function getOrCreateRoomForAdmin(User $admin, int $jobId, int $studentId): array
    {
        $job     = $jobId === 0 ? (object) ['id' => 0, 'title' => 'ติดต่อสอบถามเจ้าหน้าที่'] : JobListing::findOrFail($jobId);
        $student = User::findOrFail($studentId);

        $roomQuery = Room::whereHas('users', fn($q) => $q->where('users.id', $studentId));
        if ($jobId === 0) {
            $roomQuery->whereNull('job_id')
                ->whereHas('users', fn($q) => $q->where('users.id', $admin->id));
        } else {
            $roomQuery->where('job_id', $jobId);
        }
        $room = $roomQuery->first();

        if (!$room) {
            if ($jobId === 0) {
                $room = $this->chatRepository->createRoom(
                    [$studentId, $admin->id],
                    'direct',
                    'ติดต่อสอบถามเจ้าหน้าที่',
                    null
                );
            } else {
                $room = $this->chatRepository->createRoom(
                    [$studentId, $admin->id],
                    'direct',
                    $job->title,
                    $jobId
                );
            }
        }

        $room->loadMissing(['users', 'job']);
        $isOwnJob = $room->job_id && ($room->job->created_by === $admin->id || $admin->isAdmin());
        $isParticipant = !$room->job_id && $room->users->contains($admin->id);
        if (!$isOwnJob && !$isParticipant) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงแชทนี้');
        }

        $messages = $this->chatRepository->getRecentMessages($room);

        // Mark messages as read for admin
        $room->users()->updateExistingPivot($admin->id, ['last_read_at' => now()]);

        return [
            'room'     => $room,
            'job'      => $job,
            'student'  => $student,
            'messages' => $messages,
        ];
    }

    /**
     * Admin/Staff ส่งข้อความถึง Student
     *
     * @param  array<UploadedFile>  $uploadedFiles
     */
    public function sendAdminMessage(User $admin, int $jobId, int $studentId, ?string $body = null, array $uploadedFiles = []): array
    {
        $roomQuery = Room::whereHas('users', fn($q) => $q->where('users.id', $studentId));
        if ($jobId === 0) {
            $roomQuery->whereNull('job_id')
                ->whereHas('users', fn($q) => $q->where('users.id', $admin->id));
        } else {
            $roomQuery->where('job_id', $jobId);
        }
        $room = $roomQuery->first();

        if (!$room) {
            if ($jobId === 0) {
                $room = $this->chatRepository->createRoom(
                    [$studentId, $admin->id],
                    'direct',
                    'ติดต่อสอบถามเจ้าหน้าที่',
                    null
                );
            } else {
                $job = JobListing::findOrFail($jobId);
                $room = $this->chatRepository->createRoom(
                    [$studentId, $admin->id],
                    'direct',
                    $job->title,
                    $jobId
                );
            }
        }

        $room->loadMissing(['users', 'job']);
        $isOwnJob = $room->job_id && ($room->job->created_by === $admin->id || $admin->isAdmin());
        $isParticipant = !$room->job_id && $room->users->contains($admin->id);
        if (!$isOwnJob && !$isParticipant) {
            abort(403, 'คุณไม่มีสิทธิ์ส่งข้อความในแชทนี้');
        }

        $attachments = [];
        foreach ($uploadedFiles as $file) {
            if ($file instanceof UploadedFile) {
                $path = $file->store('chat/attachments', 'public');
                $attachments[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path'          => $path,
                    'url'           => '/storage/' . $path,
                    'mime_type'     => $file->getMimeType(),
                    'size'          => $file->getSize(),
                ];
            }
        }

        $msg = $this->chatRepository->sendMessage(
            $room,
            $admin,
            $body ?? '',
            'text',
            $attachments
        );

        return $this->formatMessage($msg);
    }

    /**
     * ลบห้องสนทนาพร้อม Broadcast ChatDeleted
     */
    public function deleteRoom(Room $room, int $userId): void
    {
        $roomId = $room->id;
        Message::where('room_id', $roomId)->delete();
        $room->delete();

        broadcast(new ChatDeleted((string) $roomId, $userId));
    }

    /**
     * ทำเครื่องหมายห้องแชทของ Job ว่าอ่านแล้ว พร้อม Broadcast MessagesRead ทันที (1ms Real-time)
     */
    public function markAsRead(User $user, int $jobId, ?int $targetUserId = null): void
    {
        $defaultAdminId = $targetUserId ?? (User::where('role', 'admin')->orderBy('id')->value('id') ?? 1);
        $room = $this->findRoom($user->id, $jobId, $defaultAdminId);

        if ($room) {
            $userPivot = DB::table('room_user')
                ->where('room_id', $room->id)
                ->where('user_id', $user->id)
                ->first();

            $prevReadAt = $userPivot?->last_read_at;

            // ตรวจสอบว่ามีข้อความของอีกฝ่ายที่ยังไม่ได้อ่านหรือไม่
            $hasUnread = Message::where('room_id', $room->id)
                ->where('user_id', '!=', $user->id)
                ->when($prevReadAt, fn($q) => $q->where('created_at', '>', $prevReadAt))
                ->exists();

            // ถ้าไม่มีข้อความใหม่ที่ยังไม่ได้อ่าน และเคยบันทึกเวลาอ่านแล้ว ให้ข้าม (ไม่รีเซ็ตเวลาเป็น "เพิ่งอ่าน" ตอนรีเฟรช)
            if (!$hasUnread && $prevReadAt !== null) {
                return;
            }

            $now = now();
            $room->users()->updateExistingPivot($user->id, ['last_read_at' => $now]);

            $otherUser = $room->users()->where('users.id', '!=', $user->id)->first();
            $studentId = $otherUser && $otherUser->role === 'student' ? $otherUser->id : ($user->role === 'student' ? $user->id : null);

            // 1ms Instant Real-time WebSocket Broadcast
            broadcast(new MessagesRead((string) $room->id, $user->id, $now->toISOString(), $studentId));

            // Publish เข้าสู่ Dragonfly PubSub Stream
            try {
                app(DragonflyPubSubService::class)->publishChatEvent(
                    (string) $room->id,
                    'MessagesRead',
                    [
                        'room_id'     => (string) $room->id,
                        'reader_id'   => $user->id,
                        'read_at'     => $now->toISOString(),
                        'read_status' => 'เพิ่งอ่าน',
                    ]
                );
            } catch (\Throwable $e) {}
        }
    }

    /**
     * ตรวจสอบสถานะ Online ของ Admin ที่เกี่ยวข้องกับ Job Room
     */
    public function checkAdminOnlineStatus(User $user, int $jobId): bool
    {
        $defaultAdminId = User::where('role', 'admin')->orderBy('id')->value('id') ?? 1;
        $room = $this->findRoom($user->id, $jobId, $defaultAdminId);

        if (!$room) {
            return false;
        }

        $adminIds = $room->users()
            ->whereIn('users.role', ['admin', 'staff'])
            ->pluck('users.id')
            ->all();

        if (empty($adminIds)) {
            return false;
        }

        return User::whereIn('id', $adminIds)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', now()->subMinutes(2))
            ->exists();
    }

    /**
     * ลบข้อความพร้อมส่ง Broadcast Event
     */
    public function deleteMessage(Message $message, int $senderId): void
    {
        $id = $message->id;
        $roomId = $message->room_id;
        $message->delete();

        broadcast(new MessageDeleted((string) $id, (string) $roomId, $senderId));

        try {
            app(DragonflyPubSubService::class)->publishChatEvent(
                $roomId,
                'MessageDeleted',
                ['id' => (string) $id, 'room_id' => (string) $roomId]
            );
        } catch (\Throwable $e) {}
    }

    /**
     * แก้ไขข้อความพร้อมส่ง Broadcast Event
     */
    public function editMessage(Message $message, string $newBody): array
    {
        $message->body = $newBody;
        $message->save();

        broadcast(new MessageEdited($message));

        try {
            app(DragonflyPubSubService::class)->publishChatEvent(
                $message->room_id,
                'MessageEdited',
                ['id' => $message->id, 'room_id' => $message->room_id, 'message' => $newBody, 'is_edited' => true]
            );
        } catch (\Throwable $e) {}

        return $this->formatMessage($message);
    }

    /**
     * จัดโครงสร้างข้อมูล Message เพื่อส่งออกทาง API / WebSocket
     */
    public function formatMessage(Message $msg): array
    {
        $msg->loadMissing(['user', 'room']);
        $user = $msg->user;

        $attachments = [];
        if (!empty($msg->attachments)) {
            foreach ($msg->attachments as $att) {
                $path = $att['path'] ?? $att['file_path'] ?? '';
                $url = !empty($path) ? asset('storage/' . $path) : ($att['url'] ?? '#');
                $mime = $att['mime_type'] ?? '';

                $attachments[] = [
                    'original_name' => $att['original_name'] ?? basename($path),
                    'path'          => $path,
                    'url'           => $url,
                    'mime_type'     => $mime,
                    'size'          => $att['size'] ?? 0,
                    'is_image'      => str_starts_with($mime, 'image/'),
                ];
            }
        }

        $otherUserPivot = null;
        if ($msg->relationLoaded('room') && $msg->room && $msg->room->relationLoaded('users')) {
            $otherUserPivot = $msg->room->users->firstWhere('id', '!=', $msg->user_id);
        } elseif ($msg->room_id) {
            $otherUserPivot = \Illuminate\Support\Facades\DB::table('room_user')
                ->where('room_id', $msg->room_id)
                ->where('user_id', '!=', $msg->user_id)
                ->first();
        }

        $readAt = null;
        $readStatus = 'ส่งแล้ว';
        $isRead = false;

        $lastReadAtStr = $otherUserPivot?->pivot?->last_read_at ?? $otherUserPivot?->last_read_at ?? null;
        if ($lastReadAtStr && $msg->created_at) {
            $lastReadAt = \Carbon\Carbon::parse($lastReadAtStr);
            if ($lastReadAt->gte($msg->created_at)) {
                $isRead = true;
                $readAt = $lastReadAt->toISOString();
                $diffInSeconds = max(0, now()->diffInSeconds($lastReadAt));
                $diffInMinutes = max(0, now()->diffInMinutes($lastReadAt));
                $diffInHours   = max(0, now()->diffInHours($lastReadAt));

                if ($diffInSeconds < 60) {
                    $readStatus = 'เพิ่งอ่าน';
                } elseif ($diffInMinutes < 60) {
                    $readStatus = "เห็นเมื่อ {$diffInMinutes} นาทีที่แล้ว";
                } elseif ($diffInHours < 24) {
                    $readStatus = "เห็นเมื่อ {$diffInHours} ชม. ที่แล้ว";
                } else {
                    $readStatus = "เห็นเมื่อ " . $lastReadAt->format('d/m H:i');
                }
            }
        }

        return [
            'id'          => $msg->id,
            'room_id'     => $msg->room_id,
            'user_id'     => $msg->user_id,
            'message'     => $msg->body,
            'body'        => $msg->body,
            'is_edited'   => (bool) ($msg->is_edited ?? false),
            'is_read'     => $isRead,
            'read_at'     => $readAt,
            'read_status' => $readStatus,
            'user'        => [
                'id'        => $msg->user_id,
                'name'      => $user?->full_name ?? $user?->name ?? 'ผู้ใช้',
                'role'      => $user?->role ?? 'student',
                'photo'     => $user?->profile_photo ? asset('storage/' . $user->profile_photo) : null,
                'faculty'   => $user?->faculty,
                'department'=> $user?->department,
            ],
            'room'        => [
                'id'     => $msg->room_id,
                'job_id' => $msg->room?->job_id,
            ],
            'attachments' => $attachments,
            'created_at'  => $msg->created_at?->toISOString() ?? now()->toISOString(),
            'time_formatted' => $msg->created_at ? $msg->created_at->format('H:i') : date('H:i'),
        ];
    }

    /**
     * ค้นหาห้องแชทตามเกณฑ์ Job ID
     */
    private function findRoom(int $userId, int $jobId, int $defaultAdminId): ?Room
    {
        $roomQuery = Room::whereHas('users', fn($q) => $q->where('users.id', $userId));

        if ($jobId < 0) {
            $otherUserId = abs($jobId);
            $roomQuery->whereNull('job_id')
                ->whereHas('users', fn($q) => $q->where('users.id', $otherUserId));
        } elseif ($jobId === 0) {
            $roomQuery->whereNull('job_id')
                ->whereHas('users', fn($q) => $q->where('users.id', $defaultAdminId));
        } else {
            $roomQuery->where('job_id', $jobId);
        }

        return $roomQuery->first();
    }
}
