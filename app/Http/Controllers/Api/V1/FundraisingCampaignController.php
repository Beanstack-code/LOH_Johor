<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendDonationReceiptEmailJob;
use App\Models\Donation;
use App\Models\FundraisingCampaign;
use App\Models\PaymentTransaction;
use App\Services\MemberPointsService;
use App\Services\PayCoolsClient;
use App\Support\ApiLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Throwable;

class FundraisingCampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $campaigns = FundraisingCampaign::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FundraisingCampaign $c) => $this->formatList($c));

        return response()->json([
            'success' => true,
            'locale' => ApiLocale::get(),
            'data' => $campaigns,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $campaign = FundraisingCampaign::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->find($id);

        if (! $campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'locale' => ApiLocale::get(),
            'data' => $this->formatDetail($campaign),
        ]);
    }

    public function donate(Request $request, int $id): JsonResponse
    {
        if (config('pkpms.donation_payments_required', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Direct donation endpoint is disabled. Please use /campaigns/{id}/donate/paycools/order and complete checkout.',
            ], 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $campaign = $this->findActiveCampaign($id);

        if (! $campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found.',
            ], 404);
        }

        $user = auth()->user();
        $role = JWTAuth::parseToken()->getPayload()->get('role');

        $donorName = $role === 'merchant' ? $user->company_name : $user->full_name;
        $donorEmail = $role === 'merchant' ? $user->company_email : $user->email;

        Donation::create([
            'fundraising_campaign_id' => $campaign->id,
            'member_name' => $donorName,
            'member_email' => $donorEmail,
            'amount' => $validated['amount'],
            'donated_at' => now(),
        ]);

        $campaign->increment('current_collected_amount', $validated['amount']);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your donation!',
        ]);
    }

    public function createPaycoolsOrder(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $campaign = $this->findActiveCampaign($id);
        if (! $campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found.',
            ], 404);
        }

        $user = auth()->user();
        [$donorName, $donorEmail] = $this->resolveDonorIdentity($user);

        $amount = round((float) $request->amount, 2);
        $amountMinor = (int) round($amount * 100);

        try {
            $client = PayCoolsClient::fromConfig();
        } catch (Throwable $e) {
            Log::critical('[Donation PayCools order] PayCools credentials not configured', [
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment gateway is not configured. Please try again later.',
            ], 503);
        }

        $merchantOrderNo = 'don' . $user->id . 'c' . $campaign->id . 't' . time();

        $currency = (string) config('services.paycools.currency', 'MYR');
        $param = [
            'mchOrderId'         => $merchantOrderNo,
            'currency'           => $currency,
            'settlementCurrency' => (string) config('services.paycools.settlement_currency', $currency),
            'countryCode'        => (string) config('services.paycools.country_code', 'MY'),
            'customerName'       => (string) ($donorName ?: 'Donor'),
            'email'              => (string) ($donorEmail ?: ''),
            'mobile'             => $this->normalizeMobile((string) ($user->phone ?? '')),
            'amount'             => $amountMinor,
            'notifyUrl'          => (string) config('services.paycools.notify_url'),
            'redirectUrl'        => (string) config('services.paycools.return_url'),
            'userId'             => (string) $user->id,
            'remark'             => 'Donation: ' . $campaign->campaign_name,
        ];

        try {
            $response = $client->createOrder($param);
        } catch (Throwable $e) {
            Log::error('[Donation PayCools order] Failed to create order', [
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
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
            Log::error('[Donation PayCools order] Missing gateway_order_id or checkout_url in response', [
                'user_id'     => $user->id,
                'campaign_id' => $campaign->id,
                'code'        => $response['code'] ?? null,
                'response'    => $response,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment gateway returned an unexpected response.',
            ], 502);
        }

        PaymentTransaction::create([
            'transaction_type' => 'donation',
            'subscribable_type' => get_class($user),
            'subscribable_id' => $user->id,
            'subscription_plan_id' => null,
            'fundraising_campaign_id' => $campaign->id,
            'provider' => 'paycools',
            'gateway_order_id' => $gatewayOrderId,
            'amount_plan' => $amount,
            'amount_admin_fee' => 0,
            'amount_total' => $amount,
            'currency' => 'MYR',
            'fee_reason' => 'donation',
            'status' => 'pending',
            'donor_name' => $donorName,
            'donor_email' => $donorEmail,
            'provider_payload' => [
                'merchant_order_no' => $merchantOrderNo,
                'create_response'   => $response,
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'checkout_url'     => $checkoutUrl,
                'gateway_order_id' => $gatewayOrderId,
                'amount'           => $amountMinor,
                'currency'         => 'MYR',
            ],
        ]);
    }

    public function queryPaycoolsStatus(Request $request, int $id): JsonResponse
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

        $campaign = $this->findActiveCampaign($id);
        if (! $campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found.',
            ], 404);
        }

        $orderId = $request->gateway_order_id;

        $tx = PaymentTransaction::where('gateway_order_id', $orderId)
            ->where('provider', 'paycools')
            ->where('transaction_type', 'donation')
            ->where('fundraising_campaign_id', $campaign->id)
            ->first();

        if (! $tx) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if ($tx->subscribable_id !== auth()->id() || $tx->subscribable_type !== get_class(auth()->user())) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($tx->status === 'captured') {
            MemberPointsService::awardForPaymentTransaction($tx);

            return response()->json(['success' => true, 'active' => true, 'message' => 'Donation already recorded.']);
        }

        try {
            $client = PayCoolsClient::fromConfig();
            $response = $client->queryOrder($orderId);
        } catch (Throwable $e) {
            Log::error('[Donation PayCools status] Query failed', [
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
            $paymentId = 'unknown_' . $orderId;
        }

        DB::transaction(function () use ($tx, $paymentId, $data) {
            $this->finalizeDonationFromTransaction($tx, $paymentId, $data);
        });

        return response()->json(['success' => true, 'active' => true, 'message' => 'Donation completed successfully.']);
    }

    public function abandonPaycoolsOrder(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gateway_order_id' => 'required|string',
            'reason' => 'nullable|string|max:255',
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
            ->where('transaction_type', 'donation')
            ->where('fundraising_campaign_id', $id)
            ->first();

        if (! $tx) {
            return response()->json(['success' => true]);
        }

        if ($tx->subscribable_id !== auth()->id() || $tx->subscribable_type !== get_class(auth()->user())) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($tx->status === 'pending') {
            $payload = $tx->provider_payload ?? [];
            $payload['abandon_reason'] = $request->input('reason', 'cancelled_by_user');
            $payload['abandoned_at'] = now()->toIso8601String();

            $tx->update([
                'status' => 'failed',
                'provider_payload' => $payload,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function donations(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Read from payment_transactions so users can see the latest payment state
        // even if a donation row was delayed/missed due to webhook timing.
        $donations = PaymentTransaction::query()
            ->with(['campaign:id,campaign_name,campaign_name_ms,campaign_name_zh'])
            ->where('transaction_type', 'donation')
            ->where('subscribable_type', get_class($user))
            ->where('subscribable_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (PaymentTransaction $tx) {
                return [
                    'id' => $tx->id,
                    'campaign_id' => $tx->fundraising_campaign_id,
                    'campaign_name' => ApiLocale::resolve(
                        $tx->campaign?->campaign_name_i18n ?? null,
                        (string) ($tx->campaign?->campaign_name ?? 'Campaign')
                    ),
                    'member_name' => $tx->donor_name,
                    'member_email' => $tx->donor_email,
                    'amount' => $tx->amount_total,
                    'donated_at' => $tx->created_at?->toIso8601String(),
                    'payment_status' => $tx->status,
                    'payment_provider' => $tx->provider,
                    'gateway_order_id' => $tx->gateway_order_id,
                    'gateway_payment_id' => $tx->gateway_payment_id,
                ];
            });

        return response()->json([
            'success' => true,
            'donations' => $donations,
        ]);
    }

    public function paycoolsWebhook(Request $request): JsonResponse
    {
        try {
            $client = PayCoolsClient::fromConfig();
        } catch (Throwable $e) {
            if (app()->isProduction()) {
                Log::critical('[Donation PayCools webhook] PayCools credentials not configured in production', [
                    'message' => $e->getMessage(),
                ]);

                return response()->json(['error' => 'Webhook not configured.'], 503);
            }
            $client = null;
        }

        $rawBody = $request->getContent();

        if ($client !== null) {
            // TODO(paycools-spec): confirm webhook signature header name.
            $sigHeader = $request->header('X-PayCools-Sign');

            if (! $client->verifyWebhookSignature($rawBody, $sigHeader)) {
                Log::warning('[Donation PayCools webhook] Invalid signature', [
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

        // TODO(paycools-spec): confirm event field name and capture-event value.
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
            ->where('transaction_type', 'donation')
            ->first();

        if (! $tx) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        if ($tx->status === 'captured') {
            MemberPointsService::awardForPaymentTransaction($tx);

            return response()->json(['status' => 'already_processed']);
        }

        // TODO(paycools-spec): confirm amount unit (minor vs major).
        $expectedMinor = (int) round((float) $tx->amount_total * 100);
        $rawAmount     = $payload['amount'] ?? null;
        $capturedMinor = $rawAmount !== null ? (int) $rawAmount : null;

        if ($capturedMinor === null || $capturedMinor !== $expectedMinor) {
            Log::error('[Donation PayCools webhook] Amount mismatch', [
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
            return response()->json(['error' => 'Currency mismatch.'], 400);
        }

        DB::transaction(function () use ($tx, $paymentId, $payload) {
            $this->finalizeDonationFromTransaction($tx, (string) $paymentId, $payload);
        });

        return response()->json(['status' => 'ok']);
    }

    private function findActiveCampaign(int $id): ?FundraisingCampaign
    {
        return FundraisingCampaign::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->find($id);
    }

    /**
     * @return array{0:string,1:?string}
     */
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

    private function resolveDonorIdentity(object $user): array
    {
        $role = JWTAuth::parseToken()->getPayload()->get('role');

        $donorName = $role === 'merchant' ? $user->company_name : $user->full_name;
        $donorEmail = $role === 'merchant' ? $user->company_email : $user->email;

        return [$donorName, $donorEmail];
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    private function finalizeDonationFromTransaction(PaymentTransaction $tx, string $paymentId, array $paymentData): void
    {
        $tx->refresh();

        if ($tx->status === 'captured') {
            MemberPointsService::awardForPaymentTransaction($tx);

            return;
        }

        $tx->update([
            'gateway_payment_id' => $paymentId,
            'status' => 'captured',
            'provider_payload' => array_merge($tx->provider_payload ?? [], $paymentData),
        ]);

        $existingDonation = Donation::where('payment_transaction_id', $tx->id)->first();
        if ($existingDonation) {
            SendDonationReceiptEmailJob::dispatch($existingDonation->id);
            MemberPointsService::awardForPaymentTransaction($tx);

            return;
        }

        $donation = Donation::create([
            'fundraising_campaign_id' => $tx->fundraising_campaign_id,
            'member_name' => (string) ($tx->donor_name ?? 'Donor'),
            'member_email' => $tx->donor_email,
            'amount' => $tx->amount_total,
            'donated_at' => now(),
            'payment_transaction_id' => $tx->id,
            'payment_provider' => $tx->provider,
            'gateway_order_id' => $tx->gateway_order_id,
            'gateway_payment_id' => $paymentId,
            'payment_status' => 'captured',
        ]);

        FundraisingCampaign::whereKey($tx->fundraising_campaign_id)
            ->increment('current_collected_amount', (float) $donation->amount);

        SendDonationReceiptEmailJob::dispatch($donation->id);

        MemberPointsService::awardForPaymentTransaction($tx->fresh());
    }

    private function formatList(FundraisingCampaign $c): array
    {
        return [
            'id' => $c->id,
            'campaign_name' => ApiLocale::resolve($c->campaign_name_i18n, (string) $c->campaign_name),
            'organization_name' => ApiLocale::resolve($c->organization_name_i18n, (string) $c->organization_name),
            'description' => ApiLocale::resolve($c->description_i18n, (string) $c->description),
            'sub_description' => ApiLocale::resolve($c->sub_description_i18n, (string) $c->sub_description),
            'cover_image_url' => $c->cover_image_url,
            'target_amount' => $c->target_amount,
            'current_collected_amount' => $c->current_collected_amount,
            'starts_at' => $c->starts_at?->toIso8601String(),
            'ends_at' => $c->ends_at?->toIso8601String(),
            'status' => $c->status,
            'campaign_name_i18n' => $c->campaign_name_i18n,
            'description_i18n' => $c->description_i18n,
        ];
    }

    private function formatDetail(FundraisingCampaign $c): array
    {
        return [
            'id' => $c->id,
            'campaign_name' => ApiLocale::resolve($c->campaign_name_i18n, (string) $c->campaign_name),
            'organization_name' => ApiLocale::resolve($c->organization_name_i18n, (string) $c->organization_name),
            'description' => ApiLocale::resolve($c->description_i18n, (string) $c->description),
            'sub_description' => ApiLocale::resolve($c->sub_description_i18n, (string) $c->sub_description),
            'impact' => ApiLocale::resolve($c->impact_i18n, (string) $c->impact),
            'how_it_helps' => ApiLocale::resolveList($c->how_it_helps_i18n, (array) $c->how_it_helps),
            'cover_image_url' => $c->cover_image_url,
            'target_amount' => $c->target_amount,
            'current_collected_amount' => $c->current_collected_amount,
            'starts_at' => $c->starts_at?->toIso8601String(),
            'ends_at' => $c->ends_at?->toIso8601String(),
            'status' => $c->status,
            'campaign_name_i18n' => $c->campaign_name_i18n,
            'organization_name_i18n' => $c->organization_name_i18n,
            'description_i18n' => $c->description_i18n,
            'sub_description_i18n' => $c->sub_description_i18n,
            'impact_i18n' => $c->impact_i18n,
            'how_it_helps_i18n' => $c->how_it_helps_i18n,
        ];
    }
}
