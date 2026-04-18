<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class PreferenciaNotificacion extends Model
{
    use HasFactory;

    protected $table = 'preferencia_notificaciones';

    protected $fillable = [
        'user_id',
        'email_recordatorios',
        'email_marketing',
        'whatsapp_recordatorios',
        'whatsapp_marketing',
        'zona_horaria',
        'canal_preferido',
    ];

    protected function casts(): array
    {
        return [
            'email_recordatorios' => 'boolean',
            'email_marketing' => 'boolean',
            'whatsapp_recordatorios' => 'boolean',
            'whatsapp_marketing' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
