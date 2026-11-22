<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BonusRule;
use App\Models\ConversionSetting;
use Illuminate\Http\JsonResponse;

class PointConfigController extends Controller
{
    public function current(): JsonResponse
    {
        $conversion = ConversionSetting::where('is_active', true)
            ->where('effective_date', '<=', now()->toDateString())
            ->latest('effective_date')
            ->first();

        if (! $conversion) {
            return response()->json([
                'success' => false,
                'message' => 'No active points conversion rate is configured.',
            ], 404);
        }

        $bonusRule = BonusRule::where('is_enabled', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->first();

        $pointsPerCurrency = (float) $conversion->points_per_currency;
        $bonusMultiplier = $bonusRule ? (float) $bonusRule->multiplier : 1.0;

        // Matches merchant/grant-points formula: floor(amount * rate * multiplier)
        $pointsForOneAmount = (int) floor(1 * $pointsPerCurrency * $bonusMultiplier);

        return response()->json([
            'success' => true,
            'data' => [
                'currency_unit' => $conversion->currency_unit,
                'points_per_currency' => $pointsPerCurrency,
                'bonus_multiplier' => $bonusMultiplier,
                'effective_points_per_currency' => $pointsPerCurrency * $bonusMultiplier,
                'points_for_one_amount' => $pointsForOneAmount,
                'rounding_mode' => 'floor',
                'formula' => 'floor(amount * points_per_currency * bonus_multiplier)',
                'effective_date' => optional($conversion->effective_date)->toDateString(),
                'active_bonus_rule' => $bonusRule ? [
                    'name' => $bonusRule->name,
                    'starts_at' => optional($bonusRule->starts_at)->toIso8601String(),
                    'ends_at' => optional($bonusRule->ends_at)->toIso8601String(),
                ] : null,
            ],
        ]);
    }
}
