<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resena extends Model
{
    protected $fillable = [
        'uuid',
        'cita_id',
        'cliente_id',
        'especialista_id',
        'puntuacion',
        'comentario',
        'visible',
    ];

    protected $casts = [
        'puntuacion' => 'integer',
        'visible' => 'boolean',
    ];

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function especialista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'especialista_id');
    }
}
