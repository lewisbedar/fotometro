<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(LineStationSeeder::class);

        $admin = User::updateOrCreate([
            'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        ], [
            'name' => env('ADMIN_NAME', 'Administrateur fotometro'),
            'username' => Str::slug(env('ADMIN_NAME', 'Administrateur fotometro')),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
        ]);

        $admin->forceFill([
            'role' => UserRole::Admin,
            'status' => UserStatus::Approved,
            'approved_at' => $admin->approved_at ?? now(),
        ])->save();
    }
}
