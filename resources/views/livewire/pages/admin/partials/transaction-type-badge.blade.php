@php
    $config = match($type ?? '') {
        'allocation'        => ['bg-blue-50 text-blue-700',   'Merchant Points'],
        'redemption'        => ['bg-rose-50 text-rose-700',   'Redemption'],
        'manual_adjustment' => ['bg-violet-50 text-violet-700', 'Manual Adj.'],
        default             => ['bg-slate-100 text-slate-600', ucfirst(str_replace('_', ' ', $type ?? '-'))],
    };
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $config[0] }}">
    {{ $config[1] }}
</span>
