<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisponibilidadBase extends Model
{
    protected $table = 'disponibilidad_base';

    protected $fillable = [
        'especialista_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'activo',
    ];

    protected $casts = [
        'dia_semana' => 'integer',
        'activo' => 'boolean',
    ];

    public function especialista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'especialista_id');
    }
}
