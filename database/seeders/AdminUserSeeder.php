<?php
declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder for creating the highest‑privilege admin user.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Run the admin user seeder.
     */
    public function run(): void
    {
        $email = 'nontawat2546.2546@gmail.com';
        $existing = User::where('email', $email)->first();
        if ($existing) {
            $this->command->info('Admin user already exists.');
            return;
        }

        User::create([
            'email'      => $email,
            'password'   => 'password', // Laravel will hash via mutator
            'full_name'  => 'Nontawat Admin',
            'role'       => 'admin',
            'faculty'    => 'คณะบริหาร',
            'department' => 'แผนกระบบ',
            'is_active'  => true,
        ]);

        $this->command->info('Admin user created successfully.');
    }
}
