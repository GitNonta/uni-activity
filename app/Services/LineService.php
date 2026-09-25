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
use Illuminate\Support\Facades\Cache;
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

    /** Get redirect-friendly URL for notifications */
    private function getRedirectUrl(string $path): string
    {
        $redirectBase = config('services.line.redirect_base_url') ?: 'https://gitnonta.github.io/uni-activity';
        $cleanPath    = ltrim($path, '/');
        return "{$redirectBase}?path={$cleanPath}";
    }

    /** Ensure image URL uses HTTPS for LINE Flex Message requirement */
    private function getValidHttpsImageUrl(?string $imagePath): ?string
    {
        if (!$imagePath) {
            return null;
        }

        // 1. Check docs/active_url.json first (auto-synced tunnel URL from Termux / GitHub)
        $activeJson = base_path('docs/active_url.json');
        if (file_exists($activeJson)) {
            $data = json_decode((string) @file_get_contents($activeJson), true);
            if (!empty($data['url']) && str_starts_with($data['url'], 'https://')) {
                return rtrim($data['url'], '/') . '/storage/' . ltrim($imagePath, '/');
            }
        }

        $url = asset('storage/' . $imagePath);
        if (str_starts_with($url, 'https://')) {
            return $url;
        }

        // If app URL is not HTTPS, check if HTTPS_HOST or proxy is available
        $httpsHost = config('app.https_url');
        if ($httpsHost && str_starts_with($httpsHost, 'https://')) {
            return rtrim($httpsHost, '/') . '/storage/' . ltrim($imagePath, '/');
        }

        // Return null to avoid LINE rejecting the entire Flex Message on non-HTTPS development URLs
        return null;
    }

    /** ส่งข้อความหาผู้ใช้ 1 คน (Push Message) */
    public function pushMessage(string $lineUserId, array $messages): bool
    {
        if (empty($this->accessToken) || empty($lineUserId)) {
            return false;
        }

        try {
            $response = Http::withOptions(['proxy' => config('services.line.forward_proxy')])
                ->withToken($this->accessToken)
                ->timeout(15)
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
                $response = Http::withOptions(['proxy' => config('services.line.forward_proxy')])
                    ->withToken($this->accessToken)
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
            ->unique()
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
        $imageUrl = $this->getValidHttpsImageUrl($activity->image_path);

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
                        'uri'   => $this->getRedirectUrl("/activities/{$activity->id}"),
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
        $imageUrl = $this->getValidHttpsImageUrl($job->image_path);

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
                        'uri'   => $this->getRedirectUrl("/jobs/{$job->id}"),
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
        $imageUrl = $this->getValidHttpsImageUrl($announcement->image_path);

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
                        'uri'   => $this->getRedirectUrl("/announcements/{$announcement->id}"),
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
    /** สร้างข้อความเตือนกิจกรรม (รองรับทั้ง 24 ชม. และ 2 ชม. ก่อนเริ่ม) */
    public function buildReminderMessage(Activity $activity, string $studentName, string $window = '24h'): array
    {
        $date      = $activity->activity_date
            ? \Carbon\Carbon::parse($activity->activity_date)->translatedFormat('j M Y') : '-';
        $startTime = $activity->start_time
            ? \Carbon\Carbon::parse($activity->start_time)->format('H:i') : '';
        $endTime   = $activity->end_time
            ? \Carbon\Carbon::parse($activity->end_time)->format('H:i') : '';
        $timeText  = $startTime ? "{$startTime} - {$endTime} น." : '-';

        $isTwoHours = $window === '2h';
        $headerColor = $isTwoHours ? '#ea580c' : '#4f46e5';
        $headerTitle = $isTwoHours ? '⏰ กิจกรรมจะเริ่มใน 2 ชม.!' : '⏰ แจ้งเตือนกิจกรรมพรุ่งนี้';
        $subtitle    = $isTwoHours ? 'กิจกรรมของคุณกำลังจะเริ่มในอีก 2 ชั่วโมง:' : 'กิจกรรมของคุณพรุ่งนี้:';
        $altText     = $isTwoHours ? "⏰ แจ้งเตือน: อีก 2 ชั่วโมง {$activity->title} จะเริ่มแล้ว!" : "⏰ แจ้งเตือน: {$activity->title} พรุ่งนี้!";

        return [
            'type'    => 'flex',
            'altText' => $altText,
            'contents' => [
                'type'   => 'bubble',
                'header' => [
                    'type'            => 'box',
                    'layout'          => 'vertical',
                    'backgroundColor' => $headerColor,
                    'paddingAll'      => '20px',
                    'contents'        => [
                        [
                            'type'   => 'text',
                            'text'   => $headerTitle,
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
                            'text' => $subtitle,
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
                            'color'  => $headerColor,
                            'height' => 'sm',
                            'action' => [
                                'type'  => 'uri',
                                'label' => 'ดูรายละเอียด / เช็คอิน',
                                'uri'   => $this->getRedirectUrl("/activities/{$activity->id}"),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** สร้าง Flex Message สำหรับประกาศด่วน / บรอดแคสต์ของกิจกรรม (ย้ายสถานที่ / เลื่อนเวลา) */
    public function buildActivityBroadcastMessage(Activity $activity, string $title, string $message, string $type = 'general'): array
    {
        $badgeConfig = match ($type) {
            'venue_change' => ['color' => '#dc2626', 'label' => '📍 แจ้งย้ายสถานที่จัดงาน'],
            'reschedule'   => ['color' => '#d97706', 'label' => '⏰ แจ้งเปลี่ยนแปลงเวลา'],
            'urgent'       => ['color' => '#b91c1c', 'label' => '🚨 ประกาศด่วน'],
            default        => ['color' => '#0284c7', 'label' => '📢 ข่าวสารถึงผู้เข้าร่วม'],
        };

        $date      = $activity->activity_date
            ? \Carbon\Carbon::parse($activity->activity_date)->translatedFormat('j M Y') : '-';
        $startTime = $activity->start_time
            ? \Carbon\Carbon::parse($activity->start_time)->format('H:i') : '';
        $endTime   = $activity->end_time
            ? \Carbon\Carbon::parse($activity->end_time)->format('H:i') : '';
        $timeText  = $startTime ? "{$startTime} - {$endTime} น." : '-';

        return [
            'type'    => 'flex',
            'altText' => "{$badgeConfig['label']}: {$title}",
            'contents' => [
                'type'   => 'bubble',
                'header' => [
                    'type'            => 'box',
                    'layout'          => 'vertical',
                    'backgroundColor' => $badgeConfig['color'],
                    'paddingAll'      => '16px',
                    'contents'        => [
                        [
                            'type'   => 'text',
                            'text'   => $badgeConfig['label'],
                            'color'  => '#ffffff',
                            'weight' => 'bold',
                            'size'   => 'sm',
                        ],
                        [
                            'type'   => 'text',
                            'text'   => $title,
                            'color'  => '#ffffff',
                            'weight' => 'bold',
                            'size'   => 'lg',
                            'wrap'   => true,
                            'margin' => 'xs',
                        ],
                    ],
                ],
                'body' => [
                    'type'     => 'box',
                    'layout'   => 'vertical',
                    'spacing'  => 'md',
                    'contents' => [
                        [
                            'type'   => 'text',
                            'text'   => $message,
                            'size'   => 'sm',
                            'color'  => '#333333',
                            'wrap'   => true,
                        ],
                        [
                            'type'     => 'box',
                            'layout'   => 'vertical',
                            'spacing'  => 'xs',
                            'paddingAll' => '10px',
                            'backgroundColor' => '#f8fafc',
                            'cornerRadius'   => '8px',
                            'contents' => [
                                [
                                    'type'   => 'text',
                                    'text'   => "กิจกรรม: {$activity->title}",
                                    'size'   => 'xs',
                                    'weight' => 'bold',
                                    'color'  => '#0f172a',
                                    'wrap'   => true,
                                ],
                                [
                                    'type'  => 'text',
                                    'text'  => "📅 วันที่: {$date} ({$timeText})",
                                    'size'  => 'xs',
                                    'color' => '#64748b',
                                ],
                                [
                                    'type'  => 'text',
                                    'text'  => "📍 สถานที่: " . ($activity->location ?? 'ยังไม่ระบุ'),
                                    'size'  => 'xs',
                                    'color' => '#64748b',
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
                            'color'  => $badgeConfig['color'],
                            'height' => 'sm',
                            'action' => [
                                'type'  => 'uri',
                                'label' => 'เปิดดูกิจกรรมในระบบ',
                                'uri'   => $this->getRedirectUrl("/activities/{$activity->id}"),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * สร้าง Flex Message แจ้งเตือนนักศึกษาที่ยังขาดชั่วโมงกิจกรรมสำหรับสำเร็จการศึกษา
     *
     * @param array<string, mixed> $auditResult
     * @return array<string, mixed>
     */
    public function buildGraduationDeficitMessage(User $student, array $auditResult): array
    {
        $totalHours = number_format((float) ($auditResult['total_hours'] ?? 0), 1);
        $minHours   = number_format((float) ($auditResult['min_total_hours'] ?? 100), 1);
        $missingItems = $auditResult['missing_requirements'] ?? [];

        $missingBoxes = [];
        foreach (array_slice($missingItems, 0, 5) as $item) {
            $missingBoxes[] = [
                'type'     => 'box',
                'layout'   => 'horizontal',
                'contents' => [
                    [
                        'type'  => 'text',
                        'text'  => '• ' . $item,
                        'size'  => 'xs',
                        'color' => '#dc2626',
                        'wrap'  => true,
                    ],
                ],
            ];
        }

        return [
            'type'     => 'flex',
            'altText'  => "แจ้งเตือนการสำเร็จการศึกษา: ชั่วโมงกิจกรรมของคุณยังไม่ครบตามเกณฑ์ ({$totalHours}/{$minHours} ชม.)",
            'contents' => [
                'type'   => 'bubble',
                'header' => [
                    'type'            => 'box',
                    'layout'          => 'vertical',
                    'backgroundColor' => '#fee2e2',
                    'contents'        => [
                        [
                            'type'   => 'text',
                            'text'   => 'แจ้งเตือนการสำเร็จการศึกษา',
                            'weight' => 'bold',
                            'color'  => '#b91c1c',
                            'size'   => 'md',
                        ],
                        [
                            'type'   => 'text',
                            'text'   => 'ชั่วโมงกิจกรรมยังไม่ครบตามเกณฑ์หลักสูตร',
                            'size'   => 'xs',
                            'color'  => '#991b1b',
                            'margin' => 'xs',
                        ],
                    ],
                ],
                'body' => [
                    'type'     => 'box',
                    'layout'   => 'vertical',
                    'contents' => [
                        [
                            'type'   => 'text',
                            'text'   => "สวัสดีคุณ " . ($student->full_name ?? 'นักศึกษา'),
                            'weight' => 'bold',
                            'size'   => 'sm',
                            'color'  => '#1e293b',
                        ],
                        [
                            'type'   => 'text',
                            'text'   => "ระบบตรวจสอบพบว่าคุณมีชั่วโมงกิจกรรมสะสม {$totalHours} / {$minHours} ชม. และยังไม่ผ่านเกณฑ์การสำเร็จการศึกษาดังนี้:",
                            'size'   => 'xs',
                            'color'  => '#475569',
                            'wrap'   => true,
                            'margin' => 'sm',
                        ],
                        [
                            'type'     => 'box',
                            'layout'   => 'vertical',
                            'margin'   => 'md',
                            'spacing'  => 'sm',
                            'contents' => $missingBoxes,
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
                                'label' => 'ตรวจสอบสถานะ & กิจกรรม',
                                'uri'   => $this->getRedirectUrl('/student/graduation-status'),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** ดึงข้อมูล Profile ผู้ใช้จาก LINE ด้วย Access Token (cached 5 min) */
    public function getLineProfile(string $accessToken): ?array
    {
        try {
            // Cache LINE profile for 5 min to reduce API calls
            return Cache::remember(
                'api:line:profile:' . md5($accessToken),
                300,
                function () use ($accessToken) {
                    $response = Http::withOptions(['proxy' => config('services.line.forward_proxy')])
                        ->withToken($accessToken)
                        ->timeout(10)
                        ->get('https://api.line.me/v2/profile');
                    return $response->successful() ? $response->json() : null;
                }
            );
        } catch (\Throwable $e) {
            Log::error('LINE getProfile exception', ['error' => $e->getMessage()]);
        }
        return null;
    }
    public function exchangeToken(string $code, string $redirectUri): ?array
    {
        try {
            $response = Http::withOptions(['proxy' => config('services.line.forward_proxy')])
                ->asForm()
                ->timeout(15)
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
