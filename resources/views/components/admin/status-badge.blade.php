@props(['status'])

@php
    $s = strtolower(str_replace('_', ' ', (string) $status));

    $classes = match (strtolower((string) $status)) {
        'active', 'approved', 'completed', 'active_subscription', 'fulfilled', 'sent', 'published', 'successful' => 'pk-badge-success',
        'pending', 'pending_subscription', 'pending_approval', 'draft', 'scheduled', 'inactive', 'processing' => 'pk-badge-warning',
        'rejected', 'failed', 'suspended', 'cancelled', 'expired', 'deactivated' => 'pk-badge-danger',
        default => 'pk-badge-warning',
    };
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ ucwords($s) }}
</span>
