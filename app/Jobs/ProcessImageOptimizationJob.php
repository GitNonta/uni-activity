<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessImageOptimizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'images';
    public int $tries = 2;
    public int $backoff = 15;

    public function __construct(
        public readonly string $relativeStoragePath,
        public readonly int $maxWidth = 1600,
        public readonly int $quality = 82
    ) {}

    public function handle(): void
    {
        $disk = Storage::disk('public');
        if (!$disk->exists($this->relativeStoragePath)) {
            Log::warning("ProcessImageOptimizationJob: Image {$this->relativeStoragePath} not found.");
            return;
        }

        $fullPath = $disk->path($this->relativeStoragePath);
        $size = @getimagesize($fullPath);
        if ($size === false) {
            Log::warning("ProcessImageOptimizationJob: Invalid image format at {$fullPath}.");
            return;
        }

        [$width, $height] = $size;
        $mime = $size['mime'] ?? '';

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($fullPath),
            'image/png'  => @imagecreatefrompng($fullPath),
            'image/webp' => @imagecreatefromwebp($fullPath),
            default      => false,
        };

        if (!$source) {
            Log::warning("ProcessImageOptimizationJob: Could not create GD resource from {$mime}.");
            return;
        }

        $targetWidth = min($width, $this->maxWidth);
        $targetHeight = (int) round(($targetWidth / $width) * $height);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$canvas) {
            imagedestroy($source);
            return;
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        imagewebp($canvas, $fullPath, $this->quality);

        imagedestroy($source);
        imagedestroy($canvas);

        Log::info("ProcessImageOptimizationJob: Optimized image {$this->relativeStoragePath} to {$targetWidth}x{$targetHeight} (Q{$this->quality})");
    }

    public function failed(Throwable $exception): void
    {
        Log::error("ProcessImageOptimizationJob failed permanently for {$this->relativeStoragePath}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
