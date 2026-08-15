<?php

declare(strict_types=1);

namespace App\Helpers;

if (!function_exists('format_description')) {
    /**
     * ระบบจัดรูปแบบข้อความคำอธิบายอัตโนมัติ (Smart Tokenized Auto-Formatting)
     * - ป้องกันการเกิด Nested Links ซ้ำซ้อน 100% ด้วยระบบ Tokenization
     * - จัดระยะห่างพารากราฟ (Paragraphs) และความสูงบรรทัด (Line-height)
     * - จัดรูปแบบรายการหัวข้อย่อยอัตโนมัติ (Bullet Lists: -, *, • และ Numbered Lists: 1., 2., 1))
     * - แปลงลิงก์ URL (http://, https://, www.) เป็นลิงก์ที่คลิกได้ปลอดภัย
     * - แปลงแฮชแท็ก (#hashtag, #จิตอาสา) เป็นปุ่มค้นหาแบบ Badge อัจฉริยะตามบริบทหน้า (Activities/Jobs/Announcements)
     * - รองรับตัวหนา (**bold**), ตัวเอียง (*italic*), และโค้ด/ไฮไลต์ (`code`)
     * - แปลงป้ายแจ้งเตือน เช่น [สำคัญ], [ด่วน], [หมายเหตุ]
     */
    function format_description(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }

        // 1. ปรับบรรทัดและตัดช่องว่างส่วนเกิน
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+$/m', '', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        $text = trim($text);

        // 2. ป้องกัน XSS
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $tokens = [];
        $tokenIdx = 0;

        // 3. แปลง URLs และเก็บเป็น Token ก่อน เพื่อป้องกันไม่ให้เกิด Regex Collision กับแท็ก HTML อื่น
        $urlPattern = '/(?xi)
            (?:
                (https?:\/\/[^\s<]+)
                |
                (?<![\/\w])(www\.[^\s<]+)
            )
        /u';

        $escaped = preg_replace_callback($urlPattern, function (array $matches) use (&$tokens, &$tokenIdx): string {
            $raw = $matches[0];
            $trailing = '';

            while ($raw !== '' && in_array(mb_substr($raw, -1), ['.', ',', ';', ':', '!', '?', ')', ']', '}', '"', '\''], true)) {
                $trailing = mb_substr($raw, -1) . $trailing;
                $raw = mb_substr($raw, 0, -1);
            }

            if ($raw === '') {
                return $trailing;
            }

            $url = (str_starts_with(strtolower($raw), 'http://') || str_starts_with(strtolower($raw), 'https://'))
                ? $raw
                : 'https://' . $raw;

            $tokenKey = '___URL_TOKEN_' . ($tokenIdx++) . '___';
            $tokens[$tokenKey] = '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" class="linkified-url" onclick="event.stopPropagation();">' . $raw . '</a>';
            return $tokenKey . $trailing;
        }, $escaped) ?? $escaped;

        // 4. แปลง แฮชแท็ก #hashtag และเก็บเป็น Token (ยกเว้นรหัสสี Hex)
        $hashtagPattern = '/(?<![\w#&;:=])#(?!(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\b)([a-zA-Z0-9_\x{0E00}-\x{0E7F}]+)/u';
        $escaped = preg_replace_callback($hashtagPattern, function (array $matches) use (&$tokens, &$tokenIdx): string {
            $tag = $matches[1];

            // ลิงก์ไปยังส่วนที่ตรงกับบริบท (Jobs / Announcements / Activities)
            $basePath = '/activities';
            if (request()->is('jobs*') || request()->is('*/jobs*')) {
                $basePath = request()->is('admin/*') ? '/admin/jobs' : '/jobs';
            } elseif (request()->is('announcements*') || request()->is('*/announcements*')) {
                $basePath = request()->is('admin/*') ? '/admin/announcements' : '/announcements';
            } elseif (request()->is('admin/*')) {
                $basePath = '/admin/activities';
            }

            $searchUrl = url($basePath) . '?search=' . urlencode('#' . $tag);
            $tokenKey = '___TAG_TOKEN_' . ($tokenIdx++) . '___';
            $tokens[$tokenKey] = '<a href="' . $searchUrl . '" class="hashtag-badge" onclick="event.stopPropagation();">#' . $tag . '</a>';
            return $tokenKey;
        }, $escaped) ?? $escaped;

        // 5. แปลง Markdown พื้นฐาน (ตัวหนา, ตัวเอียง, โค้ด)
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/(?<!\*)\*([^*]+?)\*(?!\*)/s', '<em>$1</em>', $escaped) ?? $escaped;
        $escaped = preg_replace('/`([^`]+?)`/', '<code>$1</code>', $escaped) ?? $escaped;

        // 6. แปลงป้ายแจ้งเตือนอัตโนมัติ [สำคัญ], [ด่วน], [ประกาศ]
        $escaped = preg_replace('/\[(สำคัญ|ด่วน|URGENT|IMPORTANT)\]/iu', '<span class="badge badge-red" style="font-size:0.75rem;vertical-align:middle;margin-right:4px;">$1</span>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\[(หมายเหตุ|NOTE|INFO)\]/iu', '<span class="badge badge-orange" style="font-size:0.75rem;vertical-align:middle;margin-right:4px;">$1</span>', $escaped) ?? $escaped;

        // 7. แยก Paragraphs และจัดการ Bullet / Numbered Lists
        $blocks = explode("\n\n", $escaped);
        $outputBlocks = [];

        foreach ($blocks as $block) {
            $lines = explode("\n", trim($block));
            $isBulletList = true;
            $isNumberList = true;

            foreach ($lines as $line) {
                $tLine = trim($line);
                if ($tLine === '') continue;

                if (!preg_match('/^[-*•]\s+/u', $tLine)) {
                    $isBulletList = false;
                }
                if (!preg_match('/^\d+[\.\)]\s+/u', $tLine)) {
                    $isNumberList = false;
                }
            }

            if ($isBulletList && count($lines) > 0) {
                $listHtml = '<ul style="margin:0.5rem 0;padding-left:1.4rem;list-style-type:disc;line-height:1.7;">';
                foreach ($lines as $l) {
                    $item = preg_replace('/^[-*•]\s+/u', '', trim($l));
                    if ($item !== '') {
                        $listHtml .= '<li style="margin-bottom:0.25rem;">' . $item . '</li>';
                    }
                }
                $listHtml .= '</ul>';
                $outputBlocks[] = $listHtml;
            } elseif ($isNumberList && count($lines) > 0) {
                $listHtml = '<ol style="margin:0.5rem 0;padding-left:1.4rem;list-style-type:decimal;line-height:1.7;">';
                foreach ($lines as $l) {
                    $item = preg_replace('/^\d+[\.\)]\s+/u', '', trim($l));
                    if ($item !== '') {
                        $listHtml .= '<li style="margin-bottom:0.25rem;">' . $item . '</li>';
                    }
                }
                $listHtml .= '</ol>';
                $outputBlocks[] = $listHtml;
            } else {
                // พารากราฟธรรมดาที่มีการเว้นบรรทัด
                $formattedLines = implode("<br>\n", $lines);
                $outputBlocks[] = '<p style="margin-bottom:0.75rem;line-height:1.75;color:#334155;word-break:break-word;">' . $formattedLines . '</p>';
            }
        }

        $finalHtml = '<div class="desc-content">' . implode("\n", $outputBlocks) . '</div>';

        // 8. คืนค่า Tokens ทั้งหมดลงใน HTML ที่สร้างเสร็จแล้ว (strtr ปลอดภัยและเร็วที่สุด)
        if (!empty($tokens)) {
            $finalHtml = strtr($finalHtml, $tokens);
        }

        return $finalHtml;
    }
}

if (!function_exists('extract_hashtags')) {
    /**
     * ดึงรายการแฮชแท็กทั้งหมดจากข้อความ
     * @return string[] อาร์เรย์ของชื่อแฮชแท็ก เช่น ['จิตอาสา', 'KKU2026']
     */
    function extract_hashtags(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $pattern = '/(?<![\w#&;:=])#(?!(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\b)([a-zA-Z0-9_\x{0E00}-\x{0E7F}]+)/u';
        if (preg_match_all($pattern, $text, $matches)) {
            return array_values(array_unique($matches[1]));
        }

        return [];
    }
}

if (!function_exists('linkify')) {
    /**
     * Alias สำหรับ format_description
     */
    function linkify(?string $text): string
    {
        return format_description($text);
    }
}
