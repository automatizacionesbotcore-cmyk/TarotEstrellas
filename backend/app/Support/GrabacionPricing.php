<?php

namespace App\Support;

class GrabacionPricing
{
    public const TARIFAS_CLP = [
        30 => 250000,
        60 => 399000,
        90 => 599000,
    ];

    public static function extraCentavos(int $duracionMinutos, string $moneda = 'CLP'): int
    {
        if ($moneda !== 'CLP') {
            // Fallback prorrateado en USD a 4.99 USD por hora -> 499 centavos USD por 60 min.
            return (int) round(($duracionMinutos / 60) * 499);
        }

        if (isset(self::TARIFAS_CLP[$duracionMinutos])) {
            return self::TARIFAS_CLP[$duracionMinutos];
        }

        // Tarifas conocidas: 30, 60, 90. Para otras duraciones, prorrateamos en base 60.
        $base = self::TARIFAS_CLP[60];
        return (int) round(($duracionMinutos / 60) * $base);
    }

    /**
     * Devuelve un monto formateado en CLP (sin centavos visibles).
     */
    public static function formatear(int $centavos, string $moneda = 'CLP'): string
    {
        $monto = $centavos / 100;
        if ($moneda === 'CLP') {
            return '$' . number_format($monto, 0, ',', '.') . ' CLP';
        }
        return '$' . number_format($monto, 2, '.', ',') . ' ' . $moneda;
    }
}
