<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-[#2f3349]">{{ __('Dashboard Overview') }}</h2>
                <p class="text-sm text-slate-500">Welcome back, {{ auth()->user()->name }}.</p>
            </article>
            <span class="rounded-xl bg-[#edf4fd] px-3 py-1.5 text-xs font-semibold text-[#0d4f8b]">PKPMS Analytics</span>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="pk-card p-5">
                <p class="text-sm text-slate-500">Total Members</p>
                <p class="mt-2 text-2xl font-semibold text-[#2f3349]">2,468</p>
                <p class="mt-2 text-xs font-medium text-[#0d4f8b]">+8.2% this month</p>
            </article>
            <article class="pk-card p-5">
                <p class="text-sm text-slate-500">Active Cases</p>
                <p class="mt-2 text-2xl font-semibold text-[#2f3349]">132</p>
                <p class="mt-2 text-xs font-medium text-[#ed1c24]">12 urgent items</p>
            </article>
            <article class="pk-card p-5">
                <p class="text-sm text-slate-500">Monthly Contributions</p>
                <p class="mt-2 text-2xl font-semibold text-[#2f3349]">RM 184,320</p>
                <p class="mt-2 text-xs font-medium text-[#0d4f8b]">Target achieved 73%</p>
            </article>
            <article class="pk-card p-5">
                <p class="text-sm text-slate-500">Service Requests</p>
                <p class="mt-2 text-2xl font-semibold text-[#2f3349]">57</p>
                <p class="mt-2 text-xs font-medium text-slate-500">Response SLA 1.2 days</p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <article class="pk-card p-6 xl:col-span-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-[#2f3349]">Activity Trend</h3>
                    <span class="rounded-lg bg-[#fef2f2] px-2.5 py-1 text-xs font-semibold text-[#ed1c24]">Live</span>
                </div>
                <div class="mt-6 grid h-52 grid-cols-12 items-end gap-2">
                    <div class="col-span-1 rounded-t-md bg-[#0d4f8b]/20" style="height: 32%"></div>
                    <div class="col-span-1 rounded-t-md bg-[#0d4f8b]/30" style="height: 48%"></div>
                    <div class="col-span-1 rounded-t-md bg-[#0d4f8b]/35" style="height: 42%"></div>
                    <div class="col-span-1 rounded-t-md bg-[#0d4f8b]/40" style="height: 60%"></div>
                    <div class="col-span-1 rounded-t-md bg-[#ed1c24]/60" style="height: 74%"></div>
                    <div class="col-span-1 rounded-t-md bg-[#0d4f8b]/50" style="height: 68%"></div>
                    <div class="col-span-1 rounded-t-md bg-[#0d4f8b]/30" style="height: 54%"></div>
                    <div class="col-span-1 rounded-t-md bg-[#0d4f8b]/40" style="height: 62%"></div>
                    <div class="col-span-1 rounded-t-md bg-[#0d4f8b]/55" style="height: 71%"></div>
                    <div class="col-span-1 rounded-t-md bg-[#ed1c24]/70" style="height: 79%"></div>
                    <div class="col-span-1 rounded-t-md bg-[#0d4f8b]/45" style="height: 66%"></div>
                    <div class="col-span-1 rounded-t-md bg-[#0d4f8b]/35" style="height: 57%"></div>
                </div>
            </div>

            <article class="pk-card p-6">
                <h3 class="text-base font-semibold text-[#2f3349]">Quick Actions</h3>
                <div class="mt-4 space-y-3">
                    <button class="inline-flex w-full items-center justify-center rounded-xl bg-[#0d4f8b] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0a4274]">
                        Add Member
                    </button>
                    <button class="inline-flex w-full items-center justify-center rounded-xl bg-[#ed1c24] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#ca111b]">
                        Open Support Case
                    </button>
                    <button class="inline-flex w-full items-center justify-center rounded-xl border border-[#dfe1ec] bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-[#f8f9fd]">
                        Generate Report
                    </button>
                </div>
            </article>
        </section>
    </div>
</x-app-layout>
