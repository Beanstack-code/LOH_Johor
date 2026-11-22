<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendSubscriptionReceiptEmailJob;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\MemberPointsService;
use App\Services\PayCoolsClient;
use App\Services\SubscriptionPricingService;
use App\Support\ApiLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Throwable;

class SubscribeController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // GET /subscription/quote?plan_id=
    // ─────────────────────────────────────────────────────────────

    /**
     * Returns a server-computed cost breakdown before payment.
     * Flutter uses this for display only; the server NEVER trusts amounts from
     * the client when creating a gateway order.
     */
    public function quote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        $pricing = app(SubscriptionPricingService::class)
            ->pricingForSubscribable($user, $plan);

        return response()->json([
            'success' => true,
            'data' => $pricing,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /subscription/paycools/order
    // ─────────────────────────────────────────────────────────────

    /**
     * Create a PayCools order server-side with the authoritative total.
     * Returns { checkout_url, gateway_order_id, amount, currency } so the
     * Flutter app can open the hosted-checkout URL in an in-app WebView.
     */
    public function createPaycoolsOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
            'quote_hash' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();
        $role = JWTAuth::parseToken()->getPayload()->get('role');
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        if (! $plan->is_active) {
            return response()->json(['success' => false, 'message' => 'This plan is no longer available.'], 422);
        }

        if (($plan->audience !== 'both') && $plan->audience !== $role) {
            return response()->json(['success' => false, 'message' => 'This plan is not available for your account type.'], 422);
        }

        if ($user->hasActiveSubscription()) {
            return response()->json(['success' => false, 'message' => 'You already own this access.'], 422);
        }

        $pricing = app(SubscriptionPricingService::class)
            ->pricingForSubscribable($user, $plan);

        // Optional: verify quote_hash to detect stale-UI submissions.
        if ($request->filled('quote_hash')) {
            if (! app(SubscriptionPricingService::class)->verifyQuoteHash($user, $plan, $request->quote_hash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pricing has changed since the quote was shown. Please refresh and try again.',
                ], 409);
            }
        }

        try {
            $client = PayCoolsClient::fromConfig();
        } catch (Throwable $e) {
            Log::critical('[PayCools order] PayCools credentials not configured', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment gateway is not configured. Please try again later.',
            ], 503);
        }

        $merchantOrderNo = 'sub' . $user->id . 'p' . $plan->id . 't' . time();

        $customerName = $role === 'merchant' ? (string) ($user->company_name ?? '') : (string) ($user->full_name ?? '');
        $customerEmail = $role === 'merchant' ? (string) ($user->company_email ?? '') : (string) ($user->email ?? '');
        $param = [
            'mchOrderId'         => $merchantOrderNo,
            'currency'           => $pricing['currency'],
            'settlementCurrency' => (string) config('services.paycools.settlement_currency', $pricing['currency']),
            'countryCode'        => (string) config('services.paycools.country_code', 'MY'),
            'customerName'       => $customerName !== '' ? $customerName : 'Customer',
            'email'              => $customerEmail,
            'mobile'             => $this->normalizeMobile((string) ($user->phone ?? '')),
            'amount'             => $pricing['total_minor'],
            'notifyUrl'          => (string) config('services.paycools.notify_url'),
            'redirectUrl'        => (string) config('services.paycools.return_url'),
            'userId'             => (string) $user->id,
            'remark'             => $plan->name . ' subscription',
        ];

        try {
            $response = $client->createOrder($param);
        } catch (Throwable $e) {
            Log::error('[PayCools order] Failed to create order', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to start payment at the moment. Please try again shortly.',
            ], 502);
        }

        // Parse checkout fields directly (PayCools v2 success code is 10000, not 1000).
        $data = is_array($response['data'] ?? null) ? $response['data'] : $response;
        $gatewayOrderId = (string) ($data['checkoutId'] ?? $data['orderId'] ?? $data['gatewayOrderId'] ?? '');
        $checkoutUrl    = (string) ($data['cashierUrl'] ?? $data['checkoutUrl'] ?? $data['payUrl'] ?? $data['redirectUrl'] ?? '');

        if ($gatewayOrderId === '' || $checkoutUrl === '') {
            Log::error('[PayCools order] Missing gateway_order_id or checkout_url in response', [
                'user_id'  => $user->id,
                'plan_id'  => $plan->id,
                'code'     => $response['code'] ?? null,
                'response' => $response,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment gateway returned an unexpected response.',
            ], 502);
        }

        // Persist a pending transaction row so the webhook can locate it.
        PaymentTransaction::create([
            'subscribable_type' => get_class($user),
            'subscribable_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'provider'             => 'paycools',
            'gateway_order_id'     => $gatewayOrderId,
            'amount_plan'          => $pricing['plan_amount'],
            'amount_admin_fee'     => $pricing['admin_fee'],
            'amount_total'         => $pricing['total'],
            'currency'             => $pricing['currency'],
            'fee_reason'           => $pricing['fee_reason'],
            'status'               => 'pending',
            'provider_payload'     => [
                'merchant_order_no' => $merchantOrderNo,
                'create_response'   => $response,
            ],
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'checkout_url'     => $checkoutUrl,
                'gateway_order_id' => $gatewayOrderId,
                'amount'           => $pricing['total_minor'],
                'currency'         => $pricing['currency'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /subscription/paycools/status  (JWT protected)
    // ─────────────────────────────────────────────────────────────

    /**
     * Polled by the Flutter app after the hosted-checkout WebView closes.
     * Queries PayCools for the order's current status; if paid, activates
     * the subscription inside a DB transaction. Idempotent — safe to be
     * called alongside the webhook.
     */
    public function queryPaycoolsStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gateway_order_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $orderId = $request->gateway_order_id;

        $tx = PaymentTransaction::where('gateway_order_id', $orderId)
            ->where('provider', 'paycools')
            ->first();

        if (! $tx) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($tx->subscribable_id !== auth()->id() || $tx->subscribable_type !== get_class(auth()->user())) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        // Already captured — nothing to do.
        if ($tx->status === 'captured') {
            SendSubscriptionReceiptEmailJob::dispatch($tx->id);
            MemberPointsService::awardForPaymentTransaction($tx);

            return response()->json(['success' => true, 'active' => true, 'message' => 'Subscription already active.']);
        }

        try {
            $client = PayCoolsClient::fromConfig();
            $response = $client->queryOrder($orderId);
        } catch (Throwable $e) {
            Log::error('[PayCools status] Query failed', [
                'order_id' => $orderId,
                'user_id'  => auth()->id(),
                'message'  => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Status check failed.'], 502);
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : $response;
        $status = strtoupper((string) ($data['status'] ?? $data['tradeStatus'] ?? ''));
        $firstTx = is_array($data['transactionList'] ?? null) ? ($data['transactionList'][0] ?? []) : [];
        $paymentId = (string) ($data['paymentId'] ?? $data['tradeNo'] ?? ($firstTx['transactionId'] ?? ''));

        $isPaid = in_array($status, ['COMPLETED', 'PAID', 'SUCCESS', 'CAPTURED'], true);

        if (! $isPaid) {
            return response()->json(['success' => true, 'active' => false, 'status' => $status]);
        }

        if ($paymentId === '') {
            Log::warning('[PayCools status] Paid response missing payment id', [
                'order_id' => $orderId,
                'response' => $response,
            ]);
            $paymentId = 'unknown_' . $orderId;
        }

        DB::transaction(function () use ($tx, $paymentId, $data) {
            $this->activateFromTransaction($tx, $paymentId, $data);
        });

        return response()->json(['success' => true, 'active' => true, 'message' => 'Subscription activated.']);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /subscription/paycools/abandon  (JWT protected)
    // ─────────────────────────────────────────────────────────────

    /**
     * Called by the client when the user closes/cancels the hosted-checkout
     * WebView, so the pending row is marked failed immediately.
     *
     * Only transitions pending → failed; already-captured rows are unaffected
     * so a late webhook can still win if the cancel was a false positive.
     */
    public function abandonPaycoolsOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gateway_order_id' => 'required|string',
            'reason'           => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tx = PaymentTransaction::where('gateway_order_id', $request->gateway_order_id)
            ->where('provider', 'paycools')
            ->first();

        if (! $tx) {
            return response()->json(['success' => true]);
        }

        if ($tx->subscribable_id !== auth()->id() || $tx->subscribable_type !== get_class(auth()->user())) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($tx->status === 'pending') {
            $payload                   = $tx->provider_payload ?? [];
            $payload['abandon_reason'] = $request->input('reason', 'cancelled_by_user');
            $payload['abandoned_at'] = now()->toIso8601String();

            $tx->update([
                'status' => 'failed',
                'provider_payload' => $payload,
            ]);

            Log::info('[PayCools abandon] Transaction marked failed', [
                'order_id' => $tx->gateway_order_id,
                'user_id'  => auth()->id(),
                'reason'   => $payload['abandon_reason'],
            ]);
        }

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /subscription/paycools/webhook  (public, no JWT auth)
    // ─────────────────────────────────────────────────────────────

    /**
     * PayCools posts signed webhook events here when an order is paid.
     * - Raw body is used for RSA verification (raw.body middleware bypasses Laravel JSON parsing).
     * - Idempotent: repeated delivery of the same event is a no-op.
     * - Subscription is activated ONLY after the signature is verified.
     * - Production requires PayCools public key + app_id (fail-closed if unset).
     */
    public function paycoolsWebhook(Request $request): JsonResponse
    {
        try {
            $client = PayCoolsClient::fromConfig();
        } catch (Throwable $e) {
            if (app()->isProduction()) {
                Log::critical('[PayCools webhook] PayCools credentials not configured in production', [
                    'message' => $e->getMessage(),
                ]);

                return response()->json(['error' => 'Webhook not configured.'], 503);
            }

            // In non-production with no keys, accept the event for local dev only.
            $client = null;
        }

        $rawBody = $request->getContent();

        if ($client !== null) {
            // TODO(paycools-spec): confirm exact webhook signature header name.
            // verifyWebhookSignature also falls back to an embedded `sign` field in the body envelope.
            $sigHeader = $request->header('X-PayCools-Sign');

            if (! $client->verifyWebhookSignature($rawBody, $sigHeader)) {
                Log::warning('[PayCools webhook] Invalid signature', [
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Invalid signature.'], 400);
            }

            $payload = $client->decodeWebhookParam($rawBody);
        } else {
            $payload = $request->json()->all();
            $payload = isset($payload['param']) && is_string($payload['param'])
                ? (json_decode($payload['param'], true) ?: [])
                : $payload;
        }

        // TODO(paycools-spec): confirm the event-type field name and which
        // value indicates a successful capture.
        $event = strtoupper((string) ($payload['event'] ?? $payload['eventType'] ?? $payload['status'] ?? ''));
        $captureEvents = ['PAYMENT.CAPTURED', 'PAID', 'SUCCESS', 'CAPTURED', 'TRADE_SUCCESS'];

        if (! in_array($event, $captureEvents, true)) {
            return response()->json(['status' => 'ignored']);
        }

        $orderId   = $payload['orderId'] ?? $payload['gatewayOrderId'] ?? null;
        $paymentId = $payload['paymentId'] ?? $payload['tradeNo'] ?? null;

        if (! $orderId || ! $paymentId) {
            return response()->json(['error' => 'Missing order_id or payment_id.'], 400);
        }

        $tx = PaymentTransaction::where('gateway_order_id', $orderId)
            ->where('provider', 'paycools')
            ->first();

        if (! $tx) {
            Log::error('[PayCools webhook] No transaction for order', ['order_id' => $orderId]);

            return response()->json(['error' => 'Order not found.'], 404);
        }

        if ($tx->status === 'captured') {
            SendSubscriptionReceiptEmailJob::dispatch($tx->id);
            MemberPointsService::awardForPaymentTransaction($tx);

            return response()->json(['status' => 'already_processed']);
        }

        // Verify captured amount matches the authoritative amount we stored.
        // TODO(paycools-spec): confirm amount unit (minor vs major).
        $expectedMinor = (int) round((float) $tx->amount_total * 100);
        $rawAmount     = $payload['amount'] ?? null;
        $capturedMinor = $rawAmount !== null ? (int) $rawAmount : null;

        if ($capturedMinor === null || $capturedMinor !== $expectedMinor) {
            Log::error('[PayCools webhook] Amount mismatch', [
                'order_id'        => $orderId,
                'expected_minor'  => $expectedMinor,
                'captured_minor'  => $capturedMinor,
                'tx_amount_total' => $tx->amount_total,
            ]);

            return response()->json(['error' => 'Amount mismatch.'], 400);
        }

        $paymentCurrency = isset($payload['currency'])
            ? strtoupper((string) $payload['currency'])
            : null;

        if ($paymentCurrency !== null && $paymentCurrency !== strtoupper((string) $tx->currency)) {
            Log::error('[PayCools webhook] Currency mismatch', [
                'order_id' => $orderId,
                'expected' => $tx->currency,
                'captured' => $paymentCurrency,
            ]);

            return response()->json(['error' => 'Currency mismatch.'], 400);
        }

        DB::transaction(function () use ($tx, $paymentId, $payload) {
            $this->activateFromTransaction($tx, (string) $paymentId, $payload);
        });

        return response()->json(['status' => 'ok']);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /subscription
    // ─────────────────────────────────────────────────────────────

    public function show(): JsonResponse
    {
        $user = auth()->user();

        $subscription = $user->activeSubscription;

        if (! $subscription) {
            return response()->json([
                'success' => true,
                'data' => null,
                'has_active_subscription' => false,
                'message' => "You don't have an active subscription yet. Subscribe to a plan to unlock all features.",
            ]);
        }

        $subscription->load('plan.descriptions');

        return response()->json([
            'success' => true,
            'has_active_subscription' => true,
            'data' => $subscription,
        ]);
    }

    /**
     * GET /payment-transactions
     * Returns all gateway payment records (subscription + donation) for current user.
     * No provider filter — historical Razorpay rows stay visible alongside PayCools rows.
     */
    public function paymentTransactions(): JsonResponse
    {
        $user = auth()->user();

        $rows = PaymentTransaction::query()
            ->with([
                'plan:id,name',
                'campaign:id,campaign_name,campaign_name_ms,campaign_name_zh',
            ])
            ->where('subscribable_type', get_class($user))
            ->where('subscribable_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (PaymentTransaction $tx): array {
                $title = $tx->transaction_type === 'donation'
                    ? ApiLocale::resolve(
                        $tx->campaign?->campaign_name_i18n ?? null,
                        (string) ($tx->campaign?->campaign_name ?? 'Donation')
                    )
                    : (string) ($tx->plan?->name ?? 'Subscription');

                return [
                    'id' => $tx->id,
                    'transaction_type' => $tx->transaction_type,
                    'title' => $title,
                    'provider' => $tx->provider,
                    'status' => $tx->status,
                    'currency' => $tx->currency,
                    'amount_total' => $tx->amount_total,
                    'amount_plan' => $tx->amount_plan,
                    'amount_admin_fee' => $tx->amount_admin_fee,
                    'fee_reason' => $tx->fee_reason,
                    'gateway_order_id' => $tx->gateway_order_id,
                    'gateway_payment_id' => $tx->gateway_payment_id,
                    'created_at' => $tx->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'payments' => $rows,
        ]);
    }

    private function normalizeMobile(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '' || $digits === null) {
            return '';
        }
        if (str_starts_with($digits, '60')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '060')) {
            $digits = substr($digits, 1);
        }
        if (! str_starts_with($digits, '0')) {
            $digits = '0' . $digits;
        }
        return $digits;
    }

    // ─────────────────────────────────────────────────────────────
    // POST /subscribe  (legacy / gated by payments.required flag)
    // ─────────────────────────────────────────────────────────────

    /**
     * Direct-activation endpoint, kept for admin-assigned subscriptions and
     * sandbox testing.  When PAYMENTS_REQUIRED=true in .env this returns 403
     * so production cannot bypass the payment flow.
     */
    public function store(Request $request): JsonResponse
    {
        if (config('pkpms.payments_required', false)) {
            return response()->json([
                'success' => false,
                'message' => 'Direct subscription activation is disabled. Please use the payment flow.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();
        $role = JWTAuth::parseToken()->getPayload()->get('role');
        $plan = SubscriptionPlan::findOrFail($request->subscription_plan_id);

        if (! $plan->is_active) {
            return response()->json(['success' => false, 'message' => 'This plan is no longer available.'], 422);
        }

        if (($plan->audience !== 'both') && $plan->audience !== $role) {
            return response()->json(['success' => false, 'message' => 'This plan is not available for your account type.'], 422);
        }

        if ($user->hasActiveSubscription()) {
            return response()->json(['success' => false, 'message' => 'You already own this access.'], 422);
        }

        $pricing = app(SubscriptionPricingService::class)
            ->pricingForSubscribable($user, $plan);

        $adminFeeCharged = $pricing['admin_fee'] > 0;

        $subscription = Subscription::create([
            'subscription_plan_id' => $plan->id,
            'subscribable_type'    => get_class($user),
            'subscribable_id'      => $user->id,
            'starts_at'            => now(),
            'status'               => 'active',
            'admin_fee_amount'     => $pricing['admin_fee'],
            'notes'                => 'Subscribed via mobile app (direct)',
        ]);

        $user->refreshSubscriptionState();
        $user->recordSubscriptionActivated($adminFeeCharged);

        if (empty($user->membership_id)) {
            $user->membership_id = get_class($user)::generateMembershipId();
            $user->save();
        }

        $subscription->load('plan.descriptions');

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated successfully.',
            'data' => [
                'subscription' => $subscription,
                'membership_id' => $user->fresh()->membership_id,
                'admin_fee_charged' => $pricing['admin_fee'],
            ],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────
    // Shared activation logic (used by webhook + verify)
    // ─────────────────────────────────────────────────────────────

    /**
     * Activates a subscription from a PaymentTransaction.
     * Idempotent: if the subscribable already has an active subscription the
     * method returns silently.  Must be called inside a DB transaction.
     *
     * @param  array<string, mixed>  $paymentData  Raw gateway payload (may be empty for verify path).
     */
    private function activateFromTransaction(
        PaymentTransaction $tx,
        string $paymentId,
        array $paymentData,
    ): void {
        $tx->update([
            'gateway_payment_id' => $paymentId,
            'status'             => 'captured',
            'provider_payload'   => array_merge($tx->provider_payload ?? [], $paymentData),
        ]);
        SendSubscriptionReceiptEmailJob::dispatch($tx->id);

        // Award member points as soon as payment is captured (idempotent).
        MemberPointsService::awardForPaymentTransaction($tx->fresh());

        // Resolve the subscribable (Member / Merchant).
        $user = $tx->subscribable_type::findOrFail($tx->subscribable_id);
        $plan = SubscriptionPlan::findOrFail($tx->subscription_plan_id);

        if ($user->hasActiveSubscription()) {
            return; // Guard against double-activation.
        }

        $adminFeeAmount  = (float) $tx->amount_admin_fee;
        $adminFeeCharged = $adminFeeAmount > 0;

        Subscription::create([
            'subscription_plan_id' => $plan->id,
            'subscribable_type'    => $tx->subscribable_type,
            'subscribable_id'      => $tx->subscribable_id,
            'starts_at'            => now(),
            'status'               => 'active',
            'admin_fee_amount'     => $adminFeeAmount,
            'notes'                => 'Activated via PayCools payment ' . $paymentId,
        ]);

        $user->refreshSubscriptionState();
        $user->recordSubscriptionActivated($adminFeeCharged);

        // Assign membership ID on first subscription.
        if (empty($user->membership_id)) {
            $user->membership_id = $user::generateMembershipId();
            $user->save();
        }
    }
}
