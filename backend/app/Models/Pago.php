<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pago extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'cita_id',
        'tipo',
        'canal',
        'monto_centavos',
        'moneda',
        'estado',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'referencia_externa',
        'pagado_en',
        'fallo_razon',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'pagado_en' => 'datetime',
            'metadata' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }
}
