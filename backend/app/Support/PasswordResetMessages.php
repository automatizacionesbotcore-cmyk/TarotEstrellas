<?php

namespace App\Support;

use Illuminate\Support\Facades\Password;

class PasswordResetMessages
{
    public static function get(string $status): string
    {
        return match ($status) {
            Password::RESET_LINK_SENT => 'Te enviamos un enlace para restablecer tu contraseña.',
            Password::PASSWORD_RESET => 'Tu contraseña fue restablecida correctamente.',
            Password::INVALID_USER => 'No encontramos una cuenta asociada a ese correo.',
            Password::INVALID_TOKEN => 'El enlace de restablecimiento no es válido o ya fue utilizado.',
            Password::RESET_THROTTLED => 'Ya solicitaste un enlace hace poco. Espera unos minutos antes de intentarlo nuevamente.',
            default => 'No se pudo procesar la solicitud de restablecimiento.',
        };
    }
}
