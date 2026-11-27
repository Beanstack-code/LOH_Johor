<?php

use App\Models\Donation;
use App\Models\FraudAlert;
use App\Models\FundraisingCampaign;
use App\Models\Member;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public array $kpis = [];

    // Member growth trend
    public array $memberGrowthLabels = [];
    public array $memberGrowthSeries = [];

    // Activity & lists
    public array $expiringSubscriptions = [];
    public array $activeCampaigns = [];

    public function mount(): void
    {
        $this->kpis = [
            'total_members' => Member::count(),
            'active_members' => Member::where('status', 'active')->count(),
            'fraud_acknowledged' => FraudAlert::where('status', 'acknowledged')->count(),
            'fraud_open' => FraudAlert::whereIn('status', ['open', 'acknowledged'])->count(),
            'fraud_critical_open' => FraudAlert::where('severity', 'critical')->whereIn('status', ['open', 'acknowledged'])->count(),
            'fraud_high_open' => FraudAlert::where('severity', 'high')->whereIn('status', ['open', 'acknowledged'])->count(),
            'fraud_resolved_today' => FraudAlert::whereIn('status', ['resolved', 'false_positive'])->whereDate('updated_at', now()->toDateString())->count(),
            'fraud_false_positive_today' => FraudAlert::where('status', 'false_positive')->whereDate('updated_at', now()->toDateString())->count(),
            'expiring_subs_count' => Subscription::where('status', 'active')
                ->whereBetween('ends_at', [now(), now()->addDays(30)])
                ->count(),
            'total_donations' => (float) Donation::sum('amount'),
        ];

        // ── Member Registration Trend (last 6 months) ──────────────────────
        $memberTrend = Member::selectRaw("substr(created_at, 1, 7) as month, count(*) as total")
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $this->memberGrowthLabels = $memberTrend->pluck('month')
            ->map(fn ($m) => Carbon::parse($m.'-01')->format('M Y'))->all();
        $this->memberGrowthSeries = $memberTrend->pluck('total')
            ->map(fn ($t) => (int) $t)->all();

        // ── Expiring Subscriptions (next 30 days) ──────────────────────────
        $this->expiringSubscriptions = Subscription::where('status', 'active')
            ->whereBetween('ends_at', [now(), now()->addDays(30)])
            ->with('subscribable')
            ->orderBy('ends_at')
            ->limit(5)
            ->get()
            ->map(function ($sub) {
                $entity = $sub->subscribable;
                $name = $entity ? ($entity->full_name ?? $entity->company_name ?? 'Unknown') : 'Unknown';
                $type = str_contains($sub->subscribable_type, 'Member') ? 'Member' : '';

                return [
                    'name' => $name,
                    'type' => $type,
                    'ends_at' => $sub->ends_at->format('d M Y'),
                    'days_left' => (int) now()->diffInDays($sub->ends_at),
                ];
            })->toArray();

        // ── Active Fundraising Campaigns ───────────────────────────────────
        $this->activeCampaigns = FundraisingCampaign::where('status', 'active')
            ->limit(4)
            ->get()
            ->map(fn ($c) => [
                'name' => $c->campaign_name,
                'target' => (float) $c->target_amount,
                'collected' => (float) $c->current_collected_amount,
                'percent' => $c->target_amount > 0
                    ? min(100, (int) round(($c->current_collected_amount / $c->target_amount) * 100))
                    : 0,
            ])->toArray();
    }
}; ?>

<x-slot name="header">
    <div>
        <h1 class="pk-page-title">{{ __('Admin Dashboard') }}</h1>
        <p class="pk-subtitle">{{ __('Overview statistics, activity and trend monitoring.') }}</p>
    </div>
</x-slot>

<div class="space-y-6">

    {{-- ── Row 1: Primary KPI Cards ──────────────────────────────────────── --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

        {{-- Total Members --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">{{ __('Total Members') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['total_members']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50">
                    <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
            </div>
            <div class="mt-2 flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                    {{ number_format($this->kpis['active_members']) }} active
                </span>
            </div>
        </article>

        {{-- Expiring Subscriptions --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">{{ __('Expiring Subscriptions') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['expiring_subs_count']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50">
                    <svg class="h-5 w-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </span>
            </div>
            <p class="mt-2 text-xs text-slate-400">{{ __('Expiring within 30 days') }}</p>
        </article>

        {{-- Fraud Alerts --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">{{ __('Fraud Alerts (Open)') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['fraud_open']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50">
                    <svg class="h-5 w-5 text-rose-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 3h.01M10.29 3.86l-7.5 13A1 1 0 0 0 3.66 18h16.68a1 1 0 0 0 .87-1.5l-7.5-13a1 1 0 0 0-1.74 0z"/></svg>
                </span>
            </div>
            <div class="mt-2 flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">
                    {{ number_format($this->kpis['fraud_critical_open']) }} critical
                </span>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                    {{ number_format($this->kpis['fraud_high_open']) }} high
                </span>
            </div>
        </article>

        {{-- Fraud Resolved Today --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">{{ __('Fraud Resolved Today') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['fraud_resolved_today']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50">
                    <svg class="h-5 w-5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                </span>
            </div>
            <p class="mt-2 text-xs text-slate-400">{{ __('Resolved + false positives') }}</p>
        </article>

        {{-- Fraud Acknowledged --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">{{ __('Fraud Acknowledged') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['fraud_acknowledged']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50">
                    <svg class="h-5 w-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
            </div>
            <p class="mt-2 text-xs text-slate-400">{{ __('Waiting final resolution') }}</p>
        </article>

        {{-- Fraud False Positive Today --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">{{ __('False Positive Today') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['fraud_false_positive_today']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100">
                    <svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
            </div>
            <p class="mt-2 text-xs text-slate-400">{{ __('Marked as not suspicious today') }}</p>
        </article>

    </section>

    {{-- ── Row 2: Member Growth ─────────────────────────────────────────────────── --}}
    <section class="grid gap-6">
        <article class="pk-card p-5">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-slate-800">{{ __('Member Registration Trend') }}</h2>
                <p class="pk-subtitle text-xs">{{ __('New members registered per month (last 6 months)') }}</p>
            </div>
            <div
                x-data
                x-init="
                    new ApexCharts($el, {
                        chart: { type: 'bar', height: 280, toolbar: { show: false } },
                        series: [{ name: 'New Members', data: @js($this->memberGrowthSeries) }],
                        xaxis: { categories: @js($this->memberGrowthLabels) },
                        colors: ['#0d4f8b'],
                        dataLabels: { enabled: false },
                        plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
                        grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
                        tooltip: { theme: 'light' }
                    }).render()
                "
            ></div>
        </article>
    </section>

    {{-- ── Row 3: Member Growth ───────────────────────────────────────────────── --}}
    <section class="grid gap-6 xl:grid-cols-3">

        {{-- Expiring Subscriptions --}}
        <article class="pk-card p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-800">{{ __('Expiring Soon') }}</h2>
                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{{ __('Next 30 days') }}</span>
            </div>
            @forelse($this->expiringSubscriptions as $sub)
                <div class="flex items-start gap-3 border-b border-slate-100 py-3 last:border-0 last:pb-0">
                    <span @class([
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                        'bg-blue-100 text-blue-700'   => $sub['type'] === 'Member',
                    ])>
                        {{ strtoupper(substr($sub['name'], 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-700">{{ $sub['name'] }}</p>
                        <p class="text-xs text-slate-400">{{ __($sub['type']) }} · {{ __('Expires') }} {{ $sub['ends_at'] }}</p>
                    </div>
                    <span @class([
                        'shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold',
                        'bg-red-50 text-red-600'    => $sub['days_left'] <= 7,
                        'bg-amber-50 text-amber-600' => $sub['days_left'] > 7,
                    ])>
                        {{ $sub['days_left'] }}d
                    </span>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-6 text-center">
                    <svg class="mb-2 h-8 w-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm text-slate-400">{{ __('No subscriptions expiring soon.') }}</p>
                </div>
            @endforelse
        </article>

        {{-- Active Fundraising Campaigns --}}
        <article class="pk-card p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-800">{{ __('Fundraising Campaigns') }}</h2>
                <a href="{{ route('admin.fundraising') }}" class="text-xs font-medium text-[#9a7300] hover:underline">{{ __('View all') }}</a>
            </div>
            @forelse($this->activeCampaigns as $campaign)
                <div class="mb-4 last:mb-0">
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <p class="truncate text-sm font-semibold text-slate-700">{{ $campaign['name'] }}</p>
                        <span class="shrink-0 text-xs font-semibold text-slate-500">{{ $campaign['percent'] }}%</span>
                    </div>
                    <div class="mb-1 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="h-2 rounded-full bg-[#ed1c24] transition-all"
                            style="width: {{ $campaign['percent'] }}%"
                        ></div>
                    </div>
                    <p class="text-xs text-slate-400">
                        RM {{ number_format($campaign['collected'], 2) }} raised of RM {{ number_format($campaign['target'], 2) }}
                    </p>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-6 text-center">
                    <svg class="mb-2 h-8 w-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    <p class="text-sm text-slate-400">{{ __('No active campaigns at the moment.') }}</p>
                </div>
            @endforelse
        </article>

    </section>

</div>
