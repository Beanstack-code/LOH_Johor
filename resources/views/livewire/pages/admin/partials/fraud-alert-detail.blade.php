<div class="grid gap-3 text-xs sm:grid-cols-2">
    <dl class="space-y-2 rounded-lg border border-slate-100 bg-slate-50/80 p-3">
        <div class="flex flex-col gap-0.5">
            <dt class="font-medium text-slate-500">{{ __('Alert ID') }}</dt>
            <dd class="font-mono text-slate-800">{{ $alert->id }}</dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="font-medium text-slate-500">{{ __('Rule code') }}</dt>
            <dd class="font-mono text-slate-800">{{ $alert->rule?->code ?? '—' }}</dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="font-medium text-slate-500">{{ __('Rule name') }}</dt>
            <dd class="text-slate-800">{{ $alert->rule?->name ?? 'Unknown Rule' }}</dd>
        </div>
        <div class="flex flex-col gap-0.5 sm:col-span-2">
            <dt class="font-medium text-slate-500">{{ __('Rule description') }}</dt>
            <dd class="text-slate-700">{{ $alert->rule?->description ?: '—' }}</dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="font-medium text-slate-500">{{ __('Risk score') }}</dt>
            <dd class="font-semibold text-slate-800">{{ $alert->risk_score }}</dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="font-medium text-slate-500">{{ __('Fingerprint') }}</dt>
            <dd class="break-all font-mono text-[11px] leading-snug text-slate-700">{{ $alert->fingerprint }}</dd>
        </div>
    </dl>
    <dl class="space-y-2 rounded-lg border border-slate-100 bg-slate-50/80 p-3">
        <div class="flex flex-col gap-0.5">
            <dt class="font-medium text-slate-500">{{ __('Entity type') }}</dt>
            <dd class="text-slate-800">{{ __($this->entityTypeLabel($alert)) }}</dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="font-medium text-slate-500">{{ __('Entity type (class)') }}</dt>
            <dd class="break-all font-mono text-[11px] text-slate-700">{{ $alert->entity_type ?: '—' }}</dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="font-medium text-slate-500">{{ __('Entity ID') }}</dt>
            <dd class="font-mono text-slate-800">{{ $alert->entity_id ?? '—' }}</dd>
        </div>
        <div class="flex flex-col gap-0.5 sm:col-span-2">
            <dt class="font-medium text-slate-500">{{ __('Entity label') }}</dt>
            <dd class="text-slate-800">{{ $this->entityDisplay($alert) }}</dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="font-medium text-slate-500">{{ __('Triggered at') }}</dt>
            <dd class="text-slate-800">{{ optional($alert->triggered_at)->format('d M Y H:i:s') ?? '—' }}</dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="font-medium text-slate-500">{{ __('Created / updated') }}</dt>
            <dd class="text-slate-800">{{ optional($alert->created_at)->format('d M Y H:i') ?? '—' }} · {{ optional($alert->updated_at)->format('d M Y H:i') ?? '—' }}</dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="font-medium text-slate-500">{{ __('Resolved by') }}</dt>
            <dd class="text-slate-800">{{ $alert->resolver?->name ?? '—' }}</dd>
        </div>
        <div class="flex flex-col gap-0.5">
            <dt class="font-medium text-slate-500">{{ __('Resolved at') }}</dt>
            <dd class="text-slate-800">{{ optional($alert->resolved_at)->format('d M Y H:i:s') ?? '—' }}</dd>
        </div>
    </dl>
</div>
<div class="mt-3">
    <p class="text-xs font-medium text-slate-500">{{ __('Detection context') }}</p>
    @if($alert->payload_json && count($alert->payload_json) > 0)
        <dl class="mt-1.5 space-y-2 rounded-lg border border-slate-100 bg-slate-50/80 p-2.5">
            @foreach($alert->payload_json as $key => $value)
                <div class="text-xs">
                    <dt class="font-medium text-slate-500">{{ Str::headline(str_replace('_', ' ', (string) $key)) }}</dt>
                    <dd class="mt-0.5 text-slate-800">
                        @if(is_array($value))
                            <dl class="mt-1 space-y-1 border-l-2 border-slate-200 pl-2">
                                @foreach($value as $subKey => $subValue)
                                    <div class="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-2">
                                        <span class="text-slate-500">{{ Str::headline(str_replace('_', ' ', (string) $subKey)) }}</span>
                                        <span class="font-medium text-slate-800 sm:text-right">
                                            @if(is_array($subValue))
                                                <span class="text-slate-500">{{ __('(nested)') }}</span>
                                            @else
                                                {{ $subValue }}
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </dl>
                        @else
                            <span class="font-medium">{{ $value }}</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    @else
        <p class="mt-1 rounded-lg border border-dashed border-slate-200 bg-white p-3 text-xs text-slate-500">{{ __('No extra detection fields stored for this alert.') }}</p>
    @endif
</div>
