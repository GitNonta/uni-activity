<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Client ของ face_api (ncnn/C++ service) สำหรับ "สกัดใบหน้า"
 * — SCRFD detect + ArcFace align + MobileFaceNet 512D ในคำขอเดียว
 *
 * ตั้งค่าได้ 2 แบบใน .env:
 *   FACE_API_URL=http://192.168.1.140:8010                     (โหนดเดียว)
 *   FACE_API_URLS=http://192.168.1.140:8010,http://127.0.0.1:8010 (หลายโหนด + failover)
 * ว่างทั้งคู่ = ปิด (ระบบกลับไปใช้ AI cluster เดิม)
 *
 * endpoint: POST /extract, POST /verify, GET /health
 */
class FaceExtractApiService
{
    /** @var array<int, string> */
    private array $urls;
    private string $key;
    private int $timeout;
    private int $healthTimeout = 3;

    public function __construct()
    {
        $list = (array) config('services.face_api.urls', []);
        if (empty($list)) {
            $single = trim((string) config('services.face_api.url', ''));
            if ($single !== '') {
                $list = [$single];
            }
        }

        $this->urls = [];
        foreach ($list as $candidate) {
            $candidate = rtrim(trim((string) $candidate), '/');
            if ($candidate !== '' && !in_array($candidate, $this->urls, true)) {
                $this->urls[] = $candidate;
            }
        }

        $this->key     = (string) config('services.face_api.key', '');
        $this->timeout = (int) config('services.face_api.timeout', 15);
    }

    public function isEnabled(): bool
    {
        return !empty($this->urls);
    }

    /**
     * @return array<int, string>
     */
    public function urls(): array
    {
        return $this->urls;
    }

    public function url(): string
    {
        return $this->urls[0] ?? '';
    }

    /**
     * สกัด embedding จากภาพ (bytes) — คืน payload ของ face_api ตามเดิม
     * โดยเติม client_ms / node ให้ผู้เรียกใช้บันทึก log ได้
     *
     * @return array<string, mixed>
     * @throws RuntimeException เมื่อทุกโหนดเชื่อมต่อไม่ได้หรือ HTTP ไม่สำเร็จ
     */
    public function extract(string $imageBytes, string $filename = 'photo.jpg', int $faces = 1): array
    {
        return $this->withImage(
            $imageBytes,
            $filename,
            '/extract' . ($faces > 1 ? '?faces=' . $faces : ''),
            [],
            'extract'
        );
    }

    /**
     * ยืนยันใบหน้า (เทียบ selfie กับ vector ที่ลงทะเบียนไว้) — สัญญาเดียวกับ
     * ai_service/server.py:/verify (is_match = face_match && liveness_passed)
     *
     * @param array<int, float> $knownEmbedding
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    public function verify(string $imageBytes, array $knownEmbedding, bool $checkLiveness = true, string $filename = 'frame.jpg'): array
    {
        if (count($knownEmbedding) !== 512) {
            throw new RuntimeException('known embedding must have 512 dimensions, got ' . count($knownEmbedding));
        }

        return $this->withImage(
            $imageBytes,
            $filename,
            '/verify',
            [
                'known_embedding' => json_encode(array_values($knownEmbedding)),
                'check_liveness'  => $checkLiveness ? 'true' : 'false',
            ],
            'verify'
        );
    }

    /**
     * ส่งภาพไปยังโหนดตามลำดับ พร้อม failover
     * ถ้าโหนดตอบ "decode failed" (เช่น Progressive JPEG/HEIC ที่ stb_image อ่านไม่ได้)
     * จะแปลงเป็น JPEG พื้นฐานด้วย GD แล้วลองใหม่ทั้งชุดหนึ่งครั้ง
     *
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private function withImage(string $imageBytes, string $filename, string $path, array $fields, string $action): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('FACE_API_URL is not configured');
        }
        if ($imageBytes === '') {
            throw new RuntimeException('empty image payload');
        }

        $converted = false;
        $prepared  = $this->toJpegIfUnsupported($imageBytes);
        if ($prepared !== $imageBytes) {
            $converted = true;
        }

        try {
            return $this->failover($prepared, $filename, $path, $fields, $action);
        } catch (RuntimeException $e) {
            if ($converted || !str_contains($e->getMessage(), 'decode failed')) {
                throw $e;
            }
            $reencoded = $this->reencodeJpeg($imageBytes);
            if ($reencoded === null) {
                throw $e;
            }

            return $this->failover($reencoded, $filename, $path, $fields, $action);
        }
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private function failover(string $imageBytes, string $filename, string $path, array $fields, string $action): array
    {
        $errors = [];

        foreach ($this->orderedUrls() as $url) {
            $request = Http::timeout($this->timeout)->acceptJson();
            if ($this->key !== '') {
                $request = $request->withHeaders(['X-API-Key' => $this->key]);
            }

            $start = microtime(true);
            try {
                $response = $request->attach('image', $imageBytes, $filename)->post($url . $path, $fields);
            } catch (Throwable $e) {
                $errors[] = $url . ' -> ' . $e->getMessage();
                continue;
            }
            $clientMs = (int) round((microtime(true) - $start) * 1000);

            if (!$response->successful()) {
                $errors[] = $url . ' HTTP ' . $response->status() . ': ' . substr((string) $response->body(), 0, 200);
                continue;
            }

            $data = $response->json();
            if (!is_array($data)) {
                $errors[] = $url . ' -> non-JSON payload';
                continue;
            }

            $data['client_ms'] = $clientMs;
            $data['node']      = 'face-api:' . $url;

            return $data;
        }

        throw new RuntimeException("face_api {$action} failed on all nodes: " . implode(' | ', $errors));
    }

    /**
     * เรียงลำดับโหนดแบบ round-robin — แต่ละคำขอเริ่มที่โหนดถัดไป เพื่อให้ web ใช้
     * ทุกโหนดจริง (ไม่ใช่ใช้โหนดแรกอย่างเดียวแล้วรอ failover)
     * ถ้าอ่าน cache ไม่ได้จะเริ่มที่โหนดแรกเสมอ (ยังคง failover ได้ตามปกติ)
     *
     * @return array<int, string>
     */
    private function orderedUrls(): array
    {
        $n = count($this->urls);
        if ($n <= 1) {
            return $this->urls;
        }

        try {
            $seq = (int) Cache::increment('face_api:round_robin');
        } catch (Throwable) {
            $seq = 1;
        }

        $start = $seq % $n;

        return array_merge(array_slice($this->urls, $start), array_slice($this->urls, 0, $start));
    }

    /**
     * สถานะของทุกโหนด (ใช้ใน dashboard/health check)
     *
     * @return array<int, array<string, mixed>>
     */
    public function healthAll(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $out = [];
        foreach ($this->urls as $url) {
            $out[] = $this->healthOf($url);
        }

        return $out;
    }

    /**
     * สถานะของโหนดแรกที่ตอบสนองได้ (ถ้าไม่มีเลย = โหนดแรกที่ตั้งค่าไว้)
     *
     * @return array<string, mixed>
     */
    public function health(): array
    {
        if (!$this->isEnabled()) {
            return ['available' => false, 'error' => 'FACE_API_URL is not configured'];
        }

        $first = null;
        foreach ($this->urls as $url) {
            $health = $this->healthOf($url);
            $first ??= $health;
            if (($health['available'] ?? false) === true) {
                return $health;
            }
        }

        return $first ?? ['available' => false, 'error' => 'FACE_API_URL is not configured'];
    }

    /**
     * @return array<string, mixed>
     */
    private function healthOf(string $url): array
    {
        $request = Http::timeout($this->healthTimeout)->acceptJson();
        if ($this->key !== '') {
            $request = $request->withHeaders(['X-API-Key' => $this->key]);
        }

        $start = microtime(true);
        try {
            $response = $request->get($url . '/health');
            $latency  = (int) round((microtime(true) - $start) * 1000);

            if (!$response->successful()) {
                return [
                    'available'   => false,
                    'url'         => $url,
                    'http_status' => $response->status(),
                    'latency_ms'  => $latency,
                ];
            }

            return array_merge($response->json() ?? [], [
                'available'  => true,
                'url'        => $url,
                'latency_ms' => $latency,
            ]);
        } catch (Throwable $e) {
            return ['available' => false, 'url' => $url, 'error' => $e->getMessage()];
        }
    }

    /**
     * face_api ถอดรูปด้วย stb_image (JPEG baseline/PNG/BMP/TGA) — WebP/GIF ที่อัปโหลดเข้ามา
     * จะถูกแปลงเป็น JPEG ด้วย GD ก่อนส่ง (คุณภาพ 95 เพื่อไม่ให้ embedding เพี้ยน)
     */
    private function toJpegIfUnsupported(string $bytes): string
    {
        $isWebp = strlen($bytes) > 12
            && substr($bytes, 0, 4) === 'RIFF'
            && substr($bytes, 8, 4) === 'WEBP';
        $isGif  = substr($bytes, 0, 3) === 'GIF';
        $isHeic = strlen($bytes) > 12
            && substr($bytes, 4, 4) === 'ftyp'
            && in_array(substr($bytes, 8, 4), ['heic', 'heix', 'mif1', 'msf1', 'hevc'], true);

        if (!$isWebp && !$isGif && !$isHeic) {
            return $bytes;
        }

        $jpg = $this->reencodeJpeg($bytes);
        if ($jpg === null) {
            throw new RuntimeException('image is WebP/GIF/HEIC but it cannot be converted to JPEG (GD)');
        }

        return $jpg;
    }

    /**
     * แปลงภาพใด ๆ ที่ GD อ่านได้เป็น JPEG พื้นฐาน (ใช้ทั้งก่อนส่งและเป็น retry)
     */
    private function reencodeJpeg(string $bytes): ?string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return null;
        }

        $img = @imagecreatefromstring($bytes);
        if ($img === false) {
            return null;
        }

        // พื้นหลังขาวรองรับภาพที่มี transparency (PNG/WebP) ไม่ให้กลายเป็นดำ
        $canvas = imagecreatetruecolor(imagesx($img), imagesy($img));
        if ($canvas !== false) {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopy($canvas, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            $source = $canvas;
        } else {
            $source = $img;
        }

        ob_start();
        imagejpeg($source, null, 95);
        $jpg = (string) ob_get_clean();

        // PHP >= 8.0 คืนหน่วยความจำของ GD เอง — imagedestroy() ถูก deprecated ใน 8.5
        return $jpg !== '' ? $jpg : null;
    }
}
