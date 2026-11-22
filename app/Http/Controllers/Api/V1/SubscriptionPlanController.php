<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanDescription;
use App\Support\ApiLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SubscriptionPlan::with('descriptions')
            ->where('is_active', true);

        if ($request->has('audience') && in_array($request->audience, ['member', 'merchant'])) {
            $query->where(function ($q) use ($request) {
                $q->where('audience', $request->audience)
                    ->orWhere('audience', 'both');
            });
        }

        $plans = $query->orderBy('display_order')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => [
                'id' => $plan->id,
                'name' => ApiLocale::resolve($plan->name_i18n, (string) $plan->name),
                'audience' => $plan->audience,
                'price' => $plan->price,
                'discount_type' => $plan->discount_type,
                'discount_value' => $plan->discount_value,
                'final_price' => $plan->final_price,
                'display_order' => $plan->display_order,
                'name_i18n' => $plan->name_i18n,
                'descriptions' => $plan->descriptions->map(fn (SubscriptionPlanDescription $d) => [
                    'id' => $d->id,
                    'description' => ApiLocale::resolve($d->description_i18n, (string) $d->description),
                    'display_order' => $d->display_order,
                    'description_i18n' => $d->description_i18n,
                ]),
            ]);

        return response()->json([
            'success' => true,
            'locale' => ApiLocale::get(),
            'data' => $plans,
        ]);
    }
}
