<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Donation receipt</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; margin: 0; padding: 24px; }
        .header { background: #ed1c24; color: #fff; padding: 14px 18px; border-radius: 6px 6px 0 0; }
        .header h1 { margin: 0; font-size: 18px; font-weight: 700; }
        .header p { margin: 6px 0 0; font-size: 11px; opacity: 0.95; }
        .box { border: 1px solid #e2e8f0; border-top: 0; padding: 16px 18px; border-radius: 0 0 6px 6px; }
        table.meta { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.meta td { padding: 6px 0; vertical-align: top; }
        table.meta td.label { width: 32%; font-weight: 700; color: #334155; }
        .footer { margin-top: 28px; font-size: 10px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PKPMS — Donation receipt</h1>
        <p>Official receipt for your contribution</p>
    </div>
    <div class="box">
        <p style="margin:0 0 12px;"><strong>{{ $donation->member_name }}</strong></p>
        <table class="meta">
            <tr>
                <td class="label">Campaign</td>
                <td>{{ $campaignName }}</td>
            </tr>
            <tr>
                <td class="label">Amount</td>
                <td>MYR {{ number_format((float) $donation->amount, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Date</td>
                <td>{{ optional($donation->donated_at)->format('d M Y, h:i A') }}</td>
            </tr>
            <tr>
                <td class="label">Payment status</td>
                <td>{{ strtoupper((string) ($donation->payment_status ?? 'captured')) }}</td>
            </tr>
            <tr>
                <td class="label">Order ID</td>
                <td>{{ $donation->gateway_order_id ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Payment ID</td>
                <td>{{ $donation->gateway_payment_id ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Receipt #</td>
                <td>DON-{{ str_pad((string) $donation->id, 6, '0', STR_PAD_LEFT) }}</td>
            </tr>
        </table>
    </div>
    <div class="footer">
        This document is your official donation receipt. Retain it for your records.
        &copy; {{ date('Y') }} PKPMS.
    </div>
</body>
</html>
