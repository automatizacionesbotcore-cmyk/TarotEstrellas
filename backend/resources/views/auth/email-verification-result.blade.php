@php
    $brandName = config('app.name', 'TarotEstrellas');
    $frontendUrl = rtrim((string) config('app.frontend_url', 'https://tarotestrellas.com'), '/');
    $logoUrl = $frontendUrl . '/brand/logo-dark.png';
    $title = $success ? 'Correo verificado' : 'No pudimos verificar tu correo';
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $brandName }}</title>
</head>
<body style="margin:0;min-height:100vh;background:#0E0820;color:#FFF8F0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;display:grid;place-items:center;padding:24px;">
    <main style="width:100%;max-width:520px;background:#171029;border:1px solid rgba(222,177,71,.42);border-radius:18px;padding:32px 26px;text-align:center;box-shadow:0 24px 80px rgba(0,0,0,.36);">
        <img src="{{ $logoUrl }}" alt="{{ $brandName }}" style="width:100%;max-width:320px;height:auto;margin:0 auto 24px;display:block;border-radius:8px;">
        <div style="width:56px;height:56px;border-radius:999px;background:{{ $success ? '#DEB147' : '#8F2E36' }};color:#10091D;display:grid;place-items:center;margin:0 auto 18px;font-size:28px;font-weight:800;">
            {{ $success ? '✓' : '!' }}
        </div>
        <h1 style="font-family:Georgia,'Times New Roman',serif;font-size:32px;line-height:1.15;margin:0 0 12px;color:#FFE8B4;">{{ $title }}</h1>
        <p style="font-size:17px;line-height:1.6;margin:0 0 24px;color:#E8DFF6;">{{ $message }}</p>
        <a href="{{ $frontendUrl }}/auth/login" style="display:inline-block;background:#DEB147;color:#10091D;text-decoration:none;font-weight:800;border-radius:999px;padding:13px 24px;">Ir a iniciar sesión</a>
    </main>
</body>
</html>
