<?php

namespace Database\Seeders;

use App\Models\TipoConsulta;
use Illuminate\Database\Seeder;

class TipoConsultaSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'slug' => 'tarot',
                'nombre' => 'Lectura de Tarot',
                'descripcion' => 'Lectura integral de situacion actual y camino proximo.',
                'duracion_minutos' => 60,
                'precio_referencial_centavos' => 4500000,
                'moneda' => 'CLP',
                'color_hex' => '#C9A961',
                'orden_visualizacion' => 1,
                'activo' => true,
            ],
            [
                'slug' => 'cartas-espanolas',
                'nombre' => 'Cartas Espanolas',
                'descripcion' => 'Consulta enfocada en decisiones practicas y panorama cercano.',
                'duracion_minutos' => 45,
                'precio_referencial_centavos' => 3500000,
                'moneda' => 'CLP',
                'color_hex' => '#9E6B3D',
                'orden_visualizacion' => 2,
                'activo' => true,
            ],
            [
                'slug' => 'carta-astral',
                'nombre' => 'Carta Astral',
                'descripcion' => 'Analisis astrologico con fecha, hora y lugar de nacimiento.',
                'duracion_minutos' => 90,
                'precio_referencial_centavos' => 6500000,
                'moneda' => 'CLP',
                'color_hex' => '#6751A2',
                'orden_visualizacion' => 3,
                'requiere_datos_natales' => true,
                'activo' => true,
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoConsulta::query()->updateOrCreate(
                ['slug' => $tipo['slug']],
                $tipo
            );
        }
    }
}
