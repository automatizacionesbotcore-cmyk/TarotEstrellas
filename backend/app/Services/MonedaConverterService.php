<?php

namespace App\Services;

class MonedaConverterService
{
    /**
     * Tasas estáticas (fallback). Para producción reemplazar por consulta a CurrencyAPI / ExchangeRates con caché.
     * Las tasas están expresadas como "1 USD = X moneda".
     */
    private const TASAS_USD = [
        'USD' => 1.0,
        'CLP' => 950.0,
        'EUR' => 0.92,
        'MXN' => 17.5,
        'ARS' => 980.0,
        'COP' => 4100.0,
        'PEN' => 3.7,
        'BRL' => 5.1,
    ];

    private const PAIS_A_MONEDA = [
        'CL' => 'CLP', 'AR' => 'ARS', 'MX' => 'MXN', 'CO' => 'COP',
        'PE' => 'PEN', 'BR' => 'BRL', 'ES' => 'EUR',
    ];

    public function monedaParaPais(?string $iso2): string
    {
        $iso = strtoupper((string) $iso2);
        return self::PAIS_A_MONEDA[$iso] ?? 'USD';
    }

    public function convertir(int $centavos, string $monedaOrigen, string $monedaDestino): int
    {
        $monedaOrigen = strtoupper($monedaOrigen);
        $monedaDestino = strtoupper($monedaDestino);
        if ($monedaOrigen === $monedaDestino) return $centavos;

        $tasaOrigen = self::TASAS_USD[$monedaOrigen] ?? null;
        $tasaDestino = self::TASAS_USD[$monedaDestino] ?? null;
        if ($tasaOrigen === null || $tasaDestino === null) return $centavos;

        $usd = $centavos / $tasaOrigen;
        return (int) round($usd * $tasaDestino);
    }

    public function tasas(): array
    {
        return self::TASAS_USD;
    }
}
