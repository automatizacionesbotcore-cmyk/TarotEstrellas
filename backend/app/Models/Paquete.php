<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paquete extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug', 'nombre', 'descripcion', 'tipo',
        'consultas_incluidas', 'vigencia_dias',
        'precio_centavos', 'moneda', 'descuento_porcentaje',
        'activo', 'imagen_url', 'destacado', 'orden_visualizacion',
    ];

    protected $casts = [
        'activo'                => 'boolean',
        'destacado'             => 'boolean',
        'precio_centavos'       => 'integer',
        'consultas_incluidas'   => 'integer',
        'vigencia_dias'         => 'integer',
        'descuento_porcentaje'  => 'float',
        'orden_visualizacion'   => 'integer',
    ];

    public function tiposConsulta(): BelongsToMany
    {
        return $this->belongsToMany(TipoConsulta::class, 'paquete_tipos_consulta');
    }

    public function membresias(): HasMany
    {
        return $this->hasMany(Membresia::class);
    }
}
