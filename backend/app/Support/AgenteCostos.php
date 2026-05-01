<?php

namespace App\Support;

class AgenteCostos
{
    /**
     * Precios USD por 1M tokens (input/output). Aproximados; ajustables vía env si se quiere.
     * Fuentes: Anthropic / OpenAI pricing pages.
     */
    public const PRECIOS = [
        // Anthropic
        'claude-3-5-sonnet-latest'    => ['in' => 3.00,  'out' => 15.00],
        'claude-3-5-sonnet-20241022'  => ['in' => 3.00,  'out' => 15.00],
        'claude-3-5-haiku-latest'     => ['in' => 0.80,  'out' => 4.00],
        'claude-3-opus-latest'        => ['in' => 15.00, 'out' => 75.00],
        'claude-3-haiku-20240307'     => ['in' => 0.25,  'out' => 1.25],
        // OpenAI
        'gpt-4o'                      => ['in' => 2.50,  'out' => 10.00],
        'gpt-4o-mini'                 => ['in' => 0.15,  'out' => 0.60],
        // Fallback
        '__default__'                 => ['in' => 1.00,  'out' => 3.00],
    ];

    public static function precio(?string $modelo): array
    {
        $m = $modelo ?: '__default__';
        return self::PRECIOS[$m] ?? self::PRECIOS['__default__'];
    }

    /** Costo USD para un par (in,out) de tokens. */
    public static function costo(?string $modelo, int $in, int $out): float
    {
        $p = self::precio($modelo);
        return round(($in / 1_000_000) * $p['in'] + ($out / 1_000_000) * $p['out'], 6);
    }
}
