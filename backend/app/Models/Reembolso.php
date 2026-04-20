<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reembolso extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'cita_id',
        'cliente_id',
        'pago_id',
        'monto_centavos',
        'moneda',
        'estado',
        'razon',
        'metodo',
        'solicitado_en',
        'procesado_en',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'solicitado_en' => 'datetime',
            'procesado_en' => 'datetime',
            'metadata' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }
}