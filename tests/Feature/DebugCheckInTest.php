<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Registration;
use App\Models\User;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DebugCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_checkin_flow(): void
    {
        $category = ActivityCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'General', 'color' => '#000000', 'min_hours_required' => 0]
        );

        $creator = User::firstOrCreate(
            ['email' => 'debug_admin@pkru.ac.th'],
            ['role' => 'admin', 'full_name' => 'Admin', 'password' => bcrypt('password')]
        );

        $activity = Activity::create([
            'title'             => 'Debug Activity',
            'location'          => 'Main Hall',
            'activity_date'     => now()->toDateString(),
            'start_time'        => '08:00',
            'end_time'          => '17:00',
            'activity_hours'    => 3,
            'max_participants'  => 100,
            'register_open_at'  => now()->subDays(5),
            'register_close_at' => now()->addDays(2),
            'checkin_open_at'   => now()->subHour(),
            'checkin_close_at'  => now()->addHours(2),
            'category_id'       => $category->id,
            'scope'             => 'university',
            'status'            => 'open',
            'is_mandatory'      => false,
            'require_face_scan' => false,
            'created_by'        => $creator->id,
            'qr_token'          => 'DEBUG_TOKEN_12345',
        ]);

        $student = User::factory()->create([
            'role' => 'student',
        ]);

        Registration::create([
            'user_id'     => $student->id,
            'activity_id' => $activity->id,
            'status'      => 'approved',
        ]);

        $service = app(CheckInService::class);
        $result = $service->processCheckIn($activity->qr_token, $student);

        fwrite(STDERR, "\nDEBUG RESULT: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        $response = $this->actingAs($student)->post(route('checkin.store', $activity->qr_token));
        if ($response->isRedirect()) {
            fwrite(STDERR, "\nSESSION ERRORS: " . json_encode(session('errors') ? session('errors')->all() : session()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        }

        $this->assertTrue($result['success']);
    }
}
