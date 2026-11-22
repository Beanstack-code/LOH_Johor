<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ForgotPasswordController;
use App\Http\Controllers\Api\V1\FundraisingCampaignController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\MerchantDirectoryController;
use App\Http\Controllers\Api\V1\MerchantRedemptionController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PerformanceController;
use App\Http\Controllers\Api\V1\PointConfigController;
use App\Http\Controllers\Api\V1\PlatformBenefitController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PushTokenController;
use App\Http\Controllers\Api\V1\QrController;
use App\Http\Controllers\Api\V1\RedemptionController;
use App\Http\Controllers\Api\V1\SubscribeController;
use App\Http\Controllers\Api\V1\SubscriptionPlanController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ─── Public: Auth ────────────────────────────────────────────
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    // ─── Public: Merchant Directory ──────────────────────────────
    Route::get('merchants', [MerchantDirectoryController::class, 'index']);
    Route::get('merchants/{id}', [MerchantDirectoryController::class, 'show']);
    Route::get('merchant-categories', [MerchantDirectoryController::class, 'categories']);

    // ─── Public: Subscription Plans ──────────────────────────────
    Route::get('subscription-plans', [SubscriptionPlanController::class, 'index']);

    // ─── Public: PayCools webhook (no JWT — verified by RSA signature) ─
    Route::middleware('raw.body')
        ->post('subscription/paycools/webhook', [SubscribeController::class, 'paycoolsWebhook']);
    Route::middleware('raw.body')
        ->post('campaigns/donate/paycools/webhook', [FundraisingCampaignController::class, 'paycoolsWebhook']);

    // ─── Public: Announcements (home banner carousel) ────────────
    Route::get('announcements', [AnnouncementController::class, 'index']);
    Route::get('announcements/{id}', [AnnouncementController::class, 'show']);

    // ─── Public: Fundraising Campaigns ───────────────────────────
    Route::get('campaigns', [FundraisingCampaignController::class, 'index']);
    Route::get('campaigns/{id}', [FundraisingCampaignController::class, 'show']);

    // ─── Public: Redemption Items ────────────────────────────────
    Route::get('redemption-items', [RedemptionController::class, 'items']);

    // ─── Protected (JWT required, member + merchant) ─────────────
    Route::middleware('api.guard')->group(function () {

        // Auth management
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('deactivate', [AuthController::class, 'deactivate']);

        // Donations (login required, no subscription required)
        Route::post('campaigns/{id}/donate', [FundraisingCampaignController::class, 'donate']);
        Route::post('campaigns/{id}/donate/paycools/order', [FundraisingCampaignController::class, 'createPaycoolsOrder']);
        // Polled by the Flutter app after the hosted-checkout WebView closes.
        Route::post('campaigns/{id}/donate/paycools/status', [FundraisingCampaignController::class, 'queryPaycoolsStatus']);
        // Mark a pending order as failed when the user closes/cancels the WebView.
        Route::post('campaigns/{id}/donate/paycools/abandon', [FundraisingCampaignController::class, 'abandonPaycoolsOrder']);
        Route::get('donations', [FundraisingCampaignController::class, 'donations']);

        // Profile
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::post('profile/merchant/cover-image', [ProfileController::class, 'updateMerchantCoverImage']);
        Route::post('profile/merchant/gallery', [ProfileController::class, 'uploadMerchantGalleryImage']);
        Route::post('profile/member/kyc', [ProfileController::class, 'uploadMemberKyc']);
        Route::post('profile/merchant/ssm', [ProfileController::class, 'uploadMerchantSsm']);
        Route::put('change-password', [ProfileController::class, 'changePassword']);

        // Subscriptions
        Route::get('subscription-plans', [SubscriptionPlanController::class, 'index']);
        Route::get('subscription/quote', [SubscribeController::class, 'quote']);
        Route::post('subscription/paycools/order', [SubscribeController::class, 'createPaycoolsOrder']);
        // Polled by the Flutter app after the hosted-checkout WebView closes.
        Route::post('subscription/paycools/status', [SubscribeController::class, 'queryPaycoolsStatus']);
        // Mark a pending order as failed when the user closes/cancels the WebView.
        Route::post('subscription/paycools/abandon', [SubscribeController::class, 'abandonPaycoolsOrder']);
        Route::post('subscribe', [SubscribeController::class, 'store']);
        Route::get('subscription', [SubscribeController::class, 'show']);
        Route::get('payment-transactions', [SubscribeController::class, 'paymentTransactions']);

        // Current points conversion settings for client-side estimate
        Route::get('points-config/current', [PointConfigController::class, 'current']);

        // Notifications inbox (role-aware) + read-state tracking
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read', [NotificationController::class, 'markAsRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::get('platform-benefits', [PlatformBenefitController::class, 'index']);
        Route::post('push/token', [PushTokenController::class, 'store']);
        Route::delete('push/token', [PushTokenController::class, 'destroy']);

        // ─── Core features (active subscription required) ─────────
        Route::middleware('subscription.required')->group(function () {

            // Home dashboard (role-aware)
            Route::get('home', [HomeController::class, 'index']);

            // Transaction history and performance (role-aware)
            Route::get('transactions', [TransactionController::class, 'index']);
            Route::get('performance', [PerformanceController::class, 'index']);

            // ─── Member-only ──────────────────────────────────────
            // Digital Member Card: generate rotating QR token
            Route::get('member/qr-token', [QrController::class, 'getMemberQrToken']);

            // Redemption
            Route::post('redeem', [RedemptionController::class, 'redeem']);
            Route::get('my-redemptions', [RedemptionController::class, 'myRedemptions']);

            // ─── Merchant-only ────────────────────────────────────
            // QR scan flow: scan → get member info → grant points
            Route::post('merchant/scan-qr', [QrController::class, 'scanMemberQr']);
            Route::post('merchant/grant-points', [QrController::class, 'grantPoints']);

            // Merchant redemption item management
            Route::get('merchant/redemption-items', [MerchantRedemptionController::class, 'indexItems']);
            Route::post('merchant/redemption-items', [MerchantRedemptionController::class, 'storeItem']);
            Route::put('merchant/redemption-items/{id}', [MerchantRedemptionController::class, 'updateItem']);

            // Merchant claim (redemption) management
            Route::get('merchant/redemptions', [MerchantRedemptionController::class, 'indexClaims']);
            Route::post('merchant/redemptions/{id}/complete', [MerchantRedemptionController::class, 'completeClaim']);
            Route::post('merchant/redemptions/{id}/reject', [MerchantRedemptionController::class, 'rejectClaim']);

        });
    });
});
