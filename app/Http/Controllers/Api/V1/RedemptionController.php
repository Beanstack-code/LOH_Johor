<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Redemption;
use App\Models\RedemptionItem;
use App\Support\ApiLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class RedemptionController extends Controller
{
    public function items(Request $request): JsonResponse
    {
        $sort = (string) $request->get('sort', 'default');
        $inStockOnly = filter_var($request->get('in_stock', false), FILTER_VALIDATE_BOOL);
        $affordableOnly = filter_var($request->get('affordable', false), FILTER_VALIDATE_BOOL);

        $member = null;
        if ($request->bearerToken()) {
            try {
                $payload = JWTAuth::parseToken()->getPayload();
                if ($payload->get('role') === 'member') {
                    $member = Member::find((int) $payload->get('sub'));
                }
            } catch (\Throwable $e) {
                // Keep endpoint usable as public list when token is missing/invalid.
                $member = null;
            }
        }

        if ($affordableOnly && ! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Affordable filter requires a valid member token.',
            ], 401);
        }

        $query = RedemptionItem::query()
            ->where('status', 'active'); // Only inactive is hidden from listing.

        if ($inStockOnly) {
            $query->where('quantity_available', '>', 0);
        }

        if ($affordableOnly && $member) {
            $query->where('required_points', '<=', $member->total_points);
        }

        match ($sort) {
            'points_low_to_high' => $query->orderBy('required_points', 'asc'),
            'points_high_to_low' => $query->orderBy('required_points', 'desc'),
            'name_a_z' => $query->orderBy('name', 'asc'),
            default => $query->orderBy('required_points', 'asc'),
        };

        $items = $query->paginate($request->get('per_page', 15));

        $items->setCollection(
            $items->getCollection()->map(function (RedemptionItem $item) use ($member) {
                $isOutOfStock = (int) $item->quantity_available <= 0;
                $isExpired = (bool) ($item->expiry_date && (string) $item->expiry_date < now()->toDateString());
                $isAffordable = $member ? ((int) $member->total_points >= (int) $item->required_points) : null;

                return [
                    'id' => $item->id,
                    'name' => ApiLocale::resolve($item->name_i18n, (string) $item->name),
                    'description' => ApiLocale::resolve($item->description_i18n, (string) $item->description),
                    'name_i18n' => $item->name_i18n,
                    'description_i18n' => $item->description_i18n,
                    'terms' => ApiLocale::resolve($item->terms_i18n, (string) ($item->terms_and_conditions ?? '')),
                    'terms_i18n' => $item->terms_i18n,
                    'required_points' => (int) $item->required_points,
                    'quantity_available' => (int) $item->quantity_available,
                    'expiry_date' => optional($item->expiry_date)->toDateString(),
                    'status' => $item->status,
                    'image_url' => $item->image_url,
                    'is_out_of_stock' => $isOutOfStock,
                    'is_expired' => $isExpired,
                    'is_affordable' => $isAffordable,
                    'can_redeem' => ! $isOutOfStock && ! $isExpired && ($isAffordable !== false),
                ];
            })
        );

        return $this->paginatedResponse($items, 'items');
    }

    public function redeem(Request $request): JsonResponse
    {
        if ($error = $this->requireRole('member')) {
            return $error;
        }

        $member = $request->user();

        $validator = Validator::make($request->all(), [
            'redemption_item_id' => 'required|integer|exists:redemption_items,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $item = RedemptionItem::find($request->redemption_item_id);

        if ($item->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This item is no longer available for redemption.',
            ], 422);
        }

        if ($item->quantity_available <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This item is out of stock.',
            ], 422);
        }

        if ($item->expiry_date && (string) $item->expiry_date < now()->toDateString()) {
            return response()->json([
                'success' => false,
                'message' => 'This item has expired.',
            ], 422);
        }

        if ($member->total_points < $item->required_points) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient points. You need {$item->required_points} points but have {$member->total_points}.",
                'data' => [
                    'required_points' => $item->required_points,
                    'current_points' => $member->total_points,
                    'shortfall' => $item->required_points - $member->total_points,
                ],
            ], 422);
        }

        DB::beginTransaction();

        try {
            $redemption = Redemption::create([
                'member_id' => $member->id,
                'redemption_item_id' => $item->id,
                'points_used' => $item->required_points,
                'status' => 'pending',
                'reference_number' => 'RDM-'.now()->format('YmdHis').'-'.$member->id,
                'redeemed_at' => now(),
            ]);

            // Reserve the item slot so nobody else can claim the same unit.
            // Points are NOT deducted here — they are deducted only when the
            // merchant or admin marks the claim as completed/given.
            $item->decrement('quantity_available');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Redemption failed. Please try again.',
            ], 500);
        }

        $redemption->load('item');

        return response()->json([
            'success' => true,
            'message' => "Claim submitted! Points will be deducted once the merchant confirms fulfilment.",
            'data' => [
                'redemption' => $redemption,
                'points_used' => $item->required_points,
                'new_total_points' => $member->total_points, // unchanged until completion
            ],
        ], 201);
    }

    public function myRedemptions(Request $request): JsonResponse
    {
        if ($error = $this->requireRole('member')) {
            return $error;
        }

        $member = $request->user();

        $redemptions = Redemption::with('item:id,name,name_ms,name_zh,required_points,image_path')
            ->where('member_id', $member->id)
            ->latest('redeemed_at')
            ->paginate($request->get('per_page', 15));

        $redemptions->setCollection(
            $redemptions->getCollection()->map(function (Redemption $r) {
                $arr = $r->toArray();
                if ($r->item) {
                    $arr['item']['name'] = ApiLocale::resolve($r->item->name_i18n, (string) $r->item->name);
                }

                return $arr;
            })
        );

        return $this->paginatedResponse($redemptions, 'redemptions');
    }
}
