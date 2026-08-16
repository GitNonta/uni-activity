<?php
declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * สกัดรหัสใบหน้าสำหรับรูปโปรไฟล์เก่าที่ยังไม่เคยประมวลผล
 * Auto-extract face encodings (512D + 128D) for existing profile photos
 */
class ExtractMissingFaceEncodings extends Command
{
    protected $signature = 'face:extract-missing 
                           {--limit=10 : Number of users to process at once}
                           {--force : Force re-extraction even if encodings exist}
                           {--dry-run : Show what would be processed without actually doing it}';

    protected $description = 'Auto-extract face encodings (512D + 128D) for existing profile photos that haven\'t been processed';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info("🎯 Face Encoding Auto-Extraction (512D + 128D)");
        $this->info("=" . str_repeat("=", 55));

        // หา users ที่มีรูปแต่ไม่มีรหัสครบ
        $query = User::whereNotNull('profile_photo')
                    ->where('profile_photo', '!=', '');

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('face_descriptor')
                  ->orWhereNull('face_descriptor_js');
            });
        }

        $users = $query->limit($limit)->get();

        if ($users->isEmpty()) {
            $this->info("✅ No users found needing face encoding extraction");
            return 0;
        }

        $this->info("📊 Found {$users->count()} users with profile photos needing extraction");

        if ($dryRun) {
            $this->info("🔍 DRY RUN - Would process:");
            foreach ($users as $user) {
                $hasPhoto = $user->profile_photo ? '✅' : '❌';
                $has512D = $user->face_descriptor ? '✅' : '❌';
                $has128D = $user->face_descriptor_js ? '✅' : '❌';
                
                $this->line("   User #{$user->id} ({$user->full_name})");
                $this->line("     Photo: {$hasPhoto} | 512D: {$has512D} | 128D: {$has128D}");
            }
            return 0;
        }

        // ตรวจสอบ AI Server
        $aiServerUrl = config('services.ai_server.url');
        if (empty($aiServerUrl)) {
            $this->error("❌ AI Server URL not configured");
            return 1;
        }

        $this->info("🔧 AI Server: {$aiServerUrl}");

        // ประมวลผลทีละคน
        $processed = 0;
        $success = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();

        foreach ($users as $user) {
            $processed++;
            
            try {
                $result = $this->extractUserFaceEncoding($user, $aiServerUrl, $force);
                
                if ($result['success']) {
                    $success++;
                    $this->newLine();
                    $this->info("✅ User #{$user->id}: {$result['message']}");
                } else {
                    $errors++;
                    $this->newLine();
                    $this->error("❌ User #{$user->id}: {$result['message']}");
                }
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("❌ User #{$user->id}: {$e->getMessage()}");
                Log::error("Face extraction error for user {$user->id}: " . $e->getMessage());
            }

            $progressBar->advance();
            
            // หน่วงเวลาเล็กน้อยเพื่อไม่ให้ AI Server ทำงานหนักเกินไป
            usleep(500000); // 0.5 วินาที
        }

        $progressBar->finish();
        $this->newLine(2);

        // สรุปผล
        $this->info("📊 EXTRACTION SUMMARY:");
        $this->info("=" . str_repeat("=", 30));
        $this->info("👥 Total processed: {$processed}");
        $this->info("✅ Successful: {$success}");
        $this->info("❌ Errors: {$errors}");
        
        if ($success > 0) {
            $successRate = round(($success / $processed) * 100, 1);
            $this->info("📈 Success rate: {$successRate}%");
        }

        return $errors > 0 ? 1 : 0;
    }

    private function extractUserFaceEncoding(User $user, string $aiServerUrl, bool $force): array
    {
        // ตรวจสอบไฟล์รูป
        $photoPath = $user->profile_photo;
        if (!$photoPath) {
            return ['success' => false, 'message' => 'No profile photo'];
        }

        $fullPath = storage_path('app/public/' . $photoPath);
        if (!file_exists($fullPath)) {
            return ['success' => false, 'message' => 'Photo file not found: ' . $photoPath];
        }

        // ตรวจสอบว่าต้องการ extraction หรือไม่
        if (!$force && $user->face_descriptor && $user->face_descriptor_js) {
            return ['success' => true, 'message' => 'Already has both encodings (skipped)'];
        }

        // อ่านไฟล์และส่งไป AI Server
        try {
            $httpReq = Http::timeout(15);
            $aiKey = config('services.ai_server.key');
            if (!empty($aiKey)) {
                $httpReq = $httpReq->withHeaders(['X-API-Key' => $aiKey]);
            }
            $response = $httpReq
                ->attach('image', file_get_contents($fullPath), basename($fullPath))
                ->post(rtrim($aiServerUrl, '/') . '/extract');

            if (!$response->successful()) {
                return ['success' => false, 'message' => 'AI Server error: ' . $response->body()];
            }

            $aiResult = $response->json();
            
            // เตรียม update data
            $updateData = [];
            $extracted = [];

            // 512D encoding
            if (!empty($aiResult['embedding_512d'])) {
                $updateData['face_descriptor'] = $aiResult['embedding_512d'];
                $extracted[] = '512D';
            }

            // 128D encoding  
            if (!empty($aiResult['embedding_128d'])) {
                $updateData['face_descriptor_js'] = $aiResult['embedding_128d'];
                $extracted[] = '128D';
            }

            // รองรับ API เก่าที่ยังส่ง 'embedding' มา
            if (empty($updateData) && !empty($aiResult['embedding'])) {
                $updateData['face_descriptor'] = $aiResult['embedding'];
                $extracted[] = '512D (legacy)';
            }

            if (empty($updateData)) {
                return ['success' => false, 'message' => 'AI Server returned no valid encodings'];
            }

            // บันทึกลงฐานข้อมูล
            $user->update($updateData);

            $extractedStr = implode(' + ', $extracted);
            $fileInfo = pathinfo($fullPath);
            $fileSize = round(filesize($fullPath) / 1024, 1);
            
            Log::info("Auto-extracted face encodings for user {$user->id}: {$extractedStr} from {$fileInfo['basename']} ({$fileSize}KB)");

            return [
                'success' => true, 
                'message' => "Extracted {$extractedStr} from existing photo ({$fileSize}KB)"
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Extraction failed: ' . $e->getMessage()];
        }
    }
}