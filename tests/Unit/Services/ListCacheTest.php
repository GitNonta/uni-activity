<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ListCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ListCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_remember_stores_value_under_versioned_key(): void
    {
        $value = ListCache::remember(ListCache::GROUP_JOBS, 'list_demo', 60, fn () => 'cached-data');

        $this->assertSame('cached-data', $value);
        $this->assertTrue(Cache::has(ListCache::key(ListCache::GROUP_JOBS, 'list_demo')));
    }

    public function test_bump_invalidates_all_keys_of_the_group(): void
    {
        ListCache::remember(ListCache::GROUP_JOBS, 'list_a', 60, fn () => 'old-a');
        ListCache::remember(ListCache::GROUP_JOBS, 'list_b', 60, fn () => 'old-b');
        ListCache::remember(ListCache::GROUP_ANNOUNCEMENTS, 'list_a', 60, fn () => 'ann-old');

        ListCache::bump(ListCache::GROUP_JOBS);

        // jobs group keys moved to a new version → callback re-executes
        $this->assertSame('new-a', ListCache::remember(ListCache::GROUP_JOBS, 'list_a', 60, fn () => 'new-a'));
        $this->assertSame('new-b', ListCache::remember(ListCache::GROUP_JOBS, 'list_b', 60, fn () => 'new-b'));

        // other groups untouched
        $this->assertSame('ann-old', ListCache::remember(ListCache::GROUP_ANNOUNCEMENTS, 'list_a', 60, fn () => 'ann-changed'));
    }

    public function test_key_includes_current_version(): void
    {
        $this->assertSame('list:jobs:v1:geo_all', ListCache::key(ListCache::GROUP_JOBS, 'geo_all'));

        ListCache::bump(ListCache::GROUP_JOBS);
        $this->assertSame('list:jobs:v2:geo_all', ListCache::key(ListCache::GROUP_JOBS, 'geo_all'));
    }
}
