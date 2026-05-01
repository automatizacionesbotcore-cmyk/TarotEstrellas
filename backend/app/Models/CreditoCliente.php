<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CreditoCliente extends Model
{
    protected $table = 'creditos_cliente';

    protected $fillable = [
        'uuid', 'cliente_id', 'origen', 'monto_centavos', 'moneda',
        'vigente_hasta', 'estado', 'descripcion',
        'cita_origen_id', 'cita_uso_id', 'creado_por', 'usado_en',
    ];

    protected $casts = [
        'monto_centavos' => 'integer',
        'vigente_hasta'  => 'datetime',
        'usado_en'       => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->uuid)) {
                $m->uuid = (string) Str::uuid();
            }
        });
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function citaOrigen(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_origen_id');
    }

    public function citaUso(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_uso_id');
    }

    public function estaDisponible(): bool
    {
        if ($this->estado !== 'disponible') {
            return false;
        }
        if ($this->vigente_hasta && now()->gt($this->vigente_hasta)) {
            return false;
        }
        return true;
    }

    public function scopeDisponibles($q)
    {
        return $q->where('estado', 'disponible')
                 ->where(function ($w) {
                     $w->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', now());
                 });
    }
}
