<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiUsageAlert extends Model
{
    protected $fillable = [
        'provider', 'period', 'nivel', 'valor_actual', 'limite', 'porcentaje', 'unidad', 'notificado_en',
    ];

    protected $casts = [
        'valor_actual' => 'float',
        'limite'       => 'float',
        'porcentaje'   => 'float',
        'notificado_en'=> 'datetime',
    ];
}
