<?php

namespace Database\Seeders;

use App\Models\TipoConsulta;
use App\Models\TipoConsultaPrecio;
use Illuminate\Database\Seeder;

class TipoConsultaPrecioSeeder extends Seeder
{
    public function run(): void
    {
        // CLP prices (centavos = pesos * 100 but spec uses centavos so 45000 CLP = 4500000)
        // USD prices (centavos = dollars * 100 so $45 USD = 4500)
        $precios = [
            'tarot' => [
                ['moneda' => 'CLP', 'precio_centavos' => 4500000],
                ['moneda' => 'USD', 'precio_centavos' => 4500],
                ['moneda' => 'MXN', 'precio_centavos' => 75000],
                ['moneda' => 'EUR', 'precio_centavos' => 4200],
            ],
            'cartas-espanolas' => [
                ['moneda' => 'CLP', 'precio_centavos' => 3500000],
                ['moneda' => 'USD', 'precio_centavos' => 3500],
                ['moneda' => 'MXN', 'precio_centavos' => 58000],
                ['moneda' => 'EUR', 'precio_centavos' => 3300],
            ],
            'carta-astral' => [
                ['moneda' => 'CLP', 'precio_centavos' => 6500000],
                ['moneda' => 'USD', 'precio_centavos' => 6500],
                ['moneda' => 'MXN', 'precio_centavos' => 110000],
                ['moneda' => 'EUR', 'precio_centavos' => 6000],
            ],
        ];

        foreach ($precios as $slug => $monedas) {
            $tipo = TipoConsulta::query()->where('slug', $slug)->first();
            if (! $tipo) {
                continue;
            }

            foreach ($monedas as $precio) {
                TipoConsultaPrecio::query()->updateOrCreate(
                    [
                        'tipo_consulta_id' => $tipo->id,
                        'moneda' => $precio['moneda'],
                        'vigente_hasta' => null,
                    ],
                    [
                        'precio_centavos' => $precio['precio_centavos'],
                        'vigente_desde' => '2026-01-01',
                    ]
                );
            }
        }
    }
}
