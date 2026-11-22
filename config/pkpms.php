<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subscription Admin Fee
    |--------------------------------------------------------------------------
    |
    | Charged when a member subscribes for the first time, or re-subscribes
    | after their membership eligibility has been ended (by lapse or admin
    | deactivation) for longer than 'admin_fee_grace_days'.
    |
    | Live values are stored in the `app_settings` table and editable under
    | Admin → Subscription Plan Management. These keys are fallbacks only if a
    | row is missing (e.g. before migrations run).
    |
    */

    'subscription_admin_fee' => 30.00,

    'subscription_admin_fee_grace_days' => 365,

    /*
    |--------------------------------------------------------------------------
    | Require Payment Before Activating Subscriptions
    |--------------------------------------------------------------------------
    |
    | Set PAYMENTS_REQUIRED=true in .env (production) to disable the legacy
    | POST /subscribe direct-activation route.  While false (default / sandbox)
    | the route is available for testing and admin-assigned subscriptions.
    |
    */

    'payments_required' => (bool) env('PAYMENTS_REQUIRED', false),

    /*
    |--------------------------------------------------------------------------
    | Require Payment Before Recording Donations
    |--------------------------------------------------------------------------
    |
    | Donation flow should go through PayCools order/status/webhook by default.
    | Keep this true unless you intentionally want direct database donation
    | writes for local QA.
    |
    */

    'donation_payments_required' => (bool) env('DONATION_PAYMENTS_REQUIRED', true),

    /*
    |--------------------------------------------------------------------------
    | Stale Payment Transaction TTL
    |--------------------------------------------------------------------------
    |
    | Minutes after which a pending gateway transaction that was never
    | captured is marked as failed by the cleanup scheduler.
    | 30 minutes is a safe default for hosted-checkout flows.
    |
    */

    'stale_payment_ttl_minutes' => (int) env('STALE_PAYMENT_TTL_MINUTES', 30),

];
