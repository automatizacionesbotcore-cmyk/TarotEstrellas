<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComprobanteTransferencia extends Model
{
    use HasFactory;

    protected $table = 'comprobantes_transferencia';

    protected $fillable = [
        'uuid',
        'pago_id',
        'cita_id',
        'archivo_url',
        'archivo_tipo',
        'tamano_bytes',
        'datos_extraidos',
        'estado_validacion',
        'validado_por',
        'validado_en',
        'razon_rechazo',
        'id_transaccion_bancaria',
    ];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'datos_extraidos' => 'array',
            'validado_en' => 'datetime',
        ];
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    public function validacionesAgente(): HasMany
    {
        return $this->hasMany(ValidacionAgente::class, 'comprobante_id');
    }
}
