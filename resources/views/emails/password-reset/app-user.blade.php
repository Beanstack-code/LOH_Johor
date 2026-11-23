<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Password Reset</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">
                    <tr>
                        <td style="background:#ed1c24;padding:18px 22px;color:#ffffff;">
                            <div style="font-size:18px;font-weight:700;letter-spacing:.3px;">柔佛州羅氏公会 User</div>
                            <div style="margin-top:6px;font-size:13px;opacity:.95;">{{ $label }} Password Reset</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 22px;">
                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;">Hello,</p>
                            <p style="margin:0 0 16px;font-size:14px;line-height:1.7;color:#475569;">
                                We received a request to reset your {{ strtolower($label) }} app account password.
                            </p>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:6px 0 18px;">
                                <tr>
                                    <td style="border-radius:10px;background:#ed1c24;">
                                        <a href="{{ $url }}" style="display:inline-block;padding:11px 18px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;">
                                            Reset App Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 10px;font-size:13px;color:#475569;">
                                This link expires in {{ $expiresInMinutes }} minutes.
                            </p>
                            <p style="margin:0 0 16px;font-size:13px;color:#475569;">
                                If you did not request a password reset, you can safely ignore this email.
                            </p>

                            <div style="padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">
                                <p style="margin:0 0 8px;font-size:12px;color:#64748b;font-weight:600;">Manual link:</p>
                                <p style="margin:0;font-size:12px;word-break:break-all;">
                                    <a href="{{ $url }}" style="color:#0d4f8b;">{{ $url }}</a>
                                </p>
                            </div>
                        </td>
                    </tr>
                </table>
                <p style="margin:12px 0 0;font-size:12px;color:#94a3b8;">&copy; {{ date('Y') }} 柔佛州羅氏公会. All rights reserved.</p>
            </td>
        </tr>
    </table>
</body>
</html>
