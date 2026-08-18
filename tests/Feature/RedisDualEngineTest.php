<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\RedisSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RedisDualEngineTest extends TestCase
{
    public function test_in_memory_cache_operates_successfully(): void
    {
        Cache::put('fast_test_key', 'in_memory_val', 60);
        $val = Cache::get('fast_test_key');
        $this->assertSame('in_memory_val', $val);
    }

    public function test_redis_search_service_indexes_and_searches_documents(): void
    {
        $searchService = app(RedisSearchService::class);
        $indexed = $searchService->indexDocument('activities', 'act_101', [
            'title' => 'กิจกรรมปฐมนิเทศนักศึกษาใหม่',
            'location' => 'หอประชุมใหญ่ อาคาร 1',
        ]);

        $this->assertTrue($indexed);

        $results = $searchService->search('activities', 'ปฐมนิเทศ');
        $this->assertIsArray($results);
    }

    public function test_redis_search_service_stores_and_retrieves_critical_data(): void
    {
        $searchService = app(RedisSearchService::class);
        $stored = $searchService->storeCriticalData('security_audit_101', [
            'event' => 'biometric_checkin',
            'user_id' => 99,
            'verified' => true,
        ], 300);

        $this->assertTrue($stored);

        $retrieved = $searchService->getCriticalData('security_audit_101');
        $this->assertIsArray($retrieved);
        $this->assertSame('biometric_checkin', $retrieved['event']);
        $this->assertTrue($retrieved['verified']);
    }
}
