<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <!-- Logo and Header Text -->
    <div class="mb-8">
        <div class="flex justify-center lg:justify-start mb-6">
            <x-application-logo class="h-12 w-12" />
        </div>
        <h1 class="text-2xl font-bold text-[#2f3349] mb-2">Forgot Password? 🔒</h1>
        <p class="text-sm text-[#566a7f]">Enter your email and we'll send you instructions to reset your password</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="space-y-5">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" class="pk-label" />
            <x-text-input wire:model="email" id="email" class="pk-input mt-1.5" type="email" name="email" required autofocus placeholder="john@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Send Reset Link Button -->
        <button
            type="submit"
            class="w-full pk-btn-primary !py-3 !text-sm !font-medium !normal-case !tracking-normal disabled:cursor-not-allowed disabled:opacity-70"
            wire:loading.attr="disabled"
            wire:target="sendPasswordResetLink"
        >
            <span wire:loading.remove wire:target="sendPasswordResetLink">
                {{ __('Send Reset Link') }}
            </span>
            <span wire:loading wire:target="sendPasswordResetLink" class="inline-flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                </svg>
                {{-- Sending... --}}
            </span>
        </button>

        <!-- Back to Login Link -->
        <div class="text-center">
            <a href="{{ route('login') }}" wire:navigate class="inline-flex items-center text-sm font-medium text-[#566a7f] hover:text-[#9a7300] transition">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                {{ __('Back to login') }}
            </a>
        </div>
    </form>
</div>
