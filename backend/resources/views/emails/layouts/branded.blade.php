@php
    $brandName = config('app.name', 'TarotEstrellas');
    $frontendUrl = rtrim((string) config('app.frontend_url', 'https://tarotestrellas.com'), '/');
    $logoUrl = $frontendUrl . '/favicon.svg';
    $accent = $accent ?? '#5A3D8B';
    $accentDark = $accentDark ?? '#3E2A61';
    $preheader = $preheader ?? '';
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $brandName }}</title>
</head>
<body style="margin:0;padding:0;background:#F4F1EA;color:#2B2238;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;">
@if($preheader)
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        {{ $preheader }}
    </div>
@endif
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F4F1EA;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#FFFFFF;border:1px solid #E8DFD6;border-radius:8px;overflow:hidden;">
                <tr>
                    <td style="background:{{ $accentDark }};padding:28px 32px;text-align:center;">
                        <img src="{{ $logoUrl }}" width="44" height="44" alt="{{ $brandName }}" style="display:block;margin:0 auto 10px;border:0;">
                        <div style="font-size:24px;line-height:1.2;font-weight:700;color:#FFFFFF;letter-spacing:0;">{{ $brandName }}</div>
                        @if(!empty($eyebrow))
                            <div style="font-size:13px;line-height:1.5;color:#E8DFF6;margin-top:4px;">{{ $eyebrow }}</div>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        @if(!empty($badge))
                            <div style="display:inline-block;background:#F3EEF9;color:{{ $accentDark }};border:1px solid #DED0EE;border-radius:999px;padding:6px 12px;font-size:12px;line-height:1.2;font-weight:700;margin-bottom:18px;">
                                {{ $badge }}
                            </div>
                        @endif
                        @if(!empty($heading))
                            <h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#2B2238;font-weight:700;letter-spacing:0;">
                                {{ $heading }}
                            </h1>
                        @endif
                        <div style="font-size:16px;line-height:1.65;color:#3C3348;text-align:left;">
                            @yield('content')
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="background:#FAF7F2;border-top:1px solid #E8DFD6;padding:18px 32px;text-align:center;">
                        <p style="margin:0 0 6px;font-size:12px;line-height:1.5;color:#756A80;">
                            {{ $footer ?? 'Gracias por confiar en TarotEstrellas.' }}
                        </p>
                        <p style="margin:0;font-size:12px;line-height:1.5;color:#8A7F94;">
                            © {{ date('Y') }} {{ $brandName }} · <a href="{{ $frontendUrl }}" style="color:{{ $accent }};text-decoration:none;">{{ parse_url($frontendUrl, PHP_URL_HOST) }}</a>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
