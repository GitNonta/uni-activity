<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SecurityLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class SecurityLogFactory extends Factory
{
    protected $model = SecurityLog::class;

    public function definition(): array
    {
        return [
            'user_id'           => null,
            'event_type'        => $this->faker->randomElement(['multi_account_login', 'suspicious_checkin', 'device_mismatch']),
            'ip_address'        => $this->faker->ipv4(),
            'device_fingerprint'=> hash('sha256', $this->faker->userAgent()),
            'related_user_ids'  => [],
            'details'           => ['message' => $this->faker->sentence()],
            'is_reviewed'       => false,
        ];
    }
}
