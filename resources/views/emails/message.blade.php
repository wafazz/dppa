<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $systemName }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f8;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td align="center" style="padding:24px 28px;border-bottom:1px solid #e5e7eb;">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $systemName }}" style="display:block;max-width:240px;max-height:120px;width:auto;height:auto;margin:0 auto;">
                            @else
                                <span style="font-size:18px;font-weight:700;">{{ $systemName }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;font-size:15px;line-height:1.6;">
                            @foreach ($lines as $line)
                                <p style="margin:0 0 16px;">{{ $line }}</p>
                            @endforeach
                            @if ($actionUrl && $actionText)
                                <p style="margin:24px 0;text-align:center;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;background:#0d6efd;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;">{{ $actionText }}</a>
                                </p>
                                <p style="margin:0 0 8px;font-size:12px;color:#6b7280;">{{ __('app.emails.fallback') }}</p>
                                <p style="margin:0;font-size:12px;word-break:break-all;"><a href="{{ $actionUrl }}" style="color:#0d6efd;">{{ $actionUrl }}</a></p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
                            <div>{{ $systemName }}</div>
                            <div>{{ __('app.emails.footer') }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
