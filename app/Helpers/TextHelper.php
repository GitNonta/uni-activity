<?php

declare(strict_types=1);

namespace App\Helpers;

if (!function_exists('linkify')) {
    /**
     * แปลงลิงก์ URL ในข้อความ (http://, https://, www.) ให้เป็นแท็ก <a> ที่คลิกได้
     * มีการ Escaped ป้องกัน XSS และรองรับการตัดวรรคตอนท้าย URL
     */
    function linkify(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }

        // 1. ป้องกัน XSS โดยการแปลง entity ก่อน
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // 2. ตรวจจับ URLs (http, https, www)
        $pattern = '/(?xi)
            (?:
                (https?:\/\/[^\s<]+)
                |
                (?<![\/\w])(www\.[^\s<]+)
            )
        /u';

        $linked = preg_replace_callback($pattern, function (array $matches): string {
            $raw = $matches[0];
            $trailing = '';

            // ตัดเครื่องหมายวรรคตอนท้าย URL ที่อาจติดมา (เช่น .,;:!?)]} เป็นต้น)
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
        }, $escaped);

        return nl2br($linked ?? '');
    }
}
