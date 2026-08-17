<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminFailedJobsManagementTest extends TestCase
{
    use RefreshDatabase;

    private function insertMockFailedJob(string $queue = 'notifications'): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid'       => $uuid,
            'connection' => 'redis',
            'queue'      => $queue,
            'payload'    => json_encode([
                'displayName' => 'App\\Jobs\\SendLineNotificationJob',
                'job'         => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'attempts'    => 3,
                'data'        => [
                    'commandName' => 'App\\Jobs\\SendLineNotificationJob',
                ],
            ]),
            'exception'  => "RuntimeException: LINE API connection timed out in /app/Jobs/SendLineNotificationJob.php:45\nStack trace:\n#0 /vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(100): App\\Jobs\\SendLineNotificationJob->handle()",
            'failed_at'  => now(),
        ]);

        return $uuid;
    }

    public function test_admin_can_view_failed_jobs_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $uuid = $this->insertMockFailedJob('notifications');

        $response = $this->actingAs($admin)->get(route('admin.system.failed-jobs.index'));

        $response->assertOk();
        $response->assertSee('Failed Queue Jobs');
        $response->assertSee('SendLineNotificationJob');
        $response->assertSee('notifications');
        $response->assertSee($uuid);
    }

    public function test_admin_can_view_single_failed_job_details_json(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $uuid = $this->insertMockFailedJob('ai');

        $response = $this->actingAs($admin)->get(route('admin.system.failed-jobs.show', $uuid));

        $response->assertOk();
        $response->assertJsonStructure([
            'id',
            'uuid',
            'connection',
            'queue',
            'display_name',
            'failed_at',
            'exception',
            'payload',
        ]);
        $response->assertJson([
            'uuid'  => $uuid,
            'queue' => 'ai',
        ]);
    }

    public function test_admin_can_retry_specific_failed_job(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $uuid = $this->insertMockFailedJob('exports');

        $job = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        $this->assertNotNull($job);

        $response = $this->actingAs($admin)->post(route('admin.system.failed-jobs.retry', $job->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_admin_can_retry_all_failed_jobs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->insertMockFailedJob('notifications');
        $this->insertMockFailedJob('ai');

        $this->assertEquals(2, DB::table('failed_jobs')->count());

        $response = $this->actingAs($admin)->post(route('admin.system.failed-jobs.retry-all'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_admin_can_delete_and_flush_failed_jobs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $uuid1 = $this->insertMockFailedJob('notifications');
        $uuid2 = $this->insertMockFailedJob('ai');

        $job1 = DB::table('failed_jobs')->where('uuid', $uuid1)->first();

        // 1. Delete single failed job
        $resDelete = $this->actingAs($admin)->delete(route('admin.system.failed-jobs.destroy', $job1->id));
        $resDelete->assertRedirect();
        $this->assertDatabaseMissing('failed_jobs', ['id' => $job1->id]);

        // 2. Flush all remaining failed jobs
        $resFlush = $this->actingAs($admin)->delete(route('admin.system.failed-jobs.flush'));
        $resFlush->assertRedirect();
        $this->assertEquals(0, DB::table('failed_jobs')->count());
    }

    public function test_student_and_guest_are_forbidden_from_managing_failed_jobs(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $uuid = $this->insertMockFailedJob();

        $resStudent = $this->actingAs($student)->get(route('admin.system.failed-jobs.index'));
        $this->assertTrue(in_array($resStudent->status(), [403, 302], true));

        auth()->logout();
        $resGuest = $this->get(route('admin.system.failed-jobs.index'));
        $resGuest->assertRedirect(route('login'));
    }
}
