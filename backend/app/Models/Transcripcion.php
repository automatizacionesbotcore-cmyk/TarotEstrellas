<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transcripcion extends Model
{
    use HasFactory;

    protected $table = 'transcripciones';

    protected $fillable = [
        'cita_id',
        'grabacion_id',
        'contenido',
        'proveedor',
        'modelo',
        'idioma',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function grabacion(): BelongsTo
    {
        return $this->belongsTo(Grabacion::class);
    }

    public function resumen(): HasOne
    {
        return $this->hasOne(Resumen::class);
    }
}