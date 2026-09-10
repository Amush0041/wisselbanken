<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You've been invited to {{ $orgName }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:40px 0;">
    <tr>
        <td align="center">
            <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">

                {{-- Header --}}
                <tr>
                    <td style="background:#6b1c1c;padding:32px 40px;text-align:center;">
                        <p style="margin:0;font-size:22px;font-weight:700;color:#ffffff;letter-spacing:-.3px;">Wisselbanken</p>
                        <p style="margin:6px 0 0;font-size:13px;color:rgba(255,255,255,.75);">The construction materials platform</p>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="padding:40px 40px 32px;">
                        <p style="margin:0 0 8px;font-size:24px;font-weight:700;color:#1a1a1a;">You're invited!</p>
                        <p style="margin:0 0 24px;font-size:15px;color:#555;line-height:1.6;">
                            Hi {{ $recipientName }},
                        </p>
                        <p style="margin:0 0 24px;font-size:15px;color:#555;line-height:1.6;">
                            <strong>{{ $inviterName }}</strong> has invited you to join
                            <strong>{{ $orgName }}</strong> on Wisselbanken as
                            <strong>{{ $roleName }}</strong>.
                        </p>

                        {{-- CTA button --}}
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="center" style="padding:8px 0 28px;">
                                    <a href="{{ $inviteUrl }}"
                                       style="display:inline-block;background:#6b1c1c;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;padding:14px 36px;border-radius:7px;letter-spacing:-.1px;">
                                        Accept invitation
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 12px;font-size:13px;color:#888;line-height:1.6;">
                            This link expires in <strong>7 days</strong> and can only be used once.
                            If you weren't expecting this, you can safely ignore this email.
                        </p>

                        {{-- Link fallback --}}
                        <div style="background:#f8f8f8;border-radius:6px;padding:12px 16px;margin-top:20px;">
                            <p style="margin:0 0 4px;font-size:11px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.06em;">Or copy this link into your browser</p>
                            <p style="margin:0;font-size:12px;color:#6b1c1c;word-break:break-all;">{{ $inviteUrl }}</p>
                        </div>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:20px 40px;border-top:1px solid #eee;text-align:center;">
                        <p style="margin:0;font-size:12px;color:#aaa;">
                            © {{ date('Y') }} Wisselbanken · This email was sent because someone invited you to their organisation.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
