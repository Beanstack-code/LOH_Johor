<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Forbidden</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md text-center">
            <div class="pk-card p-8">
                <div class="mb-6 flex justify-center">
                    <div class="flex h-20 w-20 items-center justify-center rounded-full bg-rose-100">
                        <svg class="h-10 w-10 text-rose-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                    </div>
                </div>
                
                <h1 class="text-3xl font-bold text-slate-800">403</h1>
                <h2 class="mt-2 text-xl font-semibold text-slate-700">Access Forbidden</h2>
                <p class="mt-3 text-sm text-slate-600">
                    You don't have permission to access this page. Please contact your administrator if you believe this is an error.
                </p>
                
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-xl bg-[#ed1c24] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#ca111b]">
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        Back to Dashboard
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Go to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
