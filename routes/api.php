<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ForgotPasswordController;
use App\Http\Controllers\Api\V1\FundraisingCampaignController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PlatformBenefitController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PushTokenController;
use App\Http\Controllers\Api\V1\QrController;
use App\Http\Controllers\Api\V1\SubscribeController;
use App\Http\Controllers\Api\V1\SubscriptionPlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ─── Public: Auth ────────────────────────────────────────────
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
    Route::post('refresh', [AuthController::class, 'refresh']);

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

    // ─── Protected (JWT required) ─────────────
    Route::middleware('api.guard')->group(function () {

        // Auth management
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('deactivate', [AuthController::class, 'deactivate']);

        // Donations (login required, no subscription required)
        Route::post('campaigns/{id}/donate', [FundraisingCampaignController::class, 'donate']);
        Route::post('campaigns/{id}/donate/paycools/order', [FundraisingCampaignController::class, 'createPaycoolsOrder']);
        Route::post('campaigns/{id}/donate/paycools/status', [FundraisingCampaignController::class, 'queryPaycoolsStatus']);
        Route::post('campaigns/{id}/donate/paycools/abandon', [FundraisingCampaignController::class, 'abandonPaycoolsOrder']);
        Route::get('donations', [FundraisingCampaignController::class, 'donations']);

        // Profile
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/member/kyc', [ProfileController::class, 'uploadMemberKyc']);
        Route::put('change-password', [ProfileController::class, 'changePassword']);

        // Subscriptions
        Route::get('subscription/quote', [SubscribeController::class, 'quote']);
        Route::post('subscription/paycools/order', [SubscribeController::class, 'createPaycoolsOrder']);
        Route::post('subscription/paycools/status', [SubscribeController::class, 'queryPaycoolsStatus']);
        Route::post('subscription/paycools/abandon', [SubscribeController::class, 'abandonPaycoolsOrder']);
        Route::post('subscribe', [SubscribeController::class, 'store']);
        Route::get('subscription', [SubscribeController::class, 'show']);
        Route::get('payment-transactions', [SubscribeController::class, 'paymentTransactions']);

        // Notifications inbox + read-state tracking
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read', [NotificationController::class, 'markAsRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::get('platform-benefits', [PlatformBenefitController::class, 'index']);
        Route::post('push/token', [PushTokenController::class, 'store']);
        Route::delete('push/token', [PushTokenController::class, 'destroy']);

        Route::middleware('subscription.required')->group(function () {
            Route::get('home', [HomeController::class, 'index']);

            // Digital Member Card: generate rotating QR token
            Route::get('member/qr-token', [QrController::class, 'getMemberQrToken']);
        });
    });
});
