<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');


// Google OAuth
use App\Http\Controllers\Auth\GoogleAuthController;

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    // Bills
    Volt::route('bills', 'bills.index')->name('bills.index');
    Volt::route('bills/create', 'bills.create')->name('bills.create');
    Volt::route('bills/{bill}/edit', 'bills.edit')->name('bills.edit');

    // Dashboard
    Volt::route('dashboard', 'dashboard.index')->name('dashboard');

    // Settings
    Volt::route('settings/ai', 'settings.ai')->name('settings.ai');
    Volt::route('settings/branches', 'settings.branches')->name('settings.branches');
    Volt::route('settings/users', 'settings.users')->name('settings.users');
});
