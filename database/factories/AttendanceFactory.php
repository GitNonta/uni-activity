<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'user_id'           => \App\Models\User::factory(),
            'activity_id'       => \App\Models\Activity::factory(),
            'checked_in_at'     => now(),
            'method'            => 'qr_scan',
            'status'            => 'pending',
            'is_verified'       => true,
            'ip_address'        => $this->faker->ipv4(),
            'device_fingerprint'=> hash('sha256', $this->faker->userAgent()),
            'is_suspicious'     => false,
        ];
    }
}
