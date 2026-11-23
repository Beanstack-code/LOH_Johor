<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Receipt</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">
                    <tr>
                        <td style="background:#ed1c24;padding:18px 22px;color:#ffffff;">
                            <div style="font-size:18px;font-weight:700;letter-spacing:.3px;">柔佛州羅氏公会 Donation</div>
                            <div style="margin-top:6px;font-size:13px;opacity:.95;">Thank you for your contribution</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 22px;">
                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;">Hello {{ $donation->member_name }},</p>
                            <p style="margin:0 0 16px;font-size:14px;line-height:1.7;color:#475569;">
                                Thank you for your donation. Your payment has been received successfully and this email is your receipt.
                                <strong>A PDF receipt is attached</strong> to this message for your records.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 18px;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 14px;background:#f8fafc;font-size:13px;font-weight:700;color:#334155;">Receipt Details</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px;">
                                        <p style="margin:0 0 8px;font-size:13px;color:#334155;"><strong>Campaign:</strong> {{ $campaignName }}</p>
                                        <p style="margin:0 0 8px;font-size:13px;color:#334155;"><strong>Amount:</strong> MYR {{ number_format((float) $donation->amount, 2) }}</p>
                                        <p style="margin:0 0 8px;font-size:13px;color:#334155;"><strong>Date:</strong> {{ optional($donation->donated_at)->format('d M Y, h:i A') }}</p>
                                        <p style="margin:0 0 8px;font-size:13px;color:#334155;"><strong>Payment Status:</strong> {{ strtoupper((string) ($donation->payment_status ?? 'captured')) }}</p>
                                        <p style="margin:0 0 8px;font-size:13px;color:#334155;"><strong>Order ID:</strong> {{ $donation->gateway_order_id ?? '-' }}</p>
                                        <p style="margin:0;font-size:13px;color:#334155;"><strong>Payment ID:</strong> {{ $donation->gateway_payment_id ?? '-' }}</p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0;font-size:13px;color:#475569;">
                                We appreciate your support. If you have any questions about this receipt, please contact our support team.
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin:12px 0 0;font-size:12px;color:#94a3b8;">&copy; {{ date('Y') }} 柔佛州羅氏公会. All rights reserved.</p>
            </td>
        </tr>
    </table>
</body>
</html>
