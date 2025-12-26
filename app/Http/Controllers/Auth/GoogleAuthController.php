<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // Find or create user
            $user = User::where('email', $googleUser->email)->first();

            if (!$user) {
                // Create new user
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'password' => bcrypt(Str::random(16)), // Random password
                    'email_verified_at' => now(),
                ]);
            } else {
                // Update existing user with Google ID if not set
                if (!$user->google_id) {
                    $user->update(['google_id' => $googleUser->id]);
                }
            }

            Auth::login($user, true);

            return redirect()->route('dashboard');
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Google authentication failed. Please try again.');
        }
    }
}
