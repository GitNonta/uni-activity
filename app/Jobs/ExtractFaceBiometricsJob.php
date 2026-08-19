<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\AiLoadBalancerService;
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

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $userId,
        public readonly ?string $photoPath = null,
        public readonly bool $force = false
    ) {
        $this->onQueue('ai');
    }

    public function handle(AiLoadBalancerService $loadBalancer): void
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

        // Security: Validate path — reject traversal attempts (N5 fix)
        if (str_contains($photoRelPath, '..') || str_contains($photoRelPath, "\0")) {
            Log::warning("ExtractFaceBiometricsJob: Path traversal attempt blocked for User #{$this->userId}: {$photoRelPath}");
            return;
        }

        // Use Storage facade exclusively — never raw file_get_contents (N5 fix)
        $imageContents = null;
        if (Storage::disk('public')->exists($photoRelPath)) {
            $imageContents = Storage::disk('public')->get($photoRelPath);
        } elseif (file_exists(storage_path('app/public/' . ltrim($photoRelPath, '/')))) {
            $imageContents = file_get_contents(storage_path('app/public/' . ltrim($photoRelPath, '/')));
        }

        if (empty($imageContents)) {
            Log::error("ExtractFaceBiometricsJob: Could not read image for User #{$this->userId} at {$photoRelPath}");
            return;
        }

        $lbResponse = $loadBalancer->executeWithFailover(
            function (string $nodeUrl, string $apiKey, int $timeout) use ($imageContents, $photoRelPath): array {
                $http = Http::timeout($timeout);
                if (!empty($apiKey)) {
                    $http = $http->withHeaders(['X-API-Key' => $apiKey]);
                }

                $response = $http
                    ->attach('image', $imageContents, basename($photoRelPath))
                    ->post(rtrim($nodeUrl, '/') . '/extract');

                if (!$response->successful()) {
                    throw new \RuntimeException("Node {$nodeUrl} extract returned HTTP {$response->status()}: " . $response->body());
                }

                return $response->json();
            }
        );

        $data = $lbResponse['result'];
        $updated = false;

        if (!empty($data['embedding_512d']) && is_array($data['embedding_512d'])) {
            $user->face_descriptor = $data['embedding_512d'];
            $updated = true;
        } elseif (!empty($data['embedding']) && is_array($data['embedding'])) {
            $user->face_descriptor = $data['embedding'];
            $updated = true;
        }

        if (!empty($data['embedding_128d']) && is_array($data['embedding_128d'])) {
            $user->face_descriptor_js = $data['embedding_128d'];
            $updated = true;
        } elseif (!empty($user->face_descriptor) && empty($user->face_descriptor_js) && count($user->face_descriptor) >= 128) {
            $user->face_descriptor_js = array_slice($user->face_descriptor, 0, 128);
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
