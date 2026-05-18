@extends('emails.layouts.branded', [
    'title' => 'Restablecer contraseña',
    'eyebrow' => 'Seguridad de cuenta',
    'badge' => 'Solicitud de recuperación',
    'heading' => 'Restablece tu contraseña',
    'preheader' => 'Usa este enlace seguro para crear una nueva contraseña.',
])

@section('content')
    <p style="margin:0 0 16px;">{{ $saludo }}</p>

    <p style="margin:0 0 16px;">
        Recibimos una solicitud para restablecer la contraseña de tu cuenta en {{ $appName }}.
        Para crear una nueva contraseña, usa el botón siguiente.
    </p>

    @include('emails.partials.button', [
        'url' => $resetUrl,
        'label' => 'Restablecer contraseña',
    ])

    <p style="margin:0 0 16px;">
        Este enlace expirará en {{ $expira }} minutos por motivos de seguridad.
    </p>

    <p style="margin:0;color:#756A80;font-size:14px;">
        Si tú no solicitaste este cambio, puedes ignorar este correo y tu contraseña seguirá siendo la misma.
    </p>
@endsection
