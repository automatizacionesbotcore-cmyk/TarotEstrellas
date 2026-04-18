<?php

namespace App\Support;

use App\Models\AppSetting;

class PaymentValidationConfig
{
    public static function bancoDestino(): string
    {
        return (string) AppSetting::getValue('TRANSFERENCIA_BANCO', 'BancoEstado');
    }

    public static function cuentaDestino(): string
    {
        return (string) AppSetting::getValue('TRANSFERENCIA_CUENTA', '1234567890');
    }

    public static function rutDestino(): string
    {
        return (string) AppSetting::getValue('TRANSFERENCIA_RUT', '11111111-1');
    }

    public static function minutosAntiguedadComprobante(): int
    {
        return max(1, (int) AppSetting::getValue('MINUTOS_ANTIGUEDAD_COMPROBANTE', 30));
    }
}
