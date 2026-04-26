<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloqueoAgenda extends Model
{
    protected $table = 'bloqueos_agenda';

    protected $fillable = [
        'especialista_id',
        'tipo',
        'motivo',
        'descripcion',
        'fecha_inicio_utc',
        'fecha_fin_utc',
        'all_day',
    ];

    protected $casts = [
        'fecha_inicio_utc' => 'datetime',
        'fecha_fin_utc' => 'datetime',
        'all_day' => 'boolean',
    ];

    public function especialista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'especialista_id');
    }
}
