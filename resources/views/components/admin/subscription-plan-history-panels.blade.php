@props([
    'subscriptions' => null,
    'payments' => null,
])

@php
    $subscriptions = $subscriptions ?? collect();
    $payments = $payments ?? collect();
@endphp

<div class="space-y-2 text-slate-700">
    <div class="space-y-2">
        <h3 class="text-sm font-semibold text-slate-700">Subscription assignment history</h3>
        <p class="text-xs text-slate-500">Records of subscriptions created for this plan (member or merchant).</p>
        @if($subscriptions->isEmpty())
            <p class="mt-2 rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-3 py-4 text-center text-xs text-slate-500">No subscription records for this plan yet.</p>
        @else
            <div class="mt-2 max-h-72 overflow-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full text-left text-xs">
                    <thead class="sticky top-0 bg-slate-50 text-slate-600">
                        <tr>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Subscriber</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Type</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Status</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Starts</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Ends</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Admin fee</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($subscriptions as $sub)
                            @php
                                $subModel = $sub->subscribable;
                                $label = '—';
                                $type = '—';
                                if ($subModel instanceof \App\Models\Member) {
                                    $label = $subModel->full_name ?: $subModel->email;
                                    $type = 'Member';
                                } elseif ($subModel instanceof \App\Models\Merchant) {
                                    $label = $subModel->company_name ?: $subModel->company_email;
                                    $type = 'Merchant';
                                }
                            @endphp
                            <tr class="align-top">
                                <td class="max-w-[140px] truncate px-3 py-2 font-medium text-slate-800" title="{{ $label }}">{{ $label }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $type }}</td>
                                <td class="whitespace-nowrap px-3 py-2"><x-admin.status-badge :status="$sub->status" class="text-[11px]" /></td>
                                <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $sub->starts_at?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $sub->ends_at?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $sub->admin_fee_amount !== null ? 'RM '.number_format((float) $sub->admin_fee_amount, 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="mt-4 border-t border-slate-100 pt-4 space-y-2">
        <p class="text-sm font-semibold text-slate-800">Payment gateway transactions</p>
        <p class="text-xs text-slate-500">Gateway charges for this plan (e.g. PayCools).</p>
        @if($payments->isEmpty())
            <p class="mt-2 rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-3 py-4 text-center text-xs text-slate-500">No payment transactions for this plan yet.</p>
        @else
            <div class="mt-2 max-h-72 overflow-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full text-left text-xs">
                    <thead class="sticky top-0 bg-slate-50 text-slate-600">
                        <tr>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Date</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Provider</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Status</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Payer</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Plan amt.</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Admin fee</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Total</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Order ID</th>
                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Payment ID</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($payments as $pay)
                            @php
                                $payer = '—';
                                $payerModel = $pay->subscribable;
                                if ($payerModel instanceof \App\Models\Member) {
                                    $payer = $payerModel->full_name ?: $payerModel->email;
                                } elseif ($payerModel instanceof \App\Models\Merchant) {
                                    $payer = $payerModel->company_name ?: $payerModel->company_email;
                                }
                            @endphp
                            <tr class="align-top">
                                <td class="whitespace-nowrap px-3 py-2 text-slate-700">{{ $pay->created_at?->format('d M Y H:i') }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $pay->provider ?? '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-2"><x-admin.status-badge :status="$pay->status" class="text-[11px]" /></td>
                                <td class="max-w-[120px] truncate px-3 py-2 text-slate-700" title="{{ $payer }}">{{ $payer }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-slate-600">RM {{ number_format((float) $pay->amount_plan, 2) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-slate-600">RM {{ number_format((float) $pay->amount_admin_fee, 2) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-800">RM {{ number_format((float) $pay->amount_total, 2) }}</td>
                                <td class="max-w-[100px] truncate px-3 py-2 font-mono text-[11px] text-slate-600" title="{{ $pay->gateway_order_id }}">{{ Str::limit($pay->gateway_order_id ?? '—', 20) }}</td>
                                <td class="max-w-[100px] truncate px-3 py-2 font-mono text-[11px] text-slate-600" title="{{ $pay->gateway_payment_id }}">{{ $pay->gateway_payment_id ? Str::limit($pay->gateway_payment_id, 20) : '—' }}</td>
                            </tr>
                            @if($pay->fee_reason)
                                <tr>
                                    <td colspan="9" class="bg-slate-50/80 px-3 py-2 text-[11px] text-slate-500">Fee reason: {{ $pay->fee_reason }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
