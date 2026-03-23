<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EnsureAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) env('ADMIN_EMAIL', ''));
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($email === '' || $password === '') {
            $this->command?->warn('Skipping admin bootstrap because ADMIN_EMAIL or ADMIN_PASSWORD is missing.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Admin User'),
                'phone' => env('ADMIN_PHONE', '9000000001'),
                'password' => Hash::make($password),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        $this->command?->info("Admin account is ready for {$email}.");
    }
}
