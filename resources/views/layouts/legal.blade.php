<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Privacy Policy' }} — {{ config('app.name', '柔佛州羅氏公会 Portal') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/jpeg" href="{{ asset('images/loh-johor-logo.jpg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-700">
        <div class="min-h-screen flex flex-col">
            <header class="bg-white border-b border-slate-200">
                <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
                    <a href="{{ url('/') }}" class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center">
                            <img src="{{ asset('images/loh-johor-logo.jpg') }}" alt="柔佛州羅氏公会 Logo" class="h-7 w-7 object-contain">
                        </div>
                        <span class="font-semibold text-slate-900">柔佛州羅氏公会 Portal</span>
                    </a>
                    <a href="{{ route('login') }}" class="text-sm font-medium text-[#9a7300] hover:underline">
                        Back to Login
                    </a>
                </div>
            </header>

            <main class="flex-1">
                <div class="max-w-3xl mx-auto px-6 py-12">
                    {{ $slot }}
                </div>
            </main>

            <footer class="border-t border-slate-200 bg-white">
                <div class="max-w-5xl mx-auto px-6 py-6 text-center text-xs text-slate-500">
                    &copy; {{ date('Y') }} 柔佛州羅氏公会 Portal. All rights reserved.
                </div>
            </footer>
        </div>
    </body>
</html>
