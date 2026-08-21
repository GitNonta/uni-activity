<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

use function app;

/**
 * Regression tests for the Redis → database session fallback.
 *
 * The dual-node cluster (2 phones × 3 Laravel workers behind an Nginx LB with
 * least_conn) requires sessions to live in a SHARED store. Falling back to the
 * 'file' driver breaks auth: each phone's workers write to their own local
 * storage/framework/sessions, so a session created on Phone 1 is invisible to
 * Phone 2 workers → random 401s on /chat/threads, /student/notifications and
 * 403s on /broadcasting/auth. The 'database' driver (shared PostgreSQL) keeps
 * sessions consistent across the whole cluster when Redis is unreachable.
 */
class RedisFallbackSessionTest extends TestCase
{
    public function test_redis_fallback_can_switch_sessions_to_shared_database_driver_when_redis_is_down(): void
    {
        config(['session.driver' => 'redis']);
        config(['cache.default' => 'redis']);

        $connection = \Mockery::mock();
        $connection->shouldReceive('ping')->andThrow(new \Exception('Connection refused'));
        Redis::shouldReceive('connection')->andReturn($connection);

        $this->makeProvider()->applyRedisFallback();

        $this->assertSame('database', config('session.driver'));
        $this->assertSame('file', config('cache.default'));

        // Restore test defaults so other tests are unaffected
        config(['session.driver' => 'array']);
        config(['cache.default' => 'array']);
    }

    public function test_redis_fallback_can_keep_redis_drivers_when_redis_is_available(): void
    {
        config(['session.driver' => 'redis']);
        config(['cache.default' => 'redis']);

        $connection = \Mockery::mock();
        $connection->shouldReceive('ping')->andReturn('PONG');
        Redis::shouldReceive('connection')->andReturn($connection);

        $this->makeProvider()->applyRedisFallback();

        $this->assertSame('redis', config('session.driver'));
        $this->assertSame('redis', config('cache.default'));

        config(['session.driver' => 'array']);
        config(['cache.default' => 'array']);
    }

    public function test_redis_fallback_does_not_touch_drivers_when_redis_is_not_configured(): void
    {
        config(['session.driver' => 'database']);
        config(['cache.default' => 'file']);

        Redis::shouldReceive('connection')->never();

        $this->makeProvider()->applyRedisFallback();

        $this->assertSame('database', config('session.driver'));
        $this->assertSame('file', config('cache.default'));
    }

    private function makeProvider(): AppServiceProvider
    {
        return new AppServiceProvider(app());
    }
}
