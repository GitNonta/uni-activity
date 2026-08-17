<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Message
 */
class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sender = $this->user;
        $room = $this->room;

        $attachments = [];
        if (!empty($this->attachments)) {
            foreach ($this->attachments as $att) {
                $attachments[] = [
                    'original_name' => $att['original_name'] ?? 'attachment',
                    'file_path'     => $att['file_path'] ?? '',
                    'url'           => !empty($att['file_path']) ? asset('storage/' . $att['file_path']) : ($att['url'] ?? ''),
                    'mime_type'     => $att['mime_type'] ?? 'application/octet-stream',
                    'size'          => $att['size'] ?? 0,
                    'is_image'      => str_starts_with($att['mime_type'] ?? '', 'image/'),
                ];
            }
        }

        return [
            'id'          => $this->id,
            'room_id'     => $this->room_id,
            'user_id'     => $this->user_id,
            'message'     => $this->body,
            'body'        => $this->body,
            'is_edited'   => (bool) ($this->is_edited ?? false),
            'attachments' => $attachments,
            'created_at'  => $this->created_at?->toISOString() ?? now()->toISOString(),
            'time_formatted' => $this->created_at ? $this->created_at->format('H:i') : date('H:i'),
            'user'        => [
                'id'        => $this->user_id,
                'name'      => $sender?->full_name ?? $sender?->name ?? 'ผู้ใช้',
                'role'      => $sender?->role ?? 'student',
                'photo'     => $sender?->profile_photo ? asset('storage/' . $sender->profile_photo) : null,
                'faculty'   => $sender?->faculty,
                'department'=> $sender?->department,
            ],
            'room'        => [
                'id'     => $this->room_id,
                'job_id' => $room?->job_id,
                'type'   => $room?->type ?? 'direct',
            ],
        ];
    }
}
