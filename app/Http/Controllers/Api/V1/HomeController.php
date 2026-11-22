<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\BonusRule;
use App\Models\ConversionSetting;
use App\Models\PointTransaction;
use App\Support\ApiLocale;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function index(): JsonResponse
    {
        $role = $this->getRole();
        $user = auth()->user();

        if ($role === 'member') {
            return $this->memberHome($user);
        }

        if ($role === 'merchant') {
            return $this->merchantHome($user);
        }

        return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
    }

    private function memberHome($member): JsonResponse
    {
        // Sync stored subscription_state with the live subscriptions table
        // so stale DB values never leak an incorrect status to the app.
        $member->refreshSubscriptionState();
        $member->load('activeSubscription.plan');

        $conversion = ConversionSetting::where('is_active', true)
            ->where('effective_date', '<=', now()->toDateString())
            ->latest('effective_date')
            ->first();

        $bonusRule = BonusRule::where('is_enabled', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->first();

        $recentTransactions = PointTransaction::with('merchant:id,company_name,company_name_ms,company_name_zh')
            ->where('member_id', $member->id)
            ->latest('occurred_at')
            ->limit(5)
            ->get(['id', 'merchant_id', 'type', 'currency_amount', 'points', 'occurred_at', 'status'])
            ->map(fn ($txn) => [
                'id' => $txn->id,
                'merchant_id' => $txn->merchant_id,
                'type' => $txn->type,
                'currency_amount' => $txn->currency_amount,
                'points' => $txn->points,
                'occurred_at' => $txn->occurred_at?->toIso8601String(),
                'status' => $txn->status,
                'merchant' => $txn->merchant ? [
                    'id' => $txn->merchant->id,
                    'company_name' => ApiLocale::resolve($txn->merchant->company_name_i18n, (string) $txn->merchant->company_name),
                    'company_name_i18n' => $txn->merchant->company_name_i18n,
                ] : null,
            ]);

        $banners = Announcement::where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('is_highlighted')
            ->orderByDesc('starts_at')
            ->limit(10)
            ->get()
            ->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => ApiLocale::resolve($a->title_i18n, (string) $a->title),
                'category' => $a->category,
                'cover_image_url' => $a->cover_image_url,
                'starts_at' => $a->starts_at?->toIso8601String(),
                'ends_at' => $a->ends_at?->toIso8601String(),
                'is_highlighted' => (bool) $a->is_highlighted,
                'title_i18n' => $a->title_i18n,
            ]);

        return response()->json([
            'success' => true,
            'locale' => ApiLocale::get(),
            'data' => [
                'role' => 'member',
                'total_points' => $member->total_points,
                'status' => $member->status,
                'subscription_state' => $member->subscription_state,
                'active_subscription' => $member->activeSubscription,
                'conversion_setting' => $conversion ? [
                    'currency_unit' => $conversion->currency_unit,
                    'points_per_currency' => $conversion->points_per_currency,
                ] : null,
                'active_bonus_rule' => $bonusRule ? [
                    'name' => $bonusRule->name,
                    'multiplier' => $bonusRule->multiplier,
                    'ends_at' => $bonusRule->ends_at,
                ] : null,
                'recent_transactions' => $recentTransactions,
                'banners' => $banners,
            ],
        ]);
    }

    private function merchantHome($merchant): JsonResponse
    {
        // Sync stored subscription_state with the live subscriptions table.
        $merchant->refreshSubscriptionState();
        $merchant->load('activeSubscription.plan');

        $totalPointsGranted = PointTransaction::where('merchant_id', $merchant->id)
            ->where('type', 'allocation')
            ->sum('points');

        $totalTransactions = PointTransaction::where('merchant_id', $merchant->id)
            ->where('type', 'allocation')
            ->count();

        $todayGrantedPoints = PointTransaction::where('merchant_id', $merchant->id)
            ->where('type', 'allocation')
            ->whereDate('occurred_at', now()->toDateString())
            ->sum('points');

        $totalCustomers = PointTransaction::where('merchant_id', $merchant->id)
            ->where('type', 'allocation')
            ->distinct('member_id')
            ->count('member_id');

        $recentTransactions = PointTransaction::with('member:id,full_name,email')
            ->where('merchant_id', $merchant->id)
            ->where('type', 'allocation')
            ->latest('occurred_at')
            ->limit(5)
            ->get(['id', 'member_id', 'currency_amount', 'points', 'occurred_at', 'status', 'reference_number']);

        return response()->json([
            'success' => true,
            'locale' => ApiLocale::get(),
            'data' => [
                'role' => 'merchant',
                'status' => $merchant->status,
                'subscription_state' => $merchant->subscription_state,
                'active_subscription' => $merchant->activeSubscription,
                'stats' => [
                    'total_points_granted' => (int) $totalPointsGranted,
                    'today_granted_points' => (int) $todayGrantedPoints,
                    'total_transactions' => $totalTransactions,
                    'total_customers' => $totalCustomers,
                ],
                'recent_transactions' => $recentTransactions,
            ],
        ]);
    }
}
