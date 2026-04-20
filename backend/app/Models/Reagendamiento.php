<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reagendamiento extends Model
{
    use HasFactory;

    protected $table = 'reagendamientos';

    protected $fillable = [
        'cita_original_id',
        'cita_nueva_id',
        'motivo',
        'gratuito',
        'reagendado_por',
    ];

    protected function casts(): array
    {
        return [
            'gratuito' => 'boolean',
        ];
    }

    public function citaOriginal(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_original_id');
    }

    public function citaNueva(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_nueva_id');
    }

    public function usuarioReagenda(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reagendado_por');
    }
}
