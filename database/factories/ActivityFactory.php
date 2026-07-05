<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('now', '+30 days');

        return [
            'title'                       => $this->faker->sentence(4),
            'description'                 => $this->faker->paragraph(),
            'location'                    => $this->faker->address(),
            'activity_date'               => $date->format('Y-m-d'),
            'start_time'                  => '09:00:00',
            'end_time'                    => '12:00:00',
            'activity_hours'              => 3,
            'max_participants'            => 50,
            'register_open_at'            => now()->subDay(),
            'register_close_at'           => now()->addDays(5),
            'checkin_open_at'             => now()->subHour(),
            'checkin_close_at'            => now()->addHours(3),
            'is_mandatory'                => false,
            'qr_token'                    => Str::uuid(),
            'status'                      => 'open',
            'scope'                       => 'university',
            'allow_early_checkin'         => true,
            'require_attendance_approval' => false,
            'created_by'                  => \App\Models\User::factory()->create(['role' => 'staff'])->id,
        ];
    }
}
