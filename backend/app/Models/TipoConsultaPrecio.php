<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TipoConsultaPrecio extends Model
{
    protected $table = 'tipos_consulta_precios';

    protected $fillable = [
        'tipo_consulta_id',
        'moneda',
        'precio_centavos',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected $casts = [
        'precio_centavos' => 'integer',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    public function tipoConsulta(): BelongsTo
    {
        return $this->belongsTo(TipoConsulta::class);
    }
}
