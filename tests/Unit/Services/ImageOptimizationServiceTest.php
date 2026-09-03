<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ImageOptimizationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageOptimizationServiceTest extends TestCase
{
    private ImageOptimizationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ImageOptimizationService();
        Storage::fake('public');
    }

    public function test_small_image_is_stored_as_webp_without_resample(): void
    {
        // 200x100 image is below maxWidth=1600 → no-op copy path, still saved as WebP.
        $file = UploadedFile::fake()->image('photo.jpg', 200, 100);

        $path = $this->service->storeImageAsWebp($file, 'test-uploads');

        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith('.webp', $path);
    }

    public function test_large_image_is_downscaled_to_max_width(): void
    {
        $file = UploadedFile::fake()->image('big.jpg', 3200, 1600);

        $path = $this->service->storeImageAsWebp($file, 'test-uploads', maxWidth: 1600);

        Storage::disk('public')->assertExists($path);

        $info = getimagesize(Storage::disk('public')->path($path));
        $this->assertNotFalse($info);
        $this->assertSame('image/webp', $info['mime']);
        $this->assertSame(1600, $info[0]);
        $this->assertSame(800, $info[1]);
    }

    public function test_invalid_file_throws(): void
    {
        $file = UploadedFile::fake()->createWithContent('not-an-image.txt', 'hello world');

        $this->expectException(\RuntimeException::class);

        $this->service->storeImageAsWebp($file, 'test-uploads');
    }
}
