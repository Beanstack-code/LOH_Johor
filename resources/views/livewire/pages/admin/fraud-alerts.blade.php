<?php

use App\Models\AuditLog;
use App\Models\FraudAlert;
use App\Models\FraudRule;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\RedemptionItem;
use App\Services\FraudDetectionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $severityFilter = '';
    public string $statusFilter = '';
    public string $ruleFilter = '';
    public string $search = '';
    public string $sortField = 'triggered_at';
    public string $sortDirection = 'desc';

    public string $successMessage = '';
    public string $toastType = 'success';

    public function with(): array
    {
        $allowedSortFields = ['triggered_at', 'risk_score', 'severity', 'status'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'triggered_at';
        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        $alertsQuery = FraudAlert::query()
            ->with(['rule', 'entity', 'resolver'])
            ->when($this->severityFilter !== '', fn ($query) => $query->where('severity', $this->severityFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->ruleFilter !== '', fn ($query) => $query->where('fraud_rule_id', (int) $this->ruleFilter))
            ->when($this->search !== '', function ($query) {
                $query->where(function ($sub) {
                    $sub->where('entity_type', 'like', '%'.$this->search.'%')
                        ->orWhere('entity_id', 'like', '%'.$this->search.'%')
                        ->orWhereHas('rule', fn ($ruleQuery) => $ruleQuery->where('name', 'like', '%'.$this->search.'%'));
                });
            });

        $alerts = $alertsQuery
            ->orderBy($sortField, $sortDirection)
            ->orderByDesc('id')
            ->paginate(12);

        return [
            'alerts' => $alerts,
            'rules' => FraudRule::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'kpis' => [
                'open' => FraudAlert::query()->where('status', 'open')->count(),
                'acknowledged' => FraudAlert::query()->where('status', 'acknowledged')->count(),
                'resolved' => FraudAlert::query()->where('status', 'resolved')->count(),
                'false_positive' => FraudAlert::query()->where('status', 'false_positive')->count(),
                'critical' => FraudAlert::query()->where('severity', 'critical')->whereIn('status', ['open', 'acknowledged'])->count(),
                'high' => FraudAlert::query()->where('severity', 'high')->whereIn('status', ['open', 'acknowledged'])->count(),
            ],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSeverityFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedRuleFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowed = ['triggered_at', 'risk_score', 'severity', 'status'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function sortIcon(string $field): string
    {
        if ($this->sortField !== $field) {
            return '';
        }

        return $this->sortDirection === 'asc' ? '↑' : '↓';
    }

    public function toggleRule(int $id): void
    {
        abort_unless(auth()->user()->can('fraud_alerts.edit'), 403);

        $rule = FraudRule::findOrFail($id);
        $before = $rule->is_enabled;
        $rule->is_enabled = ! $rule->is_enabled;
        $rule->save();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'fraud_rule_toggled',
            'entity_type' => FraudRule::class,
            'entity_id' => $rule->id,
            'before_data' => ['is_enabled' => $before],
            'after_data' => ['is_enabled' => $rule->is_enabled],
        ]);

        $this->showToast($rule->is_enabled ? 'Rule enabled.' : 'Rule disabled.');
    }

    public function runDetection(FraudDetectionService $service): void
    {
        abort_unless(auth()->user()->can('fraud_alerts.edit'), 403);

        $summary = $service->detect();
        $this->showToast(
            "Detection completed. {$summary['alerts_created_or_updated']} alert(s) updated.",
            'success'
        );
    }

    public function acknowledgeAlert(int $id): void
    {
        $this->updateAlertStatus($id, 'acknowledged');
    }

    public function resolveAlert(int $id): void
    {
        $this->updateAlertStatus($id, 'resolved');
    }

    public function markFalsePositive(int $id): void
    {
        $this->updateAlertStatus($id, 'false_positive');
    }

    private function updateAlertStatus(int $id, string $status): void
    {
        abort_unless(auth()->user()->can('fraud_alerts.resolve'), 403);

        $allowed = ['acknowledged', 'resolved', 'false_positive'];
        if (! in_array($status, $allowed, true)) {
            return;
        }

        $alert = FraudAlert::findOrFail($id);
        $before = $alert->status;
        $alert->status = $status;

        if (in_array($status, ['resolved', 'false_positive'], true)) {
            $alert->resolved_by = Auth::id();
            $alert->resolved_at = now();
        } else {
            $alert->resolved_by = null;
            $alert->resolved_at = null;
        }

        $alert->save();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'fraud_alert_status_updated',
            'entity_type' => FraudAlert::class,
            'entity_id' => $alert->id,
            'before_data' => ['status' => $before],
            'after_data' => ['status' => $alert->status],
        ]);

        $this->showToast('Alert status updated successfully.');
    }

    public function severityBadgeClass(string $severity): string
    {
        return match (strtolower($severity)) {
            'critical' => 'pk-badge-danger',
            'high' => 'pk-badge-warning',
            'medium' => 'pk-badge-success',
            default => 'pk-badge-warning',
        };
    }

    public function entityDisplay(FraudAlert $alert): string
    {
        $entity = $alert->entity;

        if ($entity instanceof Member) {
            return trim($entity->full_name.' '.($entity->membership_id ? '('.$entity->membership_id.')' : ''));
        }

        if ($entity instanceof Merchant) {
            return trim($entity->company_name.' '.($entity->membership_id ? '('.$entity->membership_id.')' : ''));
        }

        if ($entity instanceof RedemptionItem) {
            return (string) ($entity->name ?: 'Reward Item');
        }

        return class_basename((string) $alert->entity_type);
    }

    public function entityTypeLabel(FraudAlert $alert): string
    {
        $type = (string) $alert->entity_type;

        return match ($type) {
            Member::class => 'Member',
            Merchant::class => 'Merchant',
            RedemptionItem::class => 'Redemption Item',
            default => class_basename($type) ?: 'Unknown',
        };
    }

    private function showToast(string $message, string $type = 'success'): void
    {
        $this->successMessage = __($message);
        $this->toastType = $type === 'error' ? 'error' : 'success';
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="pk-page-title">{{ __('Fraud Alert Indicator') }}</h1>
            <p class="pk-subtitle">{{ __('Monitor anomaly signals, manage fraud rules, and triage suspicious activity quickly.') }}</p>
        </div>
        @can('fraud_alerts.edit')
        <button type="button" wire:click="runDetection" class="pk-btn-primary">{{ __('Run Detection') }}</button>
        @endcan
    </div>
</x-slot>

<div class="space-y-6">
    <div
        x-data="{ message: $wire.entangle('successMessage'), type: $wire.entangle('toastType') }"
        x-cloak
        class="pointer-events-none z-[150]"
        style="position: fixed; right: 0.75rem; bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px)); width: calc(100vw - 1.5rem); max-width: 24rem;"
    >
        <div
            x-show="message"
            x-transition.opacity.duration.200ms
            class="pointer-events-auto rounded-xl border p-4 text-sm shadow-lg"
            :style="type === 'error'
                ? 'border-color:#fecdd3;background-color:#fff1f2;color:#9f1239;'
                : 'border-color:#bbf7d0;background-color:#f0fdf4;color:#166534;'"
            x-init="$watch('message', value => { if (value) { setTimeout(() => message = '', 2800) } })"
        >
            <div class="flex items-center justify-between gap-3">
                <p x-text="message"></p>
                <button type="button" class="text-xs font-semibold" :style="type === 'error' ? 'color:#be123c;' : 'color:#15803d;'" @click="message = ''">
                    Dismiss
                </button>
            </div>
        </div>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <article class="pk-card p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Open') }}</p>
            <p class="mt-2 text-xl font-semibold text-slate-800">{{ $kpis['open'] }}</p>
        </article>
        <article class="pk-card p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Acknowledged') }}</p>
            <p class="mt-2 text-xl font-semibold text-slate-800">{{ $kpis['acknowledged'] }}</p>
        </article>
        <article class="pk-card p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Resolved') }}</p>
            <p class="mt-2 text-xl font-semibold text-slate-800">{{ $kpis['resolved'] }}</p>
        </article>
        <article class="pk-card p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('False Positive') }}</p>
            <p class="mt-2 text-xl font-semibold text-slate-800">{{ $kpis['false_positive'] }}</p>
        </article>
        <article class="pk-card p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('Critical Active') }}</p>
            <p class="mt-2 text-xl font-semibold text-rose-700">{{ $kpis['critical'] }}</p>
        </article>
        <article class="pk-card p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('High Active') }}</p>
            <p class="mt-2 text-xl font-semibold text-amber-700">{{ $kpis['high'] }}</p>
        </article>
    </section>

    <section class="pk-filter-bar">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search rule or entity...') }}" class="pk-input w-full sm:max-w-sm">
        <select wire:model.live="severityFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">{{ __('All Severities') }}</option>
            <option value="critical">{{ __('Critical') }}</option>
            <option value="high">{{ __('High') }}</option>
            <option value="medium">{{ __('Medium') }}</option>
        </select>
        <select wire:model.live="statusFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">{{ __('All Statuses') }}</option>
            <option value="open">{{ __('Open') }}</option>
            <option value="acknowledged">{{ __('Acknowledged') }}</option>
            <option value="resolved">{{ __('Resolved') }}</option>
            <option value="false_positive">{{ __('False Positive') }}</option>
        </select>
        <select wire:model.live="ruleFilter" class="pk-input w-full sm:max-w-sm">
            <option value="">{{ __('All Rules') }}</option>
            @foreach($rules as $rule)
                <option value="{{ $rule->id }}">{{ $rule->name }}</option>
            @endforeach
        </select>
    </section>

    <section class="grid items-start gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <div class="pk-mobile-only space-y-3">
                @foreach($alerts as $alert)
                    <article class="pk-card p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800">{{ $alert->rule?->name ?? 'Unknown Rule' }}</p>
                                <p class="mt-0.5 font-mono text-[11px] text-slate-500">{{ $alert->rule?->code ?? '—' }}</p>
                                <p class="mt-1 text-xs text-slate-600">{{ $this->entityDisplay($alert) }}</p>
                            </div>
                            <x-admin.status-badge :status="$alert->status" />
                        </div>
                        <dl class="mt-3 space-y-1 text-xs text-slate-600">
                            <div class="flex justify-between gap-3"><dt>{{ __('Alert ID') }}</dt><dd class="font-mono font-semibold text-slate-700">#{{ $alert->id }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>{{ __('Type') }}</dt><dd class="font-semibold text-slate-700">{{ __($this->entityTypeLabel($alert)) }}</dd></div>
                            <div class="flex justify-between gap-3">
                                <dt>{{ __('Severity') }}</dt>
                                <dd><span class="{{ $this->severityBadgeClass($alert->severity) }}">{{ __(Str::title($alert->severity)) }}</span></dd>
                            </div>
                            <div class="flex justify-between gap-3"><dt>{{ __('Risk score') }}</dt><dd class="font-semibold text-slate-800">{{ $alert->risk_score }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>{{ __('Triggered') }}</dt><dd>{{ optional($alert->triggered_at)->format('d M Y H:i') }}</dd></div>
                            @if($alert->resolved_at)
                                <div class="flex justify-between gap-3"><dt>{{ __('Resolved') }}</dt><dd>{{ optional($alert->resolved_at)->format('d M Y H:i') }}</dd></div>
                            @endif
                        </dl>
                        <details class="mt-3 rounded-xl border border-slate-200 bg-slate-50/60">
                            <summary class="cursor-pointer select-none px-3 py-2 text-xs font-semibold text-slate-700">{{ __('Full alert details') }}</summary>
                            <div class="border-t border-slate-200 px-3 py-3">
                                @include('livewire.pages.admin.partials.fraud-alert-detail', ['alert' => $alert])
                            </div>
                        </details>
                        @can('fraud_alerts.resolve')
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <button class="pk-btn-secondary px-2 py-2 text-xs" wire:click="acknowledgeAlert({{ $alert->id }})">{{ __('Ack') }}</button>
                            <button class="pk-btn-secondary px-2 py-2 text-xs" wire:click="resolveAlert({{ $alert->id }})">{{ __('Resolve') }}</button>
                            <button class="pk-btn-secondary px-2 py-2 text-xs text-rose-600" wire:click="markFalsePositive({{ $alert->id }})">{{ __('False+') }}</button>
                        </div>
                        @endcan
                    </article>
                @endforeach
            </div>

            <div class="pk-desktop-only" x-data="{ openAlertId: null }">
                <x-admin.table.wrapper>
                    <table class="pk-table w-full">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap">{{ __('Rule') }}</th>
                                <th class="whitespace-nowrap">{{ __('Type') }}</th>
                                <th class="whitespace-nowrap">{{ __('Entity') }}</th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('severity')" class="inline-flex items-center gap-1">{{ __('Severity') }} <span>{{ $this->sortIcon('severity') }}</span></button>
                                </th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('risk_score')" class="inline-flex items-center gap-1">{{ __('Risk') }} <span>{{ $this->sortIcon('risk_score') }}</span></button>
                                </th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('status')" class="inline-flex items-center gap-1">{{ __('Status') }} <span>{{ $this->sortIcon('status') }}</span></button>
                                </th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('triggered_at')" class="inline-flex items-center gap-1">{{ __('Triggered') }} <span>{{ $this->sortIcon('triggered_at') }}</span></button>
                                </th>
                                <th class="whitespace-nowrap">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($alerts as $alert)
                                <tr class="align-top">
                                    <td>
                                        <div class="max-w-[14rem] whitespace-normal">
                                            <span class="font-medium text-slate-800">{{ $alert->rule?->name ?? 'Unknown Rule' }}</span>
                                            <p class="mt-0.5 font-mono text-[11px] text-slate-500">{{ $alert->rule?->code ?? '—' }}</p>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap font-semibold text-slate-700">{{ __($this->entityTypeLabel($alert)) }}</td>
                                    <td>
                                        <div class="max-w-[12rem] whitespace-normal text-sm text-slate-700">{{ $this->entityDisplay($alert) }}</div>
                                        <p class="mt-0.5 font-mono text-[10px] text-slate-400">#{{ $alert->entity_id ?? '—' }}</p>
                                    </td>
                                    <td class="whitespace-nowrap"><span class="{{ $this->severityBadgeClass($alert->severity) }}">{{ __(Str::title($alert->severity)) }}</span></td>
                                    <td class="whitespace-nowrap font-semibold text-slate-800">{{ $alert->risk_score }}</td>
                                    <td class="whitespace-nowrap"><x-admin.status-badge :status="$alert->status" /></td>
                                    <td class="whitespace-nowrap">{{ optional($alert->triggered_at)->format('d M Y H:i') }}</td>
                                    <td class="whitespace-nowrap">
                                        <div class="flex flex-col items-start gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                                            <button
                                                type="button"
                                                class="pk-btn-secondary px-3 py-1.5 text-xs"
                                                @click="openAlertId = openAlertId === {{ $alert->id }} ? null : {{ $alert->id }}"
                                            >
                                                <span x-show="openAlertId !== {{ $alert->id }}">{{ __('Show details') }}</span>
                                                <span x-show="openAlertId === {{ $alert->id }}">{{ __('Hide details') }}</span>
                                            </button>
                                            @can('fraud_alerts.resolve')
                                            <div class="flex flex-wrap items-center gap-2">
                                                <button type="button" class="pk-btn-secondary px-3 py-1.5 text-xs" wire:click="acknowledgeAlert({{ $alert->id }})">{{ __('Acknowledge') }}</button>
                                                <button type="button" class="pk-btn-secondary px-3 py-1.5 text-xs" wire:click="resolveAlert({{ $alert->id }})">{{ __('Resolve') }}</button>
                                                <button type="button" class="pk-btn-secondary px-3 py-1.5 text-xs text-rose-600" wire:click="markFalsePositive({{ $alert->id }})">{{ __('False Positive') }}</button>
                                            </div>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="openAlertId === {{ $alert->id }}" x-collapse class="bg-slate-50/90">
                                    <td colspan="8" class="border-t border-slate-200 p-4">
                                        @include('livewire.pages.admin.partials.fraud-alert-detail', ['alert' => $alert])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-admin.table.wrapper>
            </div>

            <div class="mt-3">{{ $alerts->links() }}</div>
        </div>

        <aside class="pk-card p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-800">{{ __('Fraud Rules') }}</h2>
                <span class="text-xs text-slate-500">{{ $rules->count() }} total</span>
            </div>
            <div class="mt-4 max-h-[calc(100vh-12rem)] space-y-3 overflow-y-auto pr-1">
                @foreach($rules as $rule)
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-800">{{ $rule->name }}</p>
                                <p class="mt-0.5 font-mono text-[11px] text-slate-500">{{ $rule->code }}</p>
                                @if($rule->description)
                                    <p class="mt-2 text-xs leading-relaxed text-slate-600">{{ $rule->description }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $this->severityBadgeClass($rule->severity) }}">{{ Str::title($rule->severity) }}</span>
                                @if($rule->is_enabled)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 ring-1 ring-emerald-200">{{ __('Enabled') }}</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 ring-1 ring-slate-200">{{ __('Disabled') }}</span>
                                @endif
                            </div>
                        </div>
                        <dl class="mt-3 space-y-2 border-t border-slate-100 pt-3 text-xs text-slate-600">
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">{{ __('Rule ID') }}</dt>
                                <dd class="font-mono font-semibold text-slate-800">#{{ $rule->id }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">{{ __('Evaluation window') }}</dt>
                                <dd class="font-medium text-slate-800">{{ $rule->window_minutes }} min</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">{{ __('Sort order') }}</dt>
                                <dd class="font-medium text-slate-800">{{ $rule->sort_order }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">{{ __('Last triggered') }}</dt>
                                <dd class="text-right font-medium text-slate-800">{{ optional($rule->last_triggered_at)->format('d M Y H:i:s') ?? 'Never' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">{{ __('Record timestamps') }}</dt>
                                <dd class="text-right text-slate-700">{{ optional($rule->created_at)->format('d M Y H:i') ?? '—' }} → {{ optional($rule->updated_at)->format('d M Y H:i') ?? '—' }}</dd>
                            </div>
                        </dl>
                        <div class="mt-3">
                            <p class="text-xs font-semibold text-slate-500">{{ __('Thresholds') }}</p>
                            @if($rule->thresholds_json && count($rule->thresholds_json) > 0)
                                <dl class="mt-1.5 space-y-2 rounded-lg border border-slate-100 bg-slate-50/80 p-2.5">
                                    @foreach($rule->thresholds_json as $key => $value)
                                        <div class="text-xs">
                                            <dt class="font-medium text-slate-500">{{ Str::headline(str_replace('_', ' ', (string) $key)) }}</dt>
                                            <dd class="mt-0.5 text-right font-medium text-slate-800 sm:text-left">
                                                @if(is_array($value))
                                                    <dl class="mt-1 space-y-1 border-l-2 border-slate-200 pl-2 text-left">
                                                        @foreach($value as $subKey => $subValue)
                                                            <div class="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-2">
                                                                <span class="text-slate-500">{{ Str::headline(str_replace('_', ' ', (string) $subKey)) }}</span>
                                                                <span class="text-slate-800">
                                                                    @if(is_array($subValue))
                                                                        <span class="text-slate-500">(nested)</span>
                                                                    @else
                                                                        {{ $subValue }}
                                                                    @endif
                                                                </span>
                                                            </div>
                                                        @endforeach
                                                    </dl>
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @else
                                <p class="mt-1 text-xs italic text-slate-400">{{ __('No threshold JSON configured.') }}</p>
                            @endif
                        </div>
                        @can('fraud_alerts.edit')
                        <div class="mt-3 border-t border-slate-100 pt-3">
                            @if($rule->is_enabled)
                                <button type="button" class="pk-btn-secondary px-3 py-1.5 text-xs text-rose-600" wire:click="toggleRule({{ $rule->id }})">{{ __('Disable') }}</button>
                            @else
                                <button type="button" class="pk-btn-secondary px-3 py-1.5 text-xs text-emerald-600" wire:click="toggleRule({{ $rule->id }})">{{ __('Enable') }}</button>
                            @endif
                        </div>
                        @endcan
                    </div>
                @endforeach
            </div>
        </aside>
    </section>
</div>
