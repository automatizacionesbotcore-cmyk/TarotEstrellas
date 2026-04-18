<?php

namespace App\Models;

use App\Models\ComprobanteTransferencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cita extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'codigo_referencia',
        'cliente_id',
        'especialista_id',
        'tipo_consulta_id',
        'inicio_utc',
        'fin_utc',
        'duracion_minutos',
        'zona_horaria_cliente',
        'estado',
        'canal_pago',
        'precio_total_centavos',
        'precio_final_centavos',
        'moneda',
        'tema_principal',
        'notas_cliente',
        'notas_chachita',
        'reservada_hasta',
        'extension_reserva_aplicada_en',
        'extension_reserva_conteo',
        'confirmada_en',
        'finalizada_en',
        'cancelada_en',
        'motivo_cancelacion',
        'es_primera_consulta',
    ];

    protected function casts(): array
    {
        return [
            'inicio_utc' => 'datetime',
            'fin_utc' => 'datetime',
            'reservada_hasta' => 'datetime',
            'extension_reserva_aplicada_en' => 'datetime',
            'extension_reserva_conteo' => 'integer',
            'confirmada_en' => 'datetime',
            'finalizada_en' => 'datetime',
            'cancelada_en' => 'datetime',
            'es_primera_consulta' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function especialista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'especialista_id');
    }

    public function tipoConsulta(): BelongsTo
    {
        return $this->belongsTo(TipoConsulta::class, 'tipo_consulta_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function comprobantesTransferencia(): HasMany
    {
        return $this->hasMany(ComprobanteTransferencia::class);
    }
}
