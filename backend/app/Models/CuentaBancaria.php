<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuentaBancaria extends Model
{
    use HasFactory;

    protected $table = 'cuentas_bancarias';

    protected $fillable = [
        'uuid',
        'user_id',
        'banco',
        'tipo_cuenta',
        'numero_cuenta',
        'nombre_titular',
        'rut_titular',
        'orden',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'orden'  => 'integer',
        ];
    }

    public function especialista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
