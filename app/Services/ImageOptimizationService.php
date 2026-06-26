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
    public function storeActivityImageAsWebp(
        UploadedFile $file,
        int $maxWidth = 1600,
        int $quality = 82
    ): string {
        [$source, $width, $height] = $this->createImageResource($file);

        $targetWidth = min($width, $maxWidth);
        $targetHeight = (int) round(($targetWidth / $width) * $height);
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (!$canvas) {
            imagedestroy($source);
            throw new RuntimeException('Cannot create optimized image canvas.');
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $relativePath = 'activities/' . Str::uuid()->toString() . '.webp';
        $absolutePath = Storage::disk('public')->path($relativePath);
        $directory = dirname($absolutePath);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new RuntimeException('Cannot create activity image directory.');
        }

        if (!imagewebp($canvas, $absolutePath, $quality)) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new RuntimeException('Cannot save optimized activity image.');
        }

        imagedestroy($source);
        imagedestroy($canvas);

        return $relativePath;
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

        $size = getimagesize($path);
        if ($size === false) {
            throw new RuntimeException('Uploaded file is not a valid image.');
        }

        [$width, $height] = $size;
        $resource = match ($size['mime'] ?? '') {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default => false,
        };

        if (!$resource) {
            throw new RuntimeException('Activity images must be JPEG, PNG, or WebP.');
        }

        return [$resource, (int) $width, (int) $height];
    }
}
