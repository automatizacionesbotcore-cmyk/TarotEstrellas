<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiUsageAlert extends Model
{
    protected $fillable = [
        'provider', 'period', 'nivel', 'valor_actual', 'limite', 'porcentaje', 'unidad', 'notificado_en',
    ];

    protected $appends = [
        'usado',
        'notificado_at',
    ];

    protected $casts = [
        'valor_actual' => 'float',
        'limite'       => 'float',
        'porcentaje'   => 'float',
        'notificado_en'=> 'datetime',
    ];

    public function getUsadoAttribute(): float
    {
        return (float) $this->valor_actual;
    }

    public function getNotificadoAtAttribute(): ?string
    {
        return $this->notificado_en?->toISOString();
    }
}
