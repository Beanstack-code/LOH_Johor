<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/jpeg" href="{{ asset('images/loh-johor-logo.jpg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        @php
            $isAppResetPage = request()->routeIs('app.password.reset');
            $appType = request()->query('type', 'member');

            if ($isAppResetPage) {
                $isMerchant = $appType === 'merchant';
                $heroTitle = $isMerchant ? 'Merchant Access' : 'Member Access';
                $heroDescription = $isMerchant
                    ? 'Secure your merchant mobile account to continue scanning member QR codes, granting points, and tracking performance.'
                    : 'Secure your member mobile account to continue earning points, redeeming rewards, and discovering merchants.';
                $featureOne = $isMerchant ? 'Grant Points' : 'Earn Points';
                $featureTwo = $isMerchant ? 'Scan QR Sales' : 'Redeem Rewards';
                $featureThree = $isMerchant ? 'Merchant Insights' : 'Discover Merchants';
            } else {
                $heroTitle = __('Welcome to 柔佛州羅氏公会 Portal');
                $heroDescription = __('Manage your loyalty program, track transactions, and engage with your members all in one place.');
                $featureOne = __('Member Management');
                $featureTwo = __('Transactions');
                $featureThree = __('Analytics');
            }
        @endphp

        <div class="min-h-screen flex">
            <!-- Left Side - Login Form -->
            <div class="relative flex-1 flex items-center justify-center px-6 py-12 bg-white">
                <div class="absolute right-6 top-6 z-10"><x-language-switcher /></div>
                <div class="w-full max-w-md">
                    {{ $slot }}
                </div>
                <div class="absolute bottom-4 left-0 right-0 flex justify-center px-6">
                    <a href="{{ route('privacy-policy') }}" class="text-xs text-slate-500 hover:text-[#9a7300] hover:underline">
                        Privacy Policy
                    </a>
                </div>
            </div>

            <!-- Right Side - Illustration/Branding -->
            <div class="hidden lg:flex lg:flex-1 items-center justify-center bg-gradient-to-br from-[#9a7300] via-[#b8890b] to-[#795b00] p-12">
                <div class="max-w-lg text-center">
                    <div class="mb-8 flex justify-center">
                        <div class="h-32 w-32 rounded-3xl bg-white flex items-center justify-center shadow-2xl">
                            <img src="{{ asset('images/loh-johor-logo.jpg') }}" alt="柔佛州羅氏公会 Logo" class="h-20 w-20 object-contain">
                        </div>
                    </div>
                    <h1 class="text-4xl font-bold text-white mb-4">{{ $heroTitle }}</h1>
                    <p class="text-lg text-white/90 leading-relaxed">
                        {{ $heroDescription }}
                    </p>
                    <div class="mt-12 grid grid-cols-3 gap-6 text-white/80">
                        <div class="flex flex-col items-center">
                            <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <span class="text-sm font-medium">{{ $featureOne }}</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                            <span class="text-sm font-medium">{{ $featureTwo }}</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span class="text-sm font-medium">{{ $featureThree }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
