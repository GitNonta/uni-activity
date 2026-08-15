<?php

declare(strict_types=1);

namespace App\Helpers;

if (!function_exists('format_description')) {
    /**
     * ระบบจัดรูปแบบข้อความคำอธิบายอัตโนมัติ (Smart Auto-Formatting)
     * - จัดระยะห่างพารากราฟ (Paragraphs) และความสูงบรรทัด (Line-height)
     * - จัดรูปแบบรายการหัวข้อย่อยอัตโนมัติ (Bullet Lists: -, *, • และ Numbered Lists: 1., 2., 1))
     * - แปลงลิงก์ URL (http://, https://, www.) เป็นลิงก์ที่คลิกได้ปลอดภัย
     * - แปลงแฮชแท็ก (#hashtag, #จิตอาสา) เป็นปุ่มค้นหาแบบ Badge อัตโนมัติ
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

        // 3. ตรวจจับและแปลง แฮชแท็ก #hashtag (ทั้งภาษาไทยและอังกฤษ) ก่อนใส่ style tags
        $hashtagPattern = '/(?<![\w#&;])#([a-zA-Z0-9_\x{0E00}-\x{0E7F}]+)/u';
        $escaped = preg_replace_callback($hashtagPattern, function (array $matches): string {
            $tag = $matches[1];
            $searchUrl = url('/activities') . '?search=' . urlencode('#' . $tag);
            return '<a href="' . $searchUrl . '" class="hashtag-badge" style="color:#ea580c;background:#fff7ed;padding:2px 8px;border-radius:6px;font-weight:600;text-decoration:none;border:1px solid #fed7aa;display:inline-block;margin:1px 2px;font-size:0.85em;transition:all 0.15s ease;" onclick="event.stopPropagation();">#' . $tag . '</a>';
        }, $escaped) ?? $escaped;

        // 4. แปลง Markdown พื้นฐาน (ตัวหนา, ตัวเอียง, โค้ด)
        // **bold** -> <strong>
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong style="color:#0f172a;font-weight:700;">$1</strong>', $escaped) ?? $escaped;
        // *italic* -> <em>
        $escaped = preg_replace('/(?<!\*)\*([^*]+?)\*(?!\*)/s', '<em>$1</em>', $escaped) ?? $escaped;
        // `code` -> <code>
        $escaped = preg_replace('/`([^`]+?)`/', '<code style="background:#f1f5f9;color:#ea580c;padding:2px 6px;border-radius:4px;font-size:0.88em;font-family:monospace;">$1</code>', $escaped) ?? $escaped;

        // 5. แปลงป้ายแจ้งเตือนอัตโนมัติ [สำคัญ], [ด่วน], [ประกาศ]
        $escaped = preg_replace('/\[(สำคัญ|ด่วน|URGENT|IMPORTANT)\]/iu', '<span class="badge badge-red" style="font-size:0.75rem;vertical-align:middle;margin-right:4px;">$1</span>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\[(หมายเหตุ|NOTE|INFO)\]/iu', '<span class="badge badge-orange" style="font-size:0.75rem;vertical-align:middle;margin-right:4px;">$1</span>', $escaped) ?? $escaped;

        // 6. ตรวจจับและแปลง URLs (http://, https://, www.)
        $urlPattern = '/(?xi)
            (?:
                (https?:\/\/[^\s<]+)
                |
                (?<![\/\w])(www\.[^\s<]+)
            )
        /u';

        $escaped = preg_replace_callback($urlPattern, function (array $matches): string {
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

            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" class="linkified-url" style="color:#ea580c;text-decoration:underline;word-break:break-word;font-weight:500;" onclick="event.stopPropagation();">' . $raw . '</a>' . $trailing;
        }, $escaped) ?? $escaped;

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

        return '<div class="desc-content" style="font-size:0.92rem;line-height:1.75;color:#334155;word-break:break-word;">' . implode("\n", $outputBlocks) . '</div>';
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

        $pattern = '/(?<![\w#&;])#([a-zA-Z0-9_\x{0E00}-\x{0E7F}]+)/u';
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
