<?php

namespace App\Http\Requests;

use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class LoginRequest extends FortifyLoginRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $loginMethod = $this->input('login_method');
        
        // If no login_method provided, try to detect from input
        if (!$loginMethod) {
            if ($this->filled('phone_number') && $this->filled('pin')) {
                $loginMethod = 'phone';
            } elseif ($this->filled('email') && $this->filled('password')) {
                $loginMethod = 'email';
            }
        }

        if ($loginMethod === 'phone') {
            return [
                'login_method' => 'required|in:phone',
                'phone_number' => 'required|string',
                'pin' => 'required|string',
            ];
        } elseif ($loginMethod === 'email') {
            return [
                'login_method' => 'required|in:email',
                'email' => 'required|string|email',
                'password' => 'required|string',
            ];
        }

        // Default: require either phone/PIN or email/password
        return [
            'login_method' => 'required|in:phone,email',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'login_method.required' => 'Please select a login method.',
            'login_method.in' => 'Invalid login method selected.',
            'phone_number.required' => 'The phone number field is required.',
            'pin.required' => 'The PIN field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'The email must be a valid email address.',
            'password.required' => 'The password field is required.',
        ];
    }
}

