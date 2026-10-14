<?php

// ── [tag:2026-10-14-translation] Post Translation Service ──
// แปลเนื้อหาโพสต์ (ประกาศ/ข่าวสาร & ประกาศงาน) จาก TH → EN โดยอัตโนมัติ
// แหล่งภายนอก: MyMemory Translation API (ฟรี, ไม่ต้องใช้ API key)
// พร้อม timeout / retry / fallback / cache-in-DB (เขียนลงคอลัมน์ *_en)

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PostTranslationService
{
    protected const ENDPOINT = 'https://api.mymemory.translated.net/get';

    /** ไม่เกิน ~450 ตัวอักษรต่อครั้ง (จำกัดของ MyMemory) */
    protected const CHUNK_SIZE = 420;

    /** แปลข้อความภาษาไทยเป็นอังกฤษ พร้อม chunk และรวมกลับ */
    public function translateThaiToEnglish(string $text): ?string
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        // ข้อความที่ไม่ได้เป็นภาษาไทยอยู่แล้ว → คืนค่าเดิม
        if (!preg_match('/[\x{0E00}-\x{0E7F}]/u', $text)) {
            return $text;
        }

        $chunks = $this->splitIntoChunks($text);
        $parts  = [];

        foreach ($chunks as $chunk) {
            $translated = $this->callApi($chunk);

            if ($translated === null) {
                return null; // แปลบาง chunk ไม่สำเร็จ → fallback ทั้งก้อน (จะโยนเป็นไทยไปตามนั้น)
            }

            $parts[] = $translated;
        }

        return implode(' ', $parts);
    }

    /** แปลหลายฟิลด์พร้อมกัน — คืน map: key => EN (เฉพาะที่แปลผ่าน) */
    public function translateFields(array $fields): array
    {
        $out = [];

        foreach ($fields as $key => $text) {
            if (!is_string($text) || trim($text) === '') {
                continue;
            }

            $translated = $this->translateThaiToEnglish($text);

            if ($translated !== null && trim($translated) !== '') {
                $out[$key . '_en'] = $translated;
            }
        }

        return $out;
    }

    protected function callApi(string $chunk): ?string
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $resp = Http::withOptions(['verify' => false])
                    ->timeout(8)
                    ->get(self::ENDPOINT, [
                        'q'        => $chunk,
                        'langpair' => 'th|en',
                    ]);

                if (!$resp->successful()) {
                    continue;
                }

                $data = $resp->json();

                if (is_array($data) && ($data['responseStatus'] ?? 500) === 200 && is_array($data['responseData'] ?? null)) {
                    $t = $data['responseData']['translatedText'] ?? null;

                    if (is_string($t) && $t !== '' && stripos($t, 'invalid language') === false) {
                        return trim($t);
                    }
                }
            } catch (\Throwable $e) {
                Log::info('PostTranslation error: ' . $e->getMessage());
            }

            usleep(500_000); // พักกัน rate-limit แล้วลองใหม่
        }

        return null;
    }

    /** แบ่งข้อความเป็น chunk โดยรักษาขอบเขตประโยค (ไม่ตัดกลางคำ) */
    protected function splitIntoChunks(string $text): array
    {
        if (mb_strlen($text) <= self::CHUNK_SIZE) {
            return [$text];
        }

        $chunks = [];
        $rest   = $text;

        while (mb_strlen($rest) > self::CHUNK_SIZE) {
            $cut = $this->findBreak($rest, self::CHUNK_SIZE);
            $chunks[] = trim(mb_substr($rest, 0, $cut));
            $rest = ltrim(mb_substr($rest, $cut));
        }

        if ($rest !== '') {
            $chunks[] = $rest;
        }

        return $chunks;
    }

    protected function findBreak(string $text, int $max): int
    {
        $window = mb_substr($text, 0, $max);

        foreach (["\n", '. ', ' '] as $sep) {
            $pos = mb_strrpos($window, $sep);

            if ($pos !== false && $pos > 40) {
                return $pos + mb_strlen($sep);
            }
        }

        return $max;
    }
}
