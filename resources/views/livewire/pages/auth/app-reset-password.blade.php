<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $broker = 'members';
    public string $emailField = 'email';
    public string $accountLabel = 'Member';
    public bool $completed = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');

        $type = (string) request()->query('type', 'member');
        if ($type === 'merchant') {
            $this->broker = 'merchants';
            $this->emailField = 'company_email';
            $this->accountLabel = 'Merchant';
        }
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $credentials = [
            $this->emailField => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
            'token' => $this->token,
        ];

        $status = Password::broker($this->broker)->reset(
            $credentials,
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'temp_password_issued_at' => null,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __($status));
            return;
        }

        $this->completed = true;
    }
}; ?>

<div>
    <div class="mb-8">
        <div class="mb-6 flex justify-center lg:justify-start">
            <x-application-logo class="h-12 w-12" />
        </div>
        <h1 class="mb-2 text-2xl font-bold text-[#2f3349]">{{ $accountLabel }} Password Reset</h1>
        <p class="text-sm text-[#566a7f]">Set a new password for your {{ $accountLabel }} account.</p>
    </div>

    @if($completed)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            <p class="font-semibold">Password reset successful.</p>
            <p class="mt-1">You can return to the app and sign in with your new password.</p>
        </div>
    @else
        <form wire:submit="resetPassword" class="space-y-5">
            <div>
                <x-input-label for="email" :value="__('Email')" class="pk-label" />
                <x-text-input wire:model="email" id="email" class="pk-input mt-1.5" type="email" name="email" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" :value="__('New Password')" class="pk-label" />
                <x-text-input wire:model="password" id="password" class="pk-input mt-1.5" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm New Password')" class="pk-label" />
                <x-text-input wire:model="password_confirmation" id="password_confirmation" class="pk-input mt-1.5" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <button type="submit" class="w-full pk-btn-primary !py-3 !text-sm !font-medium !normal-case !tracking-normal">
                {{ __('Reset Password') }}
            </button>
        </form>
    @endif
</div>
