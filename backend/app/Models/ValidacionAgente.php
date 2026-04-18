<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidacionAgente extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'validaciones_agente';

    protected $fillable = [
        'comprobante_id',
        'cliente_id',
        'cita_id',
        'pago_id',
        'regla_1_cuenta_ok',
        'regla_2_monto_ok',
        'regla_3_referencia_ok',
        'regla_4_unicidad_ok',
        'regla_5_ventana_tiempo_ok',
        'decision',
        'razon',
        'modelo_ia',
        'tokens_usados',
        'duracion_ms',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'regla_1_cuenta_ok' => 'boolean',
            'regla_2_monto_ok' => 'boolean',
            'regla_3_referencia_ok' => 'boolean',
            'regla_4_unicidad_ok' => 'boolean',
            'regla_5_ventana_tiempo_ok' => 'boolean',
            'tokens_usados' => 'integer',
            'duracion_ms' => 'integer',
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(ComprobanteTransferencia::class, 'comprobante_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }
}
