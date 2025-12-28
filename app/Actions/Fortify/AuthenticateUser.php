<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateUser
{
    /**
     * Authenticate the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \App\Models\User|null
     */
    public function authenticate(Request $request)
    {
        \Log::info('AuthenticateUser::authenticate called', ['all_input' => $request->all()]);
        
        // Get login method from flag or detect from input
        $loginMethod = $request->input('login_method');
        
        // If no login_method provided, try to detect from input
        if (!$loginMethod) {
            if ($request->filled('phone_number') && $request->filled('pin')) {
                $loginMethod = 'phone';
            } elseif ($request->filled('email') && $request->filled('password')) {
                $loginMethod = 'email';
            } else {
                throw ValidationException::withMessages([
                    'phone_number' => [__('Please provide either email/password or phone number/PIN.')],
                ]);
            }
        }
        
        // Validate login_method if provided
        if ($loginMethod && !in_array($loginMethod, ['phone', 'email'])) {
            throw ValidationException::withMessages([
                'login_method' => [__('Invalid login method.')],
            ]);
        }

        if ($loginMethod === 'phone') {
            // Phone number and PIN authentication - only validate phone/pin fields
            $request->validate([
                'phone_number' => ['required', 'string'],
                'pin' => ['required', 'string'],
            ], [], [
                'phone_number' => __('phone number'),
                'pin' => __('PIN'),
            ]);

            $user = User::where('phone_number', $request->phone_number)->first();

            if (!$user) {
                throw ValidationException::withMessages([
                    'phone_number' => [__('The provided credentials are incorrect.')],
                ]);
            }

            // Check if user has a PIN set
            if (!$user->pin) {
                throw ValidationException::withMessages([
                    'phone_number' => [__('PIN not set for this account. Please contact administrator.')],
                ]);
            }

            // Verify PIN
            \Log::info('PIN verification', [
                'input_pin' => $request->pin,
                'input_pin_length' => strlen($request->pin),
                'user_has_pin' => !empty($user->pin),
                'pin_check_result' => Hash::check($request->pin, $user->pin),
            ]);
            
            if (!Hash::check($request->pin, $user->pin)) {
                throw ValidationException::withMessages([
                    'phone_number' => [__('The provided credentials are incorrect.')],
                ]);
            }

            return $user;
        } elseif ($loginMethod === 'email') {
            // Email and password authentication - only validate email/password fields
            $request->validate([
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
            ], [], [
                'email' => __('email address'),
                'password' => __('password'),
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => [__('The provided credentials are incorrect.')],
                ]);
            }

            return $user;
        } else {
            // Invalid login method
            throw ValidationException::withMessages([
                'login_method' => [__('Invalid login method.')],
            ]);
        }
    }
}
