<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\JobListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SqlQueryBindingSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_activities_index_handles_sql_injection_payloads_safely(): void
    {
        $category = ActivityCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'General', 'color' => '#000000', 'min_hours_required' => 0]
        );

        $student = User::factory()->create([
            'role' => 'student',
            'faculty' => "Engineering' OR '1'='1",
            'department' => "Computer Science'); DROP TABLE users;--",
        ]);

        $creator = User::factory()->create(['role' => 'admin']);

        Activity::create([
            'created_by' => $creator->id,
            'category_id' => $category->id,
            'title' => 'Secure Programming Workshop',
            'description' => 'Cybersecurity activity',
            'location' => 'Building 1',
            'activity_date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '12:00',
            'activity_hours' => 3,
            'max_participants' => 30,
            'register_open_at' => now()->subDay(),
            'register_close_at' => now()->addDays(1),
            'checkin_open_at' => now()->addDays(2)->setTime(8, 30),
            'checkin_close_at' => now()->addDays(2)->setTime(12, 30),
            'scope' => 'department',
            'department' => 'Computer Science',
            'faculty' => 'Engineering',
            'status' => 'open',
            'is_mandatory' => false,
            'qr_token' => 'SECURE_TOKEN_01',
        ]);

        // 1. Recommended sort with malicious faculty/dept in user model
        $response = $this->actingAs($student)->get(route('activities.index', ['sort' => 'recommended']));
        $response->assertOk();

        // 2. Closing soon sort with search containing SQL injection payload
        $response = $this->actingAs($student)->get(route('activities.index', [
            'sort' => 'closing_soon',
            'search' => "' OR '1'='1' --",
        ]));
        $response->assertOk();

        // 3. Upcoming sort
        $response = $this->actingAs($student)->get(route('activities.index', ['sort' => 'upcoming']));
        $response->assertOk();
    }

    public function test_jobs_index_handles_sql_injection_payloads_safely(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
        ]);

        JobListing::create([
            'created_by' => $staff->id,
            'title' => 'Campus Lab Assistant',
            'position' => 'Lab Assistant',
            'description' => 'Assisting in science lab',
            'location' => 'Lab 301',
            'status' => 'open',
            'gender' => 'any',
            'compensation' => '500 THB/day',
            'start_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        // 1. Recommended sort
        $response = $this->actingAs($staff)->get(route('jobs.index', ['sort' => 'recommended']));
        $response->assertOk();

        // 2. Starting soon sort with search containing injection payload
        $response = $this->actingAs($staff)->get(route('jobs.index', [
            'sort' => 'starting_soon',
            'search' => "'; DELETE FROM job_listings;--",
        ]));
        $response->assertOk();

        // 3. Compensation sort
        $response = $this->actingAs($staff)->get(route('jobs.index', ['sort' => 'compensation']));
        $response->assertOk();
    }
}
