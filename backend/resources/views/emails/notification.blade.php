<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi TMS</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width:480px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background-color:#0EA5E9; padding:20px 24px;">
                            <span style="color:#ffffff; font-size:18px; font-weight:bold;">TMS</span>
                            <span style="color:#e0f2fe; font-size:13px; margin-left:8px;">Transport Management System</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px 8px 24px;">
                            <p style="margin:0 0 4px 0; font-size:14px; color:#475569;">Halo {{ $userName }},</p>
                            <p style="margin:0; font-size:15px; color:#0f172a; line-height:1.6;">{{ $notificationMessage }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 24px 28px 24px;">
                            <a href="{{ $actionUrl }}" style="display:inline-block; background-color:#0EA5E9; color:#ffffff; text-decoration:none; padding:10px 20px; border-radius:8px; font-size:14px; font-weight:bold;">
                                Buka TMS
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px; background-color:#f8fafc; border-top:1px solid #e2e8f0;">
                            <p style="margin:0; font-size:12px; color:#94a3b8;">Email otomatis dari TMS PT Pro Energi — jangan membalas email ini.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
