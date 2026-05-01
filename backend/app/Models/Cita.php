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

    protected static function booted(): void
    {
        static::created(function (self $cita): void {
            if (! $cita->estado) {
                return;
            }

            CitaEstadoHistorial::query()->create([
                'cita_id' => $cita->id,
                'estado_anterior' => null,
                'estado_nuevo' => $cita->estado,
                'motivo' => null,
                'cambiado_por' => null,
                'cambiado_en' => now(),
                'metadatos' => ['origen' => 'model.created'],
            ]);
        });

        static::updated(function (self $cita): void {
            if (! $cita->wasChanged('estado')) {
                return;
            }

            CitaEstadoHistorial::query()->create([
                'cita_id' => $cita->id,
                'estado_anterior' => $cita->getOriginal('estado'),
                'estado_nuevo' => $cita->estado,
                'motivo' => $cita->motivo_cancelacion,
                'cambiado_por' => null,
                'cambiado_en' => now(),
                'metadatos' => ['origen' => 'model.updated'],
            ]);
        });
    }

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
        'daily_room_url',
        'daily_room_name',
        'grabacion_solicitada',
        'grabacion_extra_centavos',
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
        'cliente_confirmo_at',
        'finalizada_en',
        'cancelada_en',
        'motivo_cancelacion',
        'es_primera_consulta',
        'paquete_id',
        'membresia_id',
        'cupon_id',
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
            'cliente_confirmo_at' => 'datetime',
            'finalizada_en' => 'datetime',
            'cancelada_en' => 'datetime',
            'es_primera_consulta' => 'boolean',
            'grabacion_solicitada' => 'boolean',
            'grabacion_extra_centavos' => 'integer',
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

    public function grabaciones(): HasMany
    {
        return $this->hasMany(Grabacion::class);
    }

    public function transcripciones(): HasMany
    {
        return $this->hasMany(Transcripcion::class);
    }

    public function resumenes(): HasMany
    {
        return $this->hasMany(Resumen::class);
    }

    public function reagendamientosComoOriginal(): HasMany
    {
        return $this->hasMany(Reagendamiento::class, 'cita_original_id');
    }

    public function reagendamientoComoNueva(): HasMany
    {
        return $this->hasMany(Reagendamiento::class, 'cita_nueva_id');
    }

    public function estadosHistorial(): HasMany
    {
        return $this->hasMany(CitaEstadoHistorial::class, 'cita_id');
    }

    public function reembolsos(): HasMany
    {
        return $this->hasMany(Reembolso::class, 'cita_id');
    }

    public function cupon(): BelongsTo
    {
        return $this->belongsTo(Cupon::class, 'cupon_id');
    }

    public function paquete(): BelongsTo
    {
        return $this->belongsTo(Paquete::class, 'paquete_id');
    }

    public function membresia(): BelongsTo
    {
        return $this->belongsTo(Membresia::class, 'membresia_id', 'uuid');
    }
}
