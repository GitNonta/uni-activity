<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\Announcement;
use App\Models\JobListing;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LINE Messaging API Service
 * จัดการส่งข้อความผ่าน LINE Official Account
 */
class LineService
{
    private string $accessToken;
    private string $channelSecret;
    private string $apiBase = 'https://api.line.me/v2/bot';

    public function __construct()
    {
        $this->accessToken   = (string) config('services.line.channel_access_token', '');
        $this->channelSecret = (string) config('services.line.channel_secret', '');
    }

    /** ส่งข้อความหาผู้ใช้ 1 คน (Push Message) */
    public function pushMessage(string $lineUserId, array $messages): bool
    {
        if (empty($this->accessToken) || empty($lineUserId)) {
            return false;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(10)
                ->post("{$this->apiBase}/message/push", [
                    'to'       => $lineUserId,
                    'messages' => $messages,
                ]);

            if (!$response->successful()) {
                Log::warning('LINE push failed', [
                    'to'     => $lineUserId,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('LINE push exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /** ส่งข้อความหาผู้ใช้หลายคนพร้อมกัน (Multicast — สูงสุด 500 คน/ครั้ง) */
    public function multicast(array $lineUserIds, array $messages): bool
    {
        if (empty($this->accessToken) || empty($lineUserIds)) {
            return false;
        }

        // ส่งได้สูงสุด 500 คนต่อ request
        foreach (array_chunk($lineUserIds, 500) as $chunk) {
            try {
                $response = Http::withToken($this->accessToken)
                    ->timeout(15)
                    ->post("{$this->apiBase}/message/multicast", [
                        'to'       => array_values($chunk),
                        'messages' => $messages,
                    ]);

                if (!$response->successful()) {
                    Log::warning('LINE multicast failed', [
                        'count'  => count($chunk),
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('LINE multicast exception', ['error' => $e->getMessage()]);
            }
        }

        return true;
    }

    /** ส่ง notification ไปทุกคนที่ผูก LINE แล้ว */
    public function broadcastToLinkedUsers(array $messages): void
    {
        $lineIds = User::whereNotNull('line_user_id')
            ->where('line_notify_enabled', true)
            ->pluck('line_user_id')
            ->filter()
            ->values()
            ->toArray();

        if (empty($lineIds)) {
            return;
        }

        $this->multicast($lineIds, $messages);
    }

    /** สร้าง Flex Message สำหรับกิจกรรมใหม่ */
    public function buildActivityMessage(Activity $activity): array
    {
        $date     = $activity->activity_date
            ? \Carbon\Carbon::parse($activity->activity_date)->translatedFormat('j M Y') : '-';
        $imageUrl = $activity->image_path
            ? asset('storage/' . $activity->image_path)
            : null;

        $body = [
            'type'     => 'box',
            'layout'   => 'vertical',
            'spacing'  => 'sm',
            'contents' => [
                [
                    'type'   => 'text',
                    'text'   => '🎓 กิจกรรมใหม่!',
                    'weight' => 'bold',
                    'color'  => '#4f46e5',
                    'size'   => 'sm',
                ],
                [
                    'type'   => 'text',
                    'text'   => $activity->title,
                    'weight' => 'bold',
                    'size'   => 'lg',
                    'wrap'   => true,
                ],
                [
                    'type'     => 'box',
                    'layout'   => 'vertical',
                    'margin'   => 'sm',
                    'spacing'  => 'xs',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => "📅 วันที่: {$date}",
                            'size' => 'sm',
                            'color' => '#555555',
                        ],
                        [
                            'type' => 'text',
                            'text' => "📍 สถานที่: " . ($activity->location ?? 'ยังไม่ระบุ'),
                            'size' => 'sm',
                            'color' => '#555555',
                            'wrap'  => true,
                        ],
                        [
                            'type' => 'text',
                            'text' => "⏱ ชั่วโมง: " . ($activity->activity_hours ?? 0) . " ชั่วโมง",
                            'size' => 'sm',
                            'color' => '#555555',
                        ],
                    ],
                ],
            ],
        ];

        $hero = $imageUrl ? [
            'type'        => 'image',
            'url'         => $imageUrl,
            'size'        => 'full',
            'aspectRatio' => '20:13',
            'aspectMode'  => 'cover',
        ] : null;

        $footer = [
            'type'     => 'box',
            'layout'   => 'vertical',
            'contents' => [
                [
                    'type'   => 'button',
                    'style'  => 'primary',
                    'color'  => '#4f46e5',
                    'height' => 'sm',
                    'action' => [
                        'type'  => 'uri',
                        'label' => 'ดูรายละเอียด',
                        'uri'   => url("/activities/{$activity->id}"),
                    ],
                ],
            ],
        ];

        $container = [
            'type'   => 'bubble',
            'body'   => $body,
            'footer' => $footer,
        ];

        if ($hero) {
            $container['hero'] = $hero;
        }

        return [
            'type'     => 'flex',
            'altText'  => "กิจกรรมใหม่: {$activity->title}",
            'contents' => $container,
        ];
    }

    /** สร้าง Flex Message สำหรับประกาศงานใหม่ */
    public function buildJobMessage(JobListing $job): array
    {
        $type     = $job->job_type === 'parttime' ? 'งาน Part-time' : 'งานทั่วไป';
        $imageUrl = $job->image_path ? asset('storage/' . $job->image_path) : null;

        $body = [
            'type'     => 'box',
            'layout'   => 'vertical',
            'spacing'  => 'sm',
            'contents' => [
                [
                    'type'   => 'text',
                    'text'   => "💼 {$type}ใหม่!",
                    'weight' => 'bold',
                    'color'  => '#f97316',
                    'size'   => 'sm',
                ],
                [
                    'type'   => 'text',
                    'text'   => $job->title,
                    'weight' => 'bold',
                    'size'   => 'lg',
                    'wrap'   => true,
                ],
                [
                    'type'     => 'box',
                    'layout'   => 'vertical',
                    'margin'   => 'sm',
                    'spacing'  => 'xs',
                    'contents' => [
                        [
                            'type'  => 'text',
                            'text'  => "💰 ค่าตอบแทน: " . ($job->salary_range ?? 'ตามตกลง'),
                            'size'  => 'sm',
                            'color' => '#555555',
                        ],
                        [
                            'type'  => 'text',
                            'text'  => "📍 " . ($job->location ?? 'ยังไม่ระบุ'),
                            'size'  => 'sm',
                            'color' => '#555555',
                            'wrap'  => true,
                        ],
                    ],
                ],
            ],
        ];

        $footer = [
            'type'     => 'box',
            'layout'   => 'vertical',
            'contents' => [
                [
                    'type'   => 'button',
                    'style'  => 'primary',
                    'color'  => '#f97316',
                    'height' => 'sm',
                    'action' => [
                        'type'  => 'uri',
                        'label' => 'ดูรายละเอียดงาน',
                        'uri'   => url("/jobs/{$job->id}"),
                    ],
                ],
            ],
        ];

        $container = [
            'type'   => 'bubble',
            'body'   => $body,
            'footer' => $footer,
        ];

        if ($imageUrl) {
            $container['hero'] = [
                'type'        => 'image',
                'url'         => $imageUrl,
                'size'        => 'full',
                'aspectRatio' => '20:13',
                'aspectMode'  => 'cover',
            ];
        }

        return [
            'type'     => 'flex',
            'altText'  => "ประกาศงานใหม่: {$job->title}",
            'contents' => $container,
        ];
    }

    /** สร้าง Flex Message สำหรับประกาศข่าวสาร */
    public function buildAnnouncementMessage(Announcement $announcement): array
    {
        $imageUrl = $announcement->image_path ? asset('storage/' . $announcement->image_path) : null;

        $body = [
            'type'     => 'box',
            'layout'   => 'vertical',
            'spacing'  => 'sm',
            'contents' => [
                [
                    'type'   => 'text',
                    'text'   => '📣 ประกาศใหม่!',
                    'weight' => 'bold',
                    'color'  => '#0ea5e9',
                    'size'   => 'sm',
                ],
                [
                    'type'   => 'text',
                    'text'   => $announcement->title,
                    'weight' => 'bold',
                    'size'   => 'lg',
                    'wrap'   => true,
                ],
                [
                    'type'  => 'text',
                    'text'  => \Str::limit(strip_tags($announcement->content ?? ''), 100),
                    'size'  => 'sm',
                    'color' => '#666666',
                    'wrap'  => true,
                ],
            ],
        ];

        $footer = [
            'type'     => 'box',
            'layout'   => 'vertical',
            'contents' => [
                [
                    'type'   => 'button',
                    'style'  => 'primary',
                    'color'  => '#0ea5e9',
                    'height' => 'sm',
                    'action' => [
                        'type'  => 'uri',
                        'label' => 'อ่านประกาศ',
                        'uri'   => url("/announcements/{$announcement->id}"),
                    ],
                ],
            ],
        ];

        $container = [
            'type'   => 'bubble',
            'body'   => $body,
            'footer' => $footer,
        ];

        if ($imageUrl) {
            $container['hero'] = [
                'type'        => 'image',
                'url'         => $imageUrl,
                'size'        => 'full',
                'aspectRatio' => '20:13',
                'aspectMode'  => 'cover',
            ];
        }

        return [
            'type'     => 'flex',
            'altText'  => "ประกาศ: {$announcement->title}",
            'contents' => $container,
        ];
    }

    /** สร้างข้อความเตือนกิจกรรมพรุ่งนี้ */
    public function buildReminderMessage(Activity $activity, string $studentName): array
    {
        $date      = $activity->activity_date
            ? \Carbon\Carbon::parse($activity->activity_date)->translatedFormat('j M Y') : '-';
        $startTime = $activity->start_time
            ? \Carbon\Carbon::parse($activity->start_time)->format('H:i') : '';
        $endTime   = $activity->end_time
            ? \Carbon\Carbon::parse($activity->end_time)->format('H:i') : '';
        $timeText  = $startTime ? "{$startTime} - {$endTime} น." : '-';

        return [
            'type'    => 'flex',
            'altText' => "⏰ แจ้งเตือน: {$activity->title} พรุ่งนี้!",
            'contents' => [
                'type'   => 'bubble',
                'header' => [
                    'type'            => 'box',
                    'layout'          => 'vertical',
                    'backgroundColor' => '#4f46e5',
                    'paddingAll'      => '20px',
                    'contents'        => [
                        [
                            'type'   => 'text',
                            'text'   => '⏰ แจ้งเตือนกิจกรรม',
                            'color'  => '#ffffff',
                            'weight' => 'bold',
                            'size'   => 'lg',
                        ],
                    ],
                ],
                'body' => [
                    'type'     => 'box',
                    'layout'   => 'vertical',
                    'spacing'  => 'sm',
                    'contents' => [
                        [
                            'type' => 'text',
                            'text' => "สวัสดี {$studentName}!",
                            'weight' => 'bold',
                            'size'   => 'md',
                        ],
                        [
                            'type' => 'text',
                            'text' => 'กิจกรรมของคุณพรุ่งนี้:',
                            'size' => 'sm',
                            'color' => '#888888',
                        ],
                        [
                            'type'   => 'text',
                            'text'   => $activity->title,
                            'weight' => 'bold',
                            'size'   => 'lg',
                            'wrap'   => true,
                            'margin' => 'md',
                        ],
                        [
                            'type'     => 'box',
                            'layout'   => 'vertical',
                            'spacing'  => 'xs',
                            'margin'   => 'sm',
                            'contents' => [
                                [
                                    'type'  => 'text',
                                    'text'  => "📅 {$date}",
                                    'size'  => 'sm',
                                    'color' => '#555555',
                                ],
                                [
                                    'type'  => 'text',
                                    'text'  => "⏱ {$timeText}",
                                    'size'  => 'sm',
                                    'color' => '#555555',
                                ],
                                [
                                    'type'  => 'text',
                                    'text'  => "📍 " . ($activity->location ?? 'ยังไม่ระบุ'),
                                    'size'  => 'sm',
                                    'color' => '#555555',
                                    'wrap'  => true,
                                ],
                            ],
                        ],
                    ],
                ],
                'footer' => [
                    'type'     => 'box',
                    'layout'   => 'vertical',
                    'contents' => [
                        [
                            'type'   => 'button',
                            'style'  => 'primary',
                            'color'  => '#4f46e5',
                            'height' => 'sm',
                            'action' => [
                                'type'  => 'uri',
                                'label' => 'ดูรายละเอียด',
                                'uri'   => url("/activities/{$activity->id}"),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** ดึงข้อมูล Profile ผู้ใช้จาก LINE ด้วย Access Token */
    public function getLineProfile(string $accessToken): ?array
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get('https://api.line.me/v2/profile');

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::error('LINE getProfile exception', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /** แลก Authorization Code เป็น Access Token */
    public function exchangeToken(string $code, string $redirectUri): ?array
    {
        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post('https://api.line.me/oauth2/v2.1/token', [
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => $redirectUri,
                    'client_id'     => config('services.line.login_channel_id'),
                    'client_secret' => config('services.line.login_channel_secret'),
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('LINE token exchange failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('LINE token exchange exception', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
