<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Membresia extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'cliente_id', 'paquete_id', 'pago_inicial_id',
        'fecha_inicio', 'fecha_fin', 'consultas_incluidas', 'consultas_usadas', 'estado',
    ];

    protected $casts = [
        'fecha_inicio'         => 'date',
        'fecha_fin'            => 'date',
        'consultas_incluidas'  => 'integer',
        'consultas_usadas'     => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function paquete(): BelongsTo
    {
        return $this->belongsTo(Paquete::class);
    }

    public function consultasRestantes(): int
    {
        return max(0, $this->consultas_incluidas - $this->consultas_usadas);
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'activa'
            && $this->consultasRestantes() > 0
            && now()->lte($this->fecha_fin);
    }
}
