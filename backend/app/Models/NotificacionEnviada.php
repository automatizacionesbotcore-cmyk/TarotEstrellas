<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificacionEnviada extends Model
{
    protected $table = 'notificaciones_enviadas';

    protected $fillable = [
        'uuid', 'user_id', 'canal', 'tipo', 'destinatario',
        'asunto', 'preview', 'estado', 'proveedor', 'proveedor_id',
        'metadata', 'enviado_en',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'enviado_en' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
