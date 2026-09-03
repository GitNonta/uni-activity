<?php

declare(strict_types=1);

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageOptimizationService
{
    public function storeImageAsWebp(
        UploadedFile $file,
        string $directory = 'uploads',
        int $maxWidth = 1600,
        int $quality = 82
    ): string {
        [$source, $width, $height] = $this->createImageResource($file);

        // Downscale huge uploads in a few progressive steps instead of one
        // giant resample — each step operates on a shrinking canvas, which is
        // significantly faster and uses less peak memory on phone hardware.
        $targetWidth = min($width, $maxWidth);
        $targetHeight = (int) round(($targetWidth / $width) * $height);

        $canvas = $this->createResampledCanvas($source, $width, $height, $targetWidth, $targetHeight);
        imagedestroy($source);

        if (!$canvas) {
            throw new RuntimeException('Cannot create optimized image canvas.');
        }

        $relativePath = rtrim($directory, '/') . '/' . Str::uuid()->toString() . '.webp';
        $absolutePath = Storage::disk('public')->path($relativePath);
        $directory = dirname($absolutePath);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            imagedestroy($canvas);
            throw new RuntimeException('Cannot create activity image directory.');
        }

        if (!imagewebp($canvas, $absolutePath, $quality)) {
            imagedestroy($canvas);
            throw new RuntimeException('Cannot save optimized activity image.');
        }

        imagedestroy($canvas);

        return $relativePath;
    }

    public function storeActivityImageAsWebp(
        UploadedFile $file,
        int $maxWidth = 1600,
        int $quality = 82
    ): string {
        return $this->storeImageAsWebp($file, 'activities', $maxWidth, $quality);
    }

    /**
     * Resample $source onto a canvas of the target size, skipping the copy
     * entirely when dimensions already match. Large shrinks are split into
     * <=50% steps to cut CPU time and peak memory.
     */
    private function createResampledCanvas(GdImage $source, int $srcWidth, int $srcHeight, int $targetWidth, int $targetHeight): GdImage|false
    {
        // Image already within size limits → no resample needed at all.
        if ($srcWidth === $targetWidth && $srcHeight === $targetHeight) {
            return $source;
        }

        $current = $source;
        $currentWidth = $srcWidth;
        $currentHeight = $srcHeight;

        // Progressive halving while still more than ~2x the target.
        while ($currentWidth > $targetWidth * 2 && $currentHeight > $targetHeight * 2) {
            $halfWidth = max($targetWidth, (int) round($currentWidth / 2));
            $halfHeight = max($targetHeight, (int) round($currentHeight / 2));

            $stepped = $this->makeCanvas($current, $currentWidth, $currentHeight, $halfWidth, $halfHeight);
            if (!$stepped instanceof GdImage) {
                return false;
            }

            if ($current !== $source) {
                imagedestroy($current);
            }
            $current = $stepped;
            $currentWidth = $halfWidth;
            $currentHeight = $halfHeight;
        }

        if ($current === $source) {
            $final = $this->makeCanvas($source, $srcWidth, $srcHeight, $targetWidth, $targetHeight);
            if (!$final instanceof GdImage) {
                return false;
            }

            return $final;
        }

        // Final pass from the last halved intermediate to the exact target.
        $final = $this->makeCanvas($current, $currentWidth, $currentHeight, $targetWidth, $targetHeight);
        imagedestroy($current);

        return $final instanceof GdImage ? $final : false;
    }

    private function makeCanvas(GdImage $source, int $srcWidth, int $srcHeight, int $dstWidth, int $dstHeight): GdImage|false
    {
        $canvas = imagecreatetruecolor($dstWidth, $dstHeight);
        if (!$canvas) {
            return false;
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        if (!imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight)) {
            imagedestroy($canvas);

            return false;
        }

        return $canvas;
    }

    /**
     * @return array{0: GdImage, 1: int, 2: int}
     */
    private function createImageResource(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('Uploaded image is not readable.');
        }

        try {
            $size = getimagesize($path);
        } catch (\Throwable) {
            // Unreadable/corrupt uploads raise PHP warnings on some platforms
            // (converted to ErrorException) instead of returning false.
            $size = false;
        }
        if ($size === false) {
            throw new RuntimeException('Uploaded file is not a valid image.');
        }

        [$width, $height] = $size;
        $resource = match ($size['mime'] ?? '') {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            'image/gif' => imagecreatefromgif($path),
            default => false,
        };

        if (!$resource) {
            throw new RuntimeException('Activity images must be JPEG, PNG, WebP, or GIF.');
        }

        return [$resource, (int) $width, (int) $height];
    }
}
