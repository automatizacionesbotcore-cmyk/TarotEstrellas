<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AgenteConversacion extends Model
{
    use HasFactory;

    protected $table = 'agente_conversaciones';

    protected $fillable = [
        'uuid',
        'cliente_id',
        'autor_user_id',
        'autor_rol',
        'pregunta',
        'respuesta',
        'proveedor',
        'modelo',
        'tokens_in',
        'tokens_out',
        'latencia_ms',
        'contexto_sesiones',
        'error',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $row): void {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_user_id');
    }
}
