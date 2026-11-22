<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Volt::route('privacy-policy', 'pages.privacy-policy')->name('privacy-policy');

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('dashboard', 'pages.admin.dashboard')->name('dashboard');

    Volt::route('admin/roles', 'pages.admin.roles')->middleware('permission:roles.view')->name('admin.roles');
    Volt::route('admin/subscription-plans', 'pages.admin.subscription-plans')->middleware('permission:subscriptions.view')->name('admin.subscription-plans');
    Volt::route('admin/members', 'pages.admin.members')->middleware('permission:members.view')->name('admin.members');
    Volt::route('admin/merchants', 'pages.admin.merchants')->middleware('permission:merchants.view')->name('admin.merchants');
    Volt::route('admin/merchant-categories', 'pages.admin.merchant-categories')->middleware('permission:merchant-categories.view')->name('admin.merchant-categories');
    Volt::route('admin/transactions', 'pages.admin.transactions')->middleware('permission:transactions.view')->name('admin.transactions');
    Volt::route('admin/redemptions', 'pages.admin.redemptions')->middleware('permission:redemptions.view')->name('admin.redemptions');
    Volt::route('admin/fraud-alerts', 'pages.admin.fraud-alerts')->middleware('permission:fraud_alerts.view')->name('admin.fraud-alerts');
    Volt::route('admin/points-config', 'pages.admin.points-config')->middleware('permission:points-config.view')->name('admin.points-config');
    Volt::route('admin/redemption-items', 'pages.admin.redemption-items')->middleware('permission:redemption-items.view')->name('admin.redemption-items');
    Volt::route('admin/notifications', 'pages.admin.notifications')->middleware('permission:notifications.view')->name('admin.notifications');
    Volt::route('admin/announcements', 'pages.admin.announcements')->middleware('permission:announcements.view')->name('admin.announcements');
    Volt::route('admin/platform-benefits', 'pages.admin.platform-benefits')->middleware('permission:platform-benefits.view')->name('admin.platform-benefits');
    Volt::route('admin/fundraising', 'pages.admin.fundraising')->middleware('permission:fundraising.view')->name('admin.fundraising');
    
    // QR Code pages
    Volt::route('admin/member-qr', 'pages.admin.member-qr')->middleware('permission:member-qr.view')->name('admin.member-qr');
    Volt::route('admin/merchant-qr', 'pages.admin.merchant-qr')->middleware('permission:merchant-qr.view')->name('admin.merchant-qr');
});

require __DIR__.'/auth.php';
