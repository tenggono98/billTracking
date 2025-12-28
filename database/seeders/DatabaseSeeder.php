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

        $user = User::firstOrCreate(
            ['phone_number' => '085609022799'],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'pin' => Hash::make('199889'),
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Ensure pin is set even if user already exists
        if (!$user->pin || !Hash::check('199889', $user->pin)) {
            $user->pin = Hash::make('199889');
            $user->password = Hash::make('password');
            $user->save();
        }
    }
}
