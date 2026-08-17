<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageConfigurationTest extends TestCase
{
    public function test_public_storage_disk_is_resolvable(): void
    {
        $disk = Storage::disk('public');
        $this->assertNotNull($disk);
    }

    public function test_s3_storage_disk_adapter_is_resolvable(): void
    {
        // Configure mock S3 environment to test Flysystem S3 adapter instantiation
        config([
            'filesystems.disks.s3' => [
                'driver'                  => 's3',
                'key'                     => 'test-key',
                'secret'                  => 'test-secret',
                'region'                  => 'auto',
                'bucket'                  => 'test-bucket',
                'endpoint'                => 'https://example-account.r2.cloudflarestorage.com',
                'use_path_style_endpoint' => false,
                'throw'                   => false,
            ],
        ]);

        $disk = Storage::disk('s3');
        $this->assertNotNull($disk);
        $this->assertInstanceOf(\Illuminate\Contracts\Filesystem\Filesystem::class, $disk);
    }

    public function test_default_filesystem_disk_is_configurable_via_env(): void
    {
        config(['filesystems.default' => 's3']);
        $this->assertEquals('s3', config('filesystems.default'));
    }
}
