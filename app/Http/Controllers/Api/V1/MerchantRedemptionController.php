<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\PointTransaction;
use App\Models\Redemption;
use App\Models\RedemptionItem;
use App\Support\ApiLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MerchantRedemptionController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // POST /merchant/redemption-items
    // ─────────────────────────────────────────────────────────────

    /**
     * Merchant creates a new redemption item.
     * Items are created as active and appear in the member rewards catalogue immediately.
     */
    public function storeItem(Request $request): JsonResponse
    {
        if ($error = $this->requireRole('merchant')) {
            return $error;
        }

        /** @var Merchant $merchant */
        $merchant = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'name_ms' => 'nullable|string|max:255',
            'name_zh' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ms' => 'nullable|string',
            'description_zh' => 'nullable|string',
            'required_points' => 'required|integer|min:1',
            'quantity_available' => 'required|integer|min:0',
            'expiry_date' => 'nullable|date|after_or_equal:today',
            'terms_and_conditions' => 'nullable|string|max:5000',
            'terms_and_conditions_ms' => 'nullable|string|max:5000',
            'terms_and_conditions_zh' => 'nullable|string|max:5000',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $imagePath = $request->file('image')->store('redemption-items/merchant', 'public');

        $item = RedemptionItem::create([
            'merchant_id' => $merchant->id,
            'name' => $request->name,
            'name_ms' => $request->name_ms,
            'name_zh' => $request->name_zh,
            'description' => $request->description,
            'description_ms' => $request->description_ms,
            'description_zh' => $request->description_zh,
            'terms_and_conditions' => $request->terms_and_conditions,
            'terms_and_conditions_ms' => $request->terms_and_conditions_ms,
            'terms_and_conditions_zh' => $request->terms_and_conditions_zh,
            'required_points' => (int) $request->required_points,
            'quantity_available' => (int) $request->quantity_available,
            'expiry_date' => $request->expiry_date ?: null,
            'status' => 'active',
            'image_path' => $imagePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Redemption item created and is now live in the catalogue.',
            'data' => $this->formatItem($item),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /merchant/redemption-items
    // ─────────────────────────────────────────────────────────────

    /**
     * Lists all redemption items created by the authenticated merchant.
     */
    public function indexItems(Request $request): JsonResponse
    {
        if ($error = $this->requireRole('merchant')) {
            return $error;
        }

        /** @var Merchant $merchant */
        $merchant = $request->user();

        $items = RedemptionItem::query()
            ->where('merchant_id', $merchant->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        $items->setCollection(
            $items->getCollection()->map(fn (RedemptionItem $item) => $this->formatItem($item))
        );

        return $this->paginatedResponse($items, 'items');
    }

    // ─────────────────────────────────────────────────────────────
    // PUT /merchant/redemption-items/{id}
    // ─────────────────────────────────────────────────────────────

    /**
     * Merchant edits their redemption item. Updates stay active in the catalogue.
     */
    public function updateItem(Request $request, int $id): JsonResponse
    {
        if ($error = $this->requireRole('merchant')) {
            return $error;
        }

        /** @var Merchant $merchant */
        $merchant = $request->user();

        $item = RedemptionItem::where('id', $id)
            ->where('merchant_id', $merchant->id)
            ->first();

        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'name_ms' => 'nullable|string|max:255',
            'name_zh' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ms' => 'nullable|string',
            'description_zh' => 'nullable|string',
            'required_points' => 'sometimes|required|integer|min:1',
            'quantity_available' => 'sometimes|required|integer|min:0',
            'expiry_date' => 'nullable|date|after_or_equal:today',
            'terms_and_conditions' => 'nullable|string|max:5000',
            'terms_and_conditions_ms' => 'nullable|string|max:5000',
            'terms_and_conditions_zh' => 'nullable|string|max:5000',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $request->only([
            'name', 'name_ms', 'name_zh',
            'description', 'description_ms', 'description_zh',
            'terms_and_conditions', 'terms_and_conditions_ms', 'terms_and_conditions_zh',
            'required_points', 'quantity_available', 'expiry_date',
        ]);

        $payload['status'] = 'active';

        if ($request->hasFile('image')) {
            $oldPath = $item->getRawOriginal('image_path');
            $payload['image_path'] = $request->file('image')->store('redemption-items/merchant', 'public');
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $item->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Item updated successfully.',
            'data' => $this->formatItem($item->fresh()),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /merchant/redemptions
    // ─────────────────────────────────────────────────────────────

    /**
     * Lists all member claims (redemptions) for items owned by this merchant.
     */
    public function indexClaims(Request $request): JsonResponse
    {
        if ($error = $this->requireRole('merchant')) {
            return $error;
        }

        /** @var Merchant $merchant */
        $merchant = $request->user();

        // Get IDs of items owned by this merchant
        $itemIds = RedemptionItem::where('merchant_id', $merchant->id)->pluck('id');

        // Optional single-item filter (e.g. ?item_id=5)
        if ($request->filled('item_id')) {
            $requestedId = (int) $request->item_id;
            // Ensure the requested item actually belongs to this merchant
            if (! $itemIds->contains($requestedId)) {
                return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
            }
            $itemIds = collect([$requestedId]);
        }

        $claims = Redemption::query()
            ->with(['member:id,full_name,display_name,email,phone', 'item:id,name,name_ms,name_zh,image_path'])
            ->whereIn('redemption_item_id', $itemIds)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('redeemed_at')
            ->paginate($request->integer('per_page', 20));

        $claims->setCollection(
            $claims->getCollection()->map(fn (Redemption $r) => $this->formatClaim($r))
        );

        return $this->paginatedResponse($claims, 'claims');
    }

    // ─────────────────────────────────────────────────────────────
    // POST /merchant/redemptions/{id}/complete
    // ─────────────────────────────────────────────────────────────

    /**
     * Merchant marks a pending claim as completed and may upload evidence image.
     * Only the merchant who owns the item can complete its claims.
     */
    public function completeClaim(Request $request, int $id): JsonResponse
    {
        if ($error = $this->requireRole('merchant')) {
            return $error;
        }

        /** @var Merchant $merchant */
        $merchant = $request->user();

        $redemption = Redemption::with('item')->find($id);

        if (! $redemption) {
            return response()->json(['success' => false, 'message' => 'Redemption not found.'], 404);
        }

        // Ownership: the item must belong to this merchant
        if ((int) $redemption->item?->merchant_id !== $merchant->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($redemption->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending claims can be completed (current status: '.$redemption->status.').',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'fulfillment_notes' => 'nullable|string|max:1000',
            'evidence_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = [
            'status' => 'completed',
            'fulfilled_by_merchant_id' => $merchant->id,
            'fulfilled_at' => now(),
            'fulfillment_notes' => $request->fulfillment_notes,
        ];

        if ($request->hasFile('evidence_image')) {
            $payload['evidence_image_path'] = $request->file('evidence_image')
                ->store('redemptions/evidence', 'public');
        }

        DB::transaction(function () use ($redemption, $payload) {
            $redemption->update($payload);

            // Deduct points from the member now that the item has been given.
            $member = Member::find($redemption->member_id);
            if ($member) {
                PointTransaction::create([
                    'member_id' => $member->id,
                    'merchant_id' => null,
                    'type' => 'redemption',
                    'currency_amount' => null,
                    'points' => -(int) $redemption->points_used,
                    'reference_number' => $redemption->reference_number,
                    'reason' => 'Redeemed: '.($redemption->item?->name ?? 'item'),
                    'status' => 'completed',
                    'occurred_at' => now(),
                ]);

                $member->decrement('total_points', (int) $redemption->points_used);
            }
        });

        Log::info('[MerchantRedemption] Claim completed — points deducted', [
            'redemption_id' => $redemption->id,
            'merchant_id' => $merchant->id,
            'points_used' => $redemption->points_used,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Redemption marked as completed.',
            'data' => $this->formatClaim($redemption->fresh(['member', 'item'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /merchant/redemptions/{id}/reject
    // ─────────────────────────────────────────────────────────────

    /**
     * Merchant marks a pending claim as not given / rejected.
     * Points were not deducted at claim time — only the reserved catalogue
     * quantity is restored. Optional reason is stored on fulfillment_notes.
     */
    public function rejectClaim(Request $request, int $id): JsonResponse
    {
        if ($error = $this->requireRole('merchant')) {
            return $error;
        }

        /** @var Merchant $merchant */
        $merchant = $request->user();

        $redemption = Redemption::with('item')->find($id);

        if (! $redemption) {
            return response()->json(['success' => false, 'message' => 'Redemption not found.'], 404);
        }

        if ((int) $redemption->item?->merchant_id !== $merchant->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($redemption->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending claims can be rejected (current status: '.$redemption->status.').',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $reason = $request->filled('rejection_reason')
            ? trim((string) $request->rejection_reason)
            : null;

        DB::transaction(function () use ($redemption, $reason) {
            $redemption->update([
                'status' => 'rejected',
                'fulfillment_notes' => $reason,
            ]);

            RedemptionItem::where('id', $redemption->redemption_item_id)
                ->increment('quantity_available');
        });

        Log::info('[MerchantRedemption] Claim rejected — quantity restored', [
            'redemption_id' => $redemption->id,
            'merchant_id' => $merchant->id,
            'points_used' => $redemption->points_used,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Redemption marked as not given.',
            'data' => $this->formatClaim($redemption->fresh(['member', 'item'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function formatItem(RedemptionItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => ApiLocale::resolve($item->name_i18n, (string) $item->name),
            'name_i18n' => $item->name_i18n,
            'description' => ApiLocale::resolve($item->description_i18n, (string) ($item->description ?? '')),
            'description_i18n' => $item->description_i18n,
            'terms' => ApiLocale::resolve($item->terms_i18n, (string) ($item->terms_and_conditions ?? '')),
            'terms_i18n' => $item->terms_i18n,
            'required_points' => (int) $item->required_points,
            'quantity_available' => (int) $item->quantity_available,
            'expiry_date' => optional($item->expiry_date)->toDateString(),
            'status' => $item->status,
            'image_url' => $item->image_url,
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function formatClaim(Redemption $r): array
    {
        $member = $r->member;
        $item = $r->item;

        return [
            'id' => $r->id,
            'reference_number' => $r->reference_number,
            'status' => $r->status,
            'points_used' => (int) $r->points_used,
            'redeemed_at' => $r->redeemed_at?->toIso8601String(),
            'fulfilled_at' => $r->fulfilled_at?->toIso8601String(),
            'fulfillment_notes' => $r->fulfillment_notes,
            'evidence_image_url' => $r->evidence_image_url,
            'member' => $member ? [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'display_name' => $member->display_name,
                'email' => $member->email,
                'phone' => $member->phone,
            ] : null,
            'item' => $item ? [
                'id' => $item->id,
                'name' => ApiLocale::resolve($item->name_i18n, (string) $item->name),
                'image_url' => $item->image_url,
            ] : null,
        ];
    }
}
