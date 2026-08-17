<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExportExcelJob;
use App\Jobs\ExtractFaceBiometricsJob;
use App\Jobs\GeneratePdfTranscriptJob;
use App\Jobs\ProcessImageOptimizationJob;
use App\Jobs\RecomputeActivityStatisticsJob;
use App\Jobs\SendActivityReminderJob;
use App\Jobs\SendLineNotificationJob;
use App\Jobs\SyncCassandraDataJob;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueJobsArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role'                => 'student',
            'student_id'          => '6511223344',
            'full_name'           => 'สมศักดิ์ ขยันยิ่ง',
            'line_user_id'        => 'U' . Str::random(32),
            'line_notify_enabled' => true,
        ], $attributes));
    }

    private function createActivity(array $attributes = []): Activity
    {
        $category = ActivityCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'วิชาการ', 'color' => '#10b981', 'required_hours' => 12]
        );

        $creator = User::firstOrCreate(
            ['email' => 'staff@pkru.ac.th'],
            ['role' => 'staff', 'full_name' => 'Staff User', 'password' => bcrypt('password')]
        );

        return Activity::create(array_merge([
            'title'             => 'สัมมนาวิศวกรรมซอฟต์แวร์',
            'description'       => 'การออกแบบ Queue Architecture ในระบบขนาดใหญ่',
            'location'          => 'อาคาร 5',
            'activity_date'     => now()->tomorrow()->format('Y-m-d'),
            'start_time'        => '09:00',
            'end_time'          => '16:00',
            'activity_hours'    => 6,
            'max_participants'  => 100,
            'register_open_at'  => now()->subDays(5),
            'register_close_at' => now()->addDay(),
            'checkin_open_at'   => now()->tomorrow()->setTime(8, 30),
            'checkin_close_at'  => now()->tomorrow()->setTime(16, 30),
            'category_id'       => $category->id,
            'scope'             => 'university',
            'status'            => 'open',
            'is_mandatory'      => false,
            'created_by'        => $creator->id,
            'qr_token'          => Str::random(16),
        ], $attributes));
    }

    public function test_all_jobs_have_designated_priority_queues(): void
    {
        $faceJob = new ExtractFaceBiometricsJob(1);
        $this->assertEquals('ai', $faceJob->queue);
        $this->assertEquals(3, $faceJob->tries);

        $lineJob = new SendLineNotificationJob([['type' => 'text', 'text' => 'Hello']]);
        $this->assertEquals('notifications', $lineJob->queue);

        $reminderJob = new SendActivityReminderJob(1, 1);
        $this->assertEquals('notifications', $reminderJob->queue);

        $imgJob = new ProcessImageOptimizationJob('uploads/test.jpg');
        $this->assertEquals('images', $imgJob->queue);

        $pdfJob = new GeneratePdfTranscriptJob(1);
        $this->assertEquals('exports', $pdfJob->queue);

        $excelJob = new ExportExcelJob('students');
        $this->assertEquals('exports', $excelJob->queue);

        $statsJob = new RecomputeActivityStatisticsJob();
        $this->assertEquals('stats', $statsJob->queue);

        $syncJob = new SyncCassandraDataJob('attendances', ['id' => 1]);
        $this->assertEquals('sync', $syncJob->queue);
    }

    public function test_reminders_command_can_dispatch_jobs_to_notifications_queue(): void
    {
        Queue::fake();

        $student = $this->createStudent();
        $activity = $this->createActivity();

        Registration::create([
            'user_id'       => $student->id,
            'activity_id'   => $activity->id,
            'status'        => 'approved',
            'registered_at' => now()->subDay(),
        ]);

        $this->artisan('reminders:send')
            ->assertSuccessful();

        Queue::assertPushedOn('notifications', SendActivityReminderJob::class);
    }

    public function test_extract_face_biometrics_job_can_process_and_save_descriptors(): void
    {
        Storage::fake('public');
        config(['services.ai_server.url' => 'http://127.0.0.1:8000']);

        $student = $this->createStudent([
            'profile_photo'      => 'profile-photos/test.jpg',
            'face_descriptor'    => null,
            'face_descriptor_js' => null,
        ]);

        Storage::disk('public')->put('profile-photos/test.jpg', 'fake-image-bytes');

        Http::fake([
            'http://127.0.0.1:8000/extract' => Http::response([
                'success'        => true,
                'embedding_512d' => array_fill(0, 512, 0.05),
                'embedding_128d' => array_fill(0, 128, 0.02),
            ], 200),
        ]);

        $job = new ExtractFaceBiometricsJob($student->id, 'profile-photos/test.jpg', true);
        app()->call([$job, 'handle']);

        $student->refresh();
        $this->assertNotNull($student->face_descriptor);
        $this->assertNotNull($student->face_descriptor_js);
        $this->assertCount(512, $student->face_descriptor);
        $this->assertCount(128, $student->face_descriptor_js);
    }

    public function test_send_line_notification_job_respects_idempotency_lock(): void
    {
        Http::fake();
        config(['services.line.channel_access_token' => 'dummy_token']);

        $lockKey = 'test_lock_' . Str::random(8);

        $job1 = new SendLineNotificationJob(
            messages: [['type' => 'text', 'text' => 'Hello 1']],
            targetLineUserIds: ['U123456'],
            lockKey: $lockKey
        );

        app()->call([$job1, 'handle']);

        // Second dispatch with same lock key should be ignored
        $job2 = new SendLineNotificationJob(
            messages: [['type' => 'text', 'text' => 'Hello 2']],
            targetLineUserIds: ['U123456'],
            lockKey: $lockKey
        );

        app()->call([$job2, 'handle']);

        Http::assertSentCount(1);
    }

    public function test_recompute_activity_statistics_job_can_prewarm_caches(): void
    {
        Cache::flush();

        $activity = $this->createActivity();
        $student = $this->createStudent();

        $job = new RecomputeActivityStatisticsJob();
        app()->call([$job, 'handle']);

        $cached = Cache::get('admin_dashboard_stats_global');
        $this->assertIsArray($cached);
        $this->assertArrayHasKey('totalActivities', $cached);
        $this->assertArrayHasKey('totalStudents', $cached);
        $this->assertGreaterThanOrEqual(1, $cached['totalActivities']);
    }

    public function test_sync_cassandra_data_job_handles_simulated_dispatch(): void
    {
        $job = new SyncCassandraDataJob(
            tableName: 'audit_logs',
            payload: ['action' => 'login', 'user_id' => 1, 'timestamp' => time()],
            operation: 'insert'
        );

        // Should complete without exception in development/testing mode
        $job->handle();
        $this->assertTrue(true);
    }
}
