@extends('emails.layouts.branded', [
    'title' => 'Verifica tu correo',
    'eyebrow' => 'Activación de cuenta',
    'badge' => 'Verificación requerida',
    'heading' => 'Confirma tu correo electrónico',
    'preheader' => 'Verifica tu correo para activar tu cuenta en TarotEstrellas.',
])

@section('content')
    <p style="margin:0 0 16px;">{{ $saludo }}</p>

    <p style="margin:0 0 16px;">
        Para activar tu cuenta y proteger tus datos, necesitamos confirmar que este correo te pertenece.
    </p>

    @include('emails.partials.button', [
        'url' => $verificationUrl,
        'label' => 'Verificar correo',
    ])

    <p style="margin:0;color:#756A80;font-size:14px;">
        Si tú no creaste esta cuenta, puedes ignorar este mensaje.
    </p>
@endsection
