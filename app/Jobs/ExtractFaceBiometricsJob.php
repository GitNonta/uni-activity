<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\FaceVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExtractFaceBiometricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'ai';
    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $userId,
        public readonly ?string $photoPath = null,
        public readonly bool $force = false
    ) {}

    public function handle(FaceVerificationService $faceService): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            Log::warning("ExtractFaceBiometricsJob: User #{$this->userId} not found.");
            return;
        }

        // If not forced and descriptors already exist, skip
        if (!$this->force && $user->face_descriptor && $user->face_descriptor_js) {
            Log::info("ExtractFaceBiometricsJob: User #{$this->userId} already has face encodings.");
            return;
        }

        $photoRelPath = $this->photoPath ?: $user->profile_photo;
        if (empty($photoRelPath)) {
            Log::warning("ExtractFaceBiometricsJob: User #{$this->userId} has no profile photo path.");
            return;
        }

        $aiServerUrl = (string) config('services.ai_server.url');
        if (empty($aiServerUrl)) {
            Log::warning("ExtractFaceBiometricsJob: AI Server URL not configured.");
            return;
        }

        $imageContents = null;
        if (Storage::disk('public')->exists($photoRelPath)) {
            $imageContents = Storage::disk('public')->get($photoRelPath);
        } elseif (file_exists($photoRelPath)) {
            $imageContents = file_get_contents($photoRelPath);
        } elseif (file_exists(storage_path('app/public/' . ltrim($photoRelPath, '/')))) {
            $imageContents = file_get_contents(storage_path('app/public/' . ltrim($photoRelPath, '/')));
        }

        if (empty($imageContents)) {
            Log::error("ExtractFaceBiometricsJob: Could not read image for User #{$this->userId} at {$photoRelPath}");
            return;
        }

        $http = Http::timeout(20);
        $aiKey = (string) config('services.ai_server.key');
        if (!empty($aiKey)) {
            $http = $http->withHeaders(['X-API-Key' => $aiKey]);
        }

        $response = $http
            ->attach('image', $imageContents, basename($photoRelPath))
            ->post(rtrim($aiServerUrl, '/') . '/extract');

        if (!$response->successful()) {
            Log::error("ExtractFaceBiometricsJob failed for User #{$this->userId}", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException("AI Server extraction failed with status {$response->status()}");
        }

        $data = $response->json();
        $updated = false;

        if (!empty($data['embedding_512d']) && is_array($data['embedding_512d'])) {
            $user->face_descriptor = $data['embedding_512d'];
            $updated = true;
        }

        if (!empty($data['embedding_128d']) && is_array($data['embedding_128d'])) {
            $user->face_descriptor_js = $data['embedding_128d'];
            $updated = true;
        }

        if ($updated) {
            $user->save();
            Log::info("ExtractFaceBiometricsJob: Successfully extracted and encrypted biometrics for User #{$this->userId}");
        } else {
            Log::warning("ExtractFaceBiometricsJob: No face detected in photo for User #{$this->userId}");
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error("ExtractFaceBiometricsJob permanently failed for User #{$this->userId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
