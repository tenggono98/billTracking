<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Only create default user if explicitly configured in environment
        if (env('SEEDER_CREATE_DEFAULT_USER', false)) {
            $user = User::firstOrCreate(
                ['email' => env('SEEDER_DEFAULT_EMAIL', 'admin@example.com')],
                [
                    'name' => env('SEEDER_DEFAULT_NAME', 'Admin User'),
                    'phone_number' => env('SEEDER_DEFAULT_PHONE', null),
                    'pin' => Hash::make(env('SEEDER_DEFAULT_PIN', 'change-me')),
                    'password' => Hash::make(env('SEEDER_DEFAULT_PASSWORD', 'change-me')),
                    'email_verified_at' => now(),
                ]
            );
        }

        // Alternatively, you can use User factories:
        // User::factory()->create([
        //     'name' => 'Admin User',
        //     'email' => 'admin@example.com',
        // ]);
    }
}
