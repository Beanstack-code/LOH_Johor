<?php

use App\Models\Donation;
use App\Models\FraudAlert;
use App\Models\FundraisingCampaign;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\PointTransaction;
use App\Models\Redemption;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public array $kpis = [];

    // Points issued over time (existing)
    public array $chartSeries = [];
    public array $chartLabels = [];

    // Member growth trend
    public array $memberGrowthLabels = [];
    public array $memberGrowthSeries = [];

    // Merchant status donut
    public array $merchantStatusData = [];
    public array $merchantStatusLabels = [];

    // Points issued vs redeemed (monthly comparison)
    public array $compareLabels = [];
    public array $compareIssued = [];
    public array $compareRedeemed = [];

    // Activity & lists
    public array $activity = [];
    public array $recentRedemptions = [];
    public array $pendingMerchants = [];
    public array $expiringSubscriptions = [];
    public array $activeCampaigns = [];

    public function mount(): void
    {
        $pointsIssued   = (int) PointTransaction::where('type', 'allocation')->sum('points');
        $pointsRedeemed = (int) Redemption::sum('points_used');

        $this->kpis = [
            'total_members'        => Member::count(),
            'active_members'       => Member::where('status', 'active')->count(),
            'total_merchants'      => Merchant::count(),
            'active_merchants'     => Merchant::where('status', 'active')->count(),
            'pending_merchants'    => Merchant::where('status', 'pending')->count(),
            'points_issued'        => $pointsIssued,
            'points_redeemed'      => $pointsRedeemed,
            'net_points'           => $pointsIssued - $pointsRedeemed,
            'total_transactions'   => PointTransaction::count(),
            'redemptions'          => Redemption::count(),
            'fraud_acknowledged'   => FraudAlert::where('status', 'acknowledged')->count(),
            'fraud_open'           => FraudAlert::whereIn('status', ['open', 'acknowledged'])->count(),
            'fraud_critical_open'  => FraudAlert::where('severity', 'critical')->whereIn('status', ['open', 'acknowledged'])->count(),
            'fraud_high_open'      => FraudAlert::where('severity', 'high')->whereIn('status', ['open', 'acknowledged'])->count(),
            'fraud_resolved_today' => FraudAlert::whereIn('status', ['resolved', 'false_positive'])->whereDate('updated_at', now()->toDateString())->count(),
            'fraud_false_positive_today' => FraudAlert::where('status', 'false_positive')->whereDate('updated_at', now()->toDateString())->count(),
            'expiring_subs_count'  => Subscription::where('status', 'active')
                                        ->whereBetween('ends_at', [now(), now()->addDays(30)])
                                        ->count(),
            'total_donations'      => (float) Donation::sum('amount'),
        ];

        // ── Points Issued Over Time (last 12 active days) ──────────────────
        $trend = PointTransaction::selectRaw("date(occurred_at) as day, sum(points) as total")
            ->where('type', 'allocation')
            ->whereNotNull('occurred_at')
            ->groupBy('day')
            ->orderBy('day')
            ->limit(12)
            ->get();

        $this->chartLabels = $trend->pluck('day')
            ->map(fn ($d) => Carbon::parse($d)->format('d M'))->all();
        $this->chartSeries = $trend->pluck('total')
            ->map(fn ($t) => (int) $t)->all();

        // ── Member Registration Trend (last 6 months) ──────────────────────
        $memberTrend = Member::selectRaw("substr(created_at, 1, 7) as month, count(*) as total")
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $this->memberGrowthLabels = $memberTrend->pluck('month')
            ->map(fn ($m) => Carbon::parse($m . '-01')->format('M Y'))->all();
        $this->memberGrowthSeries = $memberTrend->pluck('total')
            ->map(fn ($t) => (int) $t)->all();

        // ── Merchant Status Breakdown (donut) ──────────────────────────────
        $this->merchantStatusLabels = ['Active', 'Pending', 'Suspended', 'Pending Subscription'];
        $this->merchantStatusData   = [
            Merchant::where('status', 'active')->count(),
            Merchant::where('status', 'pending')->count(),
            Merchant::where('status', 'suspended')->count(),
            Merchant::where('status', 'pending_subscription')->count(),
        ];

        // ── Points Issued vs Redeemed — last 6 months ──────────────────────
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(now()->subMonths($i)->format('Y-m'));
        }

        $issuedByMonth = PointTransaction::selectRaw("substr(occurred_at, 1, 7) as month, sum(points) as total")
            ->where('type', 'allocation')
            ->whereNotNull('occurred_at')
            ->where('occurred_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $redeemedByMonth = Redemption::selectRaw("substr(redeemed_at, 1, 7) as month, sum(points_used) as total")
            ->whereNotNull('redeemed_at')
            ->where('redeemed_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $this->compareLabels   = $months->map(fn ($m) => Carbon::parse($m . '-01')->format('M Y'))->all();
        $this->compareIssued   = $months->map(fn ($m) => (int) ($issuedByMonth[$m]->total ?? 0))->all();
        $this->compareRedeemed = $months->map(fn ($m) => (int) ($redeemedByMonth[$m]->total ?? 0))->all();

        // ── Recent Transaction Activity ─────────────────────────────────────
        $this->activity = PointTransaction::query()
            ->latest('occurred_at')
            ->limit(6)
            ->get()
            ->map(fn ($txn) => [
                'title' => strtoupper($txn->type) . ' #' . $txn->reference_number,
                'meta'  => ($txn->points ?? 0) . ' pts',
                'time'  => optional($txn->occurred_at)->diffForHumans() ?? 'n/a',
            ])->all();

        // ── Recent Redemptions ──────────────────────────────────────────────
        $this->recentRedemptions = Redemption::with(['member:id,full_name', 'item:id,name'])
            ->latest('redeemed_at')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'member' => optional($r->member)->full_name ?? 'Unknown',
                'item'   => optional($r->item)->name ?? 'Unknown',
                'points' => $r->points_used,
                'time'   => optional($r->redeemed_at)->diffForHumans() ?? 'n/a',
            ])->toArray();

        // ── Pending Merchant Approvals ──────────────────────────────────────
        $this->pendingMerchants = Merchant::where('status', 'pending')
            ->latest()
            ->limit(5)
            ->get(['id', 'company_name', 'company_email', 'created_at'])
            ->map(fn ($m) => [
                'id'         => $m->id,
                'name'       => $m->company_name,
                'email'      => $m->company_email,
                'registered' => $m->created_at->format('d M Y'),
            ])->toArray();

        // ── Expiring Subscriptions (next 30 days) ──────────────────────────
        $this->expiringSubscriptions = Subscription::where('status', 'active')
            ->whereBetween('ends_at', [now(), now()->addDays(30)])
            ->with('subscribable')
            ->orderBy('ends_at')
            ->limit(5)
            ->get()
            ->map(function ($sub) {
                $entity = $sub->subscribable;
                $name   = $entity ? ($entity->full_name ?? $entity->company_name ?? 'Unknown') : 'Unknown';
                $type   = str_contains($sub->subscribable_type, 'Member') ? 'Member' : 'Merchant';
                return [
                    'name'      => $name,
                    'type'      => $type,
                    'ends_at'   => $sub->ends_at->format('d M Y'),
                    'days_left' => (int) now()->diffInDays($sub->ends_at),
                ];
            })->toArray();

        // ── Active Fundraising Campaigns ───────────────────────────────────
        $this->activeCampaigns = FundraisingCampaign::where('status', 'active')
            ->limit(4)
            ->get()
            ->map(fn ($c) => [
                'name'      => $c->campaign_name,
                'target'    => (float) $c->target_amount,
                'collected' => (float) $c->current_collected_amount,
                'percent'   => $c->target_amount > 0
                    ? min(100, (int) round(($c->current_collected_amount / $c->target_amount) * 100))
                    : 0,
            ])->toArray();
    }
}; ?>

<x-slot name="header">
    <div>
        <h1 class="pk-page-title">Admin Dashboard</h1>
        <p class="pk-subtitle">Overview statistics, activity and trend monitoring.</p>
    </div>
</x-slot>

<div class="space-y-6">

    {{-- ── Row 1: Primary KPI Cards ──────────────────────────────────────── --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

        {{-- Total Members --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">Total Members</p>
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

        {{-- Total Merchants --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">Total Merchants</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['total_merchants']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50">
                    <svg class="h-5 w-5 text-purple-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </span>
            </div>
            <div class="mt-2 flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                    {{ number_format($this->kpis['active_merchants']) }} active
                </span>
                @if($this->kpis['pending_merchants'] > 0)
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                        {{ number_format($this->kpis['pending_merchants']) }} pending
                    </span>
                @endif
            </div>
        </article>

        {{-- Points Economy --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">Points Issued</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['points_issued']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-50">
                    <svg class="h-5 w-5 text-[#ed1c24]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="mt-2 flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                    {{ number_format($this->kpis['points_redeemed']) }} redeemed
                </span>
            </div>
        </article>

        {{-- Net Points Balance --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">Net Points Balance</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['net_points']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50">
                    <svg class="h-5 w-5 text-teal-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </span>
            </div>
            <p class="mt-2 text-xs text-slate-400">Outstanding loyalty liability</p>
        </article>

        {{-- Total Transactions --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">Total Transactions</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['total_transactions']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50">
                    <svg class="h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </span>
            </div>
            <div class="mt-2">
                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                    {{ number_format($this->kpis['redemptions']) }} redemptions
                </span>
            </div>
        </article>

        {{-- Expiring Subscriptions --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">Expiring Subscriptions</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['expiring_subs_count']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50">
                    <svg class="h-5 w-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </span>
            </div>
            <p class="mt-2 text-xs text-slate-400">Expiring within 30 days</p>
        </article>

        {{-- Pending Merchant Approvals --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">Pending Approvals</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['pending_merchants']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-50">
                    <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="mt-2 text-xs text-slate-400">Merchants awaiting review</p>
        </article>

        {{-- Total Donations --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">Total Donations Raised</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">RM {{ number_format($this->kpis['total_donations'], 2) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-pink-50">
                    <svg class="h-5 w-5 text-pink-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </span>
            </div>
            <p class="mt-2 text-xs text-slate-400">Across all fundraising campaigns</p>
        </article>

        {{-- Fraud Alerts --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">Fraud Alerts (Open)</p>
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
                    <p class="pk-subtitle">Fraud Resolved Today</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['fraud_resolved_today']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50">
                    <svg class="h-5 w-5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                </span>
            </div>
            <p class="mt-2 text-xs text-slate-400">Resolved + false positives</p>
        </article>

        {{-- Fraud Acknowledged --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">Fraud Acknowledged</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['fraud_acknowledged']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50">
                    <svg class="h-5 w-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
            </div>
            <p class="mt-2 text-xs text-slate-400">Waiting final resolution</p>
        </article>

        {{-- Fraud False Positive Today --}}
        <article class="pk-card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="pk-subtitle">False Positive Today</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-800">{{ number_format($this->kpis['fraud_false_positive_today']) }}</p>
                </div>
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100">
                    <svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </span>
            </div>
            <p class="mt-2 text-xs text-slate-400">Marked as not suspicious today</p>
        </article>

    </section>

    {{-- ── Row 2: Points Issued Over Time + Recent Transaction Activity ─── --}}
    <section class="grid gap-6 xl:grid-cols-3">
        <article class="pk-card p-5 xl:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-800">Points Issued Over Time</h2>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Live
                </span>
            </div>
            <div
                x-data
                x-init="
                    new ApexCharts($el, {
                        chart: { type: 'area', height: 300, toolbar: { show: false } },
                        series: [{ name: 'Points', data: @js($this->chartSeries) }],
                        xaxis: { categories: @js($this->chartLabels) },
                        stroke: { curve: 'smooth', width: 3 },
                        colors: ['#ed1c24'],
                        dataLabels: { enabled: false },
                        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
                        grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
                        tooltip: { theme: 'light' }
                    }).render()
                "
            ></div>
        </article>

        <article class="pk-card p-5">
            <h2 class="mb-4 text-base font-semibold text-slate-800">Recent Activities</h2>
            <div class="space-y-3">
                @forelse($this->activity as $item)
                    <div class="rounded-xl border border-slate-100 p-3">
                        <p class="text-sm font-semibold text-slate-700">{{ $item['title'] }}</p>
                        <p class="text-xs text-slate-500">{{ $item['meta'] }} • {{ $item['time'] }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No activity available.</p>
                @endforelse
            </div>
        </article>
    </section>

    {{-- ── Row 3: Member Growth + Merchant Status Breakdown ───────────────── --}}
    <section class="grid gap-6 xl:grid-cols-3">
        <article class="pk-card p-5 xl:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-800">Member Registration Trend</h2>
                    <p class="pk-subtitle text-xs">New members registered per month (last 6 months)</p>
                </div>
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

        <article class="pk-card p-5">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-slate-800">Merchant Status</h2>
                <p class="pk-subtitle text-xs">Distribution by current status</p>
            </div>
            <div
                x-data
                x-init="
                    new ApexCharts($el, {
                        chart: { type: 'donut', height: 260 },
                        series: @js($this->merchantStatusData),
                        labels: @js($this->merchantStatusLabels),
                        colors: ['#22c55e', '#f59e0b', '#ef4444', '#94a3b8'],
                        legend: { position: 'bottom', fontSize: '12px' },
                        dataLabels: { enabled: true },
                        plotOptions: { pie: { donut: { size: '65%' } } },
                        tooltip: { theme: 'light' }
                    }).render()
                "
            ></div>
        </article>
    </section>

    {{-- ── Row 4: Points Issued vs Redeemed + Recent Redemptions ──────────── --}}
    <section class="grid gap-6 xl:grid-cols-3">
        <article class="pk-card p-5 xl:col-span-2">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-slate-800">Points Issued vs Redeemed</h2>
                <p class="pk-subtitle text-xs">Monthly comparison for the last 6 months</p>
            </div>
            <div
                x-data
                x-init="
                    new ApexCharts($el, {
                        chart: { type: 'bar', height: 280, toolbar: { show: false } },
                        series: [
                            { name: 'Issued', data: @js($this->compareIssued) },
                            { name: 'Redeemed', data: @js($this->compareRedeemed) }
                        ],
                        xaxis: { categories: @js($this->compareLabels) },
                        colors: ['#ed1c24', '#0d4f8b'],
                        dataLabels: { enabled: false },
                        plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
                        grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
                        legend: { position: 'top' },
                        tooltip: { theme: 'light' }
                    }).render()
                "
            ></div>
        </article>

        <article class="pk-card p-5">
            <h2 class="mb-4 text-base font-semibold text-slate-800">Recent Redemptions</h2>
            <div class="space-y-3">
                @forelse($this->recentRedemptions as $r)
                    <div class="rounded-xl border border-slate-100 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-700">{{ $r['member'] }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $r['item'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-[#ed1c24]">
                                {{ number_format($r['points']) }} pts
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">{{ $r['time'] }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No redemptions yet.</p>
                @endforelse
            </div>
        </article>
    </section>

    {{-- ── Row 5: Pending Approvals + Expiring Subscriptions + Campaigns ─── --}}
    <section class="grid gap-6 lg:grid-cols-3">

        {{-- Pending Merchant Approvals --}}
        <article class="pk-card p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-800">Pending Approvals</h2>
                <a href="{{ route('admin.merchants') }}" class="text-xs font-medium text-[#ed1c24] hover:underline">View all</a>
            </div>
            @forelse($this->pendingMerchants as $merchant)
                <div class="flex items-start gap-3 border-b border-slate-100 py-3 last:border-0 last:pb-0">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-700">
                        {{ strtoupper(substr($merchant['name'], 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-700">{{ $merchant['name'] }}</p>
                        <p class="truncate text-xs text-slate-400">{{ $merchant['email'] }}</p>
                        <p class="text-xs text-slate-400">Applied {{ $merchant['registered'] }}</p>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-6 text-center">
                    <svg class="mb-2 h-8 w-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm text-slate-400">All caught up! No pending approvals.</p>
                </div>
            @endforelse
        </article>

        {{-- Expiring Subscriptions --}}
        <article class="pk-card p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-800">Expiring Soon</h2>
                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Next 30 days</span>
            </div>
            @forelse($this->expiringSubscriptions as $sub)
                <div class="flex items-start gap-3 border-b border-slate-100 py-3 last:border-0 last:pb-0">
                    <span @class([
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                        'bg-blue-100 text-blue-700'   => $sub['type'] === 'Member',
                        'bg-purple-100 text-purple-700' => $sub['type'] === 'Merchant',
                    ])>
                        {{ strtoupper(substr($sub['name'], 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-700">{{ $sub['name'] }}</p>
                        <p class="text-xs text-slate-400">{{ $sub['type'] }} · Expires {{ $sub['ends_at'] }}</p>
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
                    <p class="text-sm text-slate-400">No subscriptions expiring soon.</p>
                </div>
            @endforelse
        </article>

        {{-- Active Fundraising Campaigns --}}
        <article class="pk-card p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-800">Fundraising Campaigns</h2>
                <a href="{{ route('admin.fundraising') }}" class="text-xs font-medium text-[#ed1c24] hover:underline">View all</a>
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
                    <p class="text-sm text-slate-400">No active campaigns at the moment.</p>
                </div>
            @endforelse
        </article>

    </section>

</div>
