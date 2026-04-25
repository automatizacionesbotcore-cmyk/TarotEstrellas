<?php

namespace Database\Factories;

use App\Models\TipoConsulta;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TipoConsulta>
 */
class TipoConsultaFactory extends Factory
{
    protected $model = TipoConsulta::class;

    public function definition(): array
    {
        $nombre = fake()->unique()->words(3, true);

        return [
            'slug'                        => Str::slug($nombre),
            'nombre'                      => ucfirst($nombre),
            'descripcion'                 => fake()->sentence(10),
            'duracion_minutos'            => fake()->randomElement([30, 45, 60, 90]),
            'precio_referencial_centavos' => fake()->randomElement([2500000, 3500000, 4500000, 6500000]),
            'moneda'                      => 'CLP',
            'color_hex'                   => fake()->hexColor(),
            'requiere_datos_natales'      => false,
            'orden_visualizacion'         => 0,
            'activo'                      => true,
        ];
    }
}
