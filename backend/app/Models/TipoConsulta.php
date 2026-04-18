<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoConsulta extends Model
{
    use HasFactory;

    protected $table = 'tipos_consulta';

    protected $fillable = [
        'slug',
        'nombre',
        'descripcion',
        'duracion_minutos',
        'precio_referencial_centavos',
        'moneda',
        'imagen_url',
        'color_hex',
        'requiere_datos_natales',
        'orden_visualizacion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'requiere_datos_natales' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }
}
