<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CitaEstadoHistorial extends Model
{
    use HasFactory;

    protected $table = 'citas_estados_historial';
    public $timestamps = false;

    protected $fillable = [
        'cita_id',
        'estado_anterior',
        'estado_nuevo',
        'motivo',
        'cambiado_por',
        'cambiado_en',
        'metadatos',
    ];

    protected function casts(): array
    {
        return [
            'cambiado_en' => 'datetime',
            'metadatos' => 'array',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function usuarioCambio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cambiado_por');
    }
}
