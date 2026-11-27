<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <!-- Logo and Welcome Text -->
    <div class="mb-8">
        <div class="flex justify-center lg:justify-start mb-6">
            <x-application-logo class="h-12 w-12" />
        </div>
        <h1 class="text-2xl font-bold text-[#2f3349] mb-2">{{ __('Welcome to 柔佛州羅氏公会! 👋') }}</h1>
        <p class="text-sm text-[#566a7f]">{{ __('Please sign-in to your account and start the adventure') }}</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-5">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" class="pk-label" />
            <x-text-input wire:model="form.email" id="email" class="pk-input mt-1.5" type="email" name="email" required autofocus autocomplete="username" placeholder="john@example.com" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <x-input-label for="password" :value="__('Password')" class="pk-label !mb-0" />
                @if (Route::has('password.request'))
                    <a class="text-xs font-medium text-[#9a7300] hover:text-[#795b00] transition" href="{{ route('password.request') }}" wire:navigate>
                        {{ __('Forgot Password?') }}
                    </a>
                @endif
            </div>

            <x-text-input wire:model="form.password" id="password" class="pk-input"
                            type="password"
                            name="password"
                            required autocomplete="current-password" 
                            placeholder="············" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center">
            <input wire:model="form.remember" id="remember" type="checkbox" class="h-4 w-4 rounded border-[#dfe1ec] text-[#9a7300] focus:ring-2 focus:ring-[#9a7300] focus:ring-offset-0" name="remember">
            <label for="remember" class="ml-2 text-sm font-medium text-[#566a7f] cursor-pointer">
                {{ __('Remember Me') }}
            </label>
        </div>

        <!-- Login Button -->
        <button type="submit" class="w-full pk-btn-primary !py-3 !text-sm !font-medium !normal-case !tracking-normal">
            {{ __('Sign In') }}
        </button>
    </form>

    {{-- Register Link --}}
    {{-- @if (Route::has('register'))
        <div class="mt-6 text-center">
            <p class="text-sm text-[#566a7f]">
                New on our platform? 
                <a href="{{ route('register') }}" wire:navigate class="font-semibold text-[#9a7300] hover:text-[#795b00] transition">
                    Create an account
                </a>
            </p>
        </div>
    @endif --}}

    {{-- Divider --}}
    {{-- <div class="relative my-6">
        <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-[#dfe1ec]"></div>
        </div>
        <div class="relative flex justify-center text-xs">
            <span class="bg-white px-4 text-[#98a0b4] font-medium">or</span>
        </div>
    </div> --}}

    {{-- Social Login Buttons --}}
    {{-- <div class="grid grid-cols-3 gap-3">
        <button type="button" class="flex items-center justify-center rounded-xl border border-[#dfe1ec] bg-white px-4 py-2.5 text-sm font-medium text-[#566a7f] transition hover:bg-[#f7f8fc] hover:border-[#d0d3e0]">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
        </button>
        <button type="button" class="flex items-center justify-center rounded-xl border border-[#dfe1ec] bg-white px-4 py-2.5 text-sm font-medium text-[#566a7f] transition hover:bg-[#f7f8fc] hover:border-[#d0d3e0]">
            <svg class="w-5 h-5" fill="#1877F2" viewBox="0 0 24 24">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
            </svg>
        </button>
        <button type="button" class="flex items-center justify-center rounded-xl border border-[#dfe1ec] bg-white px-4 py-2.5 text-sm font-medium text-[#566a7f] transition hover:bg-[#f7f8fc] hover:border-[#d0d3e0]">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
            </svg>
        </button>
    </div> --}}
</div>
