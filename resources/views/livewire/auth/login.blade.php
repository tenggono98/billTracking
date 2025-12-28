<x-layouts.auth>
    <div class="flex flex-col gap-6" x-data="{ method: 'phone' }">
        <x-auth-header :title="__('Welcome back')" :description="__('Sign in to your account to continue')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <!-- Error Messages -->
        @if ($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800 p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-red-800 dark:text-red-200 mb-2">
                            {{ __('Whoops! Something went wrong.') }}
                        </h3>
                        <ul class="list-disc list-inside space-y-1 text-sm text-red-700 dark:text-red-300">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <!-- Login Method Toggle -->
        <div class="relative flex gap-1 p-1 bg-neutral-100 dark:bg-neutral-800/50 rounded-xl border border-neutral-200 dark:border-neutral-700">
            <button 
                type="button" 
                @click="method = 'phone'" 
                :class="method === 'phone' ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm' : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'" 
                class="relative flex-1 px-4 py-2.5 text-sm font-semibold rounded-lg transition-all duration-200 ease-in-out z-10"
            >
                <div class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                    </svg>
                    <span>{{ __('Phone & PIN') }}</span>
                </div>
            </button>
            <button 
                type="button" 
                @click="method = 'email'" 
                :class="method === 'email' ? 'bg-white dark:bg-neutral-700 text-neutral-900 dark:text-white shadow-sm' : 'text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-white'" 
                class="relative flex-1 px-4 py-2.5 text-sm font-semibold rounded-lg transition-all duration-200 ease-in-out z-10"
            >
                <div class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <span>{{ __('Email & Password') }}</span>
                </div>
            </button>
        </div>

        <!-- Phone Number & PIN Form -->
        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5" x-show="method === 'phone'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 transform translate-y-0" x-transition:leave-end="opacity-0 transform translate-y-2">
            @csrf
            <input type="hidden" name="login_method" value="phone">

            <!-- Phone Number -->
            <div>
                <flux:input
                    name="phone_number"
                    :label="__('Phone number')"
                    :value="(old('phone_number') && strpos(old('phone_number'), '@') === false) ? old('phone_number') : ''"
                    type="text"
                    required
                    autofocus
                    autocomplete="tel"
                    placeholder="085609022799"
                    maxlength="12"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    x-on:input="
                        let value = $event.target.value.replace(/\D/g, '');
                        if (value.length > 0 && !value.startsWith('08')) {
                            if (value.startsWith('8')) {
                                value = '0' + value;
                            } else {
                                value = '08' + value;
                            }
                        }
                        if (value.length > 12) {
                            value = value.substring(0, 12);
                        }
                        $event.target.value = value;
                    "
                    x-on:focus="
                        if (!$event.target.value) {
                            $event.target.value = '08';
                        }
                    "
                    x-on:keypress="
                        if (!/[0-9]/.test($event.key) && !['Backspace', 'Delete', 'Tab', 'Enter'].includes($event.key)) {
                            $event.preventDefault();
                        }
                    "
                    x-on:paste="
                        $event.preventDefault();
                        let paste = ($event.clipboardData || window.clipboardData).getData('text');
                        let value = paste.replace(/\D/g, '');
                        if (value.length > 0 && !value.startsWith('08')) {
                            if (value.startsWith('8')) {
                                value = '0' + value;
                            } else {
                                value = '08' + value;
                            }
                        }
                        if (value.length > 12) {
                            value = value.substring(0, 12);
                        }
                        $event.target.value = value;
                    "
                    class="w-full"
                />
            </div>

            <!-- PIN -->
            <div>
                <flux:input
                    name="pin"
                    :label="__('PIN')"
                    type="password"
                    required
                    autocomplete="off"
                    :placeholder="__('Enter your PIN')"
                    maxlength="6"
                    x-on:input="
                        let value = $event.target.value.replace(/\D/g, '');
                        if (value.length > 6) {
                            value = value.substring(0, 6);
                        }
                        $event.target.value = value;
                    "
                    x-on:keypress="
                        if (!/[0-9]/.test($event.key) && !['Backspace', 'Delete', 'Tab', 'Enter'].includes($event.key)) {
                            $event.preventDefault();
                        }
                    "
                    viewable
                    class="w-full"
                />
            </div>

            <!-- Remember Me -->
            <div class="flex items-center">
                <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />
            </div>

            <!-- Submit Button -->
            <flux:button 
                variant="primary" 
                type="submit" 
                class="w-full py-2.5 font-semibold shadow-sm hover:shadow-md transition-all duration-200" 
                data-test="login-phone-button"
            >
                <span class="flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    {{ __('Sign in') }}
                </span>
            </flux:button>
        </form>

        <!-- Email & Password Form -->
        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5" x-show="method === 'email'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform translate-y-2" x-transition:enter-end="opacity-100 transform translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 transform translate-y-0" x-transition:leave-end="opacity-0 transform translate-y-2">
            @csrf
            <input type="hidden" name="login_method" value="email">

            <!-- Email Address -->
            <div>
                <flux:input
                    name="email"
                    :label="__('Email address')"
                    :value="old('email')"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                    class="w-full"
                />
            </div>

            <!-- Password -->
            <div class="space-y-1">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Enter your password')"
                    viewable
                    class="w-full"
                />
                @if (Route::has('password.request'))
                    <div class="flex justify-end">
                        <flux:link :href="route('password.request')" wire:navigate class="text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-200">
                            {{ __('Forgot password?') }}
                        </flux:link>
                    </div>
                @endif
            </div>

            <!-- Remember Me -->
            <div class="flex items-center">
                <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />
            </div>

            <!-- Submit Button -->
            <flux:button 
                variant="primary" 
                type="submit" 
                class="w-full py-2.5 font-semibold shadow-sm hover:shadow-md transition-all duration-200" 
                data-test="login-email-button"
            >
                <span class="flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    {{ __('Sign in') }}
                </span>
            </flux:button>
        </form>

        <!-- Divider -->
        <div class="relative my-2">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-neutral-200 dark:border-neutral-700"></div>
            </div>
            <div class="relative flex justify-center text-xs">
                <span class="bg-white dark:bg-neutral-900 px-3 text-neutral-500 dark:text-neutral-400 font-medium">{{ __('Or continue with') }}</span>
            </div>
        </div>

        <!-- Google Login -->
        <div>
            <a 
                href="{{ route('auth.google') }}" 
                class="flex w-full items-center justify-center gap-3 rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2.5 text-sm font-medium text-neutral-700 dark:text-neutral-200 shadow-sm hover:bg-neutral-50 dark:hover:bg-neutral-700 hover:shadow-md transition-all duration-200"
            >
                <svg class="w-5 h-5" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <span>{{ __('Continue with Google') }}</span>
            </a>
        </div>

        <!-- Register Link -->
        @if (Route::has('register'))
            <div class="text-center pt-2">
                <p class="text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Don\'t have an account?') }}
                    <flux:link :href="route('register')" wire:navigate class="font-semibold text-neutral-900 dark:text-white hover:underline">
                        {{ __('Sign up') }}
                    </flux:link>
                </p>
            </div>
        @endif
    </div>
</x-layouts.auth>
