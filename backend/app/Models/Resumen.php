<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resumen extends Model
{
    use HasFactory;

    protected $table = 'resumenes';

    protected $fillable = [
        'cita_id',
        'grabacion_id',
        'transcripcion_id',
        'contenido',
        'proveedor',
        'modelo',
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

    public function transcripcion(): BelongsTo
    {
        return $this->belongsTo(Transcripcion::class);
    }
}