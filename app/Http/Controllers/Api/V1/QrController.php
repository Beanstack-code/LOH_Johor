<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BonusRule;
use App\Models\ConversionSetting;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\PointTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class QrController extends Controller
{
    /**
     * Member: generate a short-lived QR token (5 min JWT).
     * Flutter renders this as a QR code on the Digital Member Card screen.
     */
    public function getMemberQrToken(): JsonResponse
    {
        if ($error = $this->requireRole('member')) {
            return $error;
        }

        $member = auth()->user();
        $member->ensureMembershipId();
        $member->refresh();

        $token = auth('member_api')
            ->setTTL(5)
            ->claims(['type' => 'member_qr'])
            ->login($member);

        return response()->json([
            'success' => true,
            'data' => [
                'qr_token' => $token,
                'expires_in' => 300,
                'member_id' => $member->id,
                'membership_id' => $member->membership_id,
                'full_name' => $member->full_name,
                'total_points' => $member->total_points,
                'status' => $member->status,
            ],
        ]);
    }

    /**
     * Merchant: scan a member QR code (Flutter JWT or admin-printed HMAC token).
     * Returns member info so merchant can review before entering amount.
     */
    public function scanMemberQr(Request $request): JsonResponse
    {
        if ($error = $this->requireRole('merchant')) {
            return $error;
        }

        $merchant = auth()->user();

        $validator = Validator::make($request->all(), [
            'qr_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! $merchant->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => 'Your merchant account must have an active subscription to scan QR codes.',
            ], 403);
        }

        ['member' => $member, 'error' => $resolveError] = $this->resolveMemberFromQrToken($request->qr_token);

        if ($resolveError) {
            return response()->json([
                'success' => false,
                'message' => $resolveError,
            ], 422);
        }

        $member->ensureMembershipId();
        $member->refresh();

        return response()->json([
            'success' => true,
            'data' => [
                'member_id' => $member->id,
                'membership_id' => $member->membership_id,
                'full_name' => $member->full_name,
                'email' => $member->email,
                'total_points' => $member->total_points,
                'status' => $member->status,
                'has_active_subscription' => $member->hasActiveSubscription(),
            ],
        ]);
    }

    /**
     * Merchant: grant points to a member (called after scan-qr confirms identity).
     * Accepts member_id directly -- decoupled from QR so expiry is not a race condition.
     */
    public function grantPoints(Request $request): JsonResponse
    {
        if ($error = $this->requireRole('merchant')) {
            return $error;
        }

        $merchant = auth()->user();

        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer|exists:members,id',
            'currency_amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! $merchant->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => 'Your merchant account must have an active subscription to grant points.',
            ], 403);
        }

        $member = Member::find($request->member_id);

        if (! $member->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => 'This member does not have an active subscription.',
            ], 403);
        }

        $conversion = ConversionSetting::where('is_active', true)
            ->where('effective_date', '<=', now()->toDateString())
            ->latest('effective_date')
            ->first();

        if (! $conversion) {
            return response()->json([
                'success' => false,
                'message' => 'No active points conversion rate is configured.',
            ], 500);
        }

        $bonusRule = BonusRule::where('is_enabled', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->first();

        $multiplier = $bonusRule ? (float) $bonusRule->multiplier : 1.0;
        $basePoints = (float) $request->currency_amount * (float) $conversion->points_per_currency;
        $finalPoints = (int) floor($basePoints * $multiplier);

        if ($finalPoints < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Amount is too low to earn any points.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $transaction = PointTransaction::create([
                'merchant_id' => $merchant->id,
                'member_id' => $member->id,
                'type' => 'allocation',
                'currency_amount' => $request->currency_amount,
                'points' => $finalPoints,
                'reference_number' => 'QR-' . now()->format('YmdHis') . '-' . $member->id,
                'status' => 'completed',
                'occurred_at' => now(),
            ]);

            $member->increment('total_points', $finalPoints);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to grant points. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully granted {$finalPoints} points.",
            'data' => [
                'points_granted' => $finalPoints,
                'currency_amount' => $request->currency_amount,
                'conversion_rate' => $conversion->points_per_currency,
                'bonus_multiplier' => $multiplier,
                'member_new_total' => $member->fresh()->total_points,
                'transaction_id' => $transaction->id,
                'reference_number' => $transaction->reference_number,
            ],
        ]);
    }
}
