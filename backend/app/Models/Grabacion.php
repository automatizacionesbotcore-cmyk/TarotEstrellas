<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Grabacion extends Model
{
    use HasFactory;

    protected $table = 'grabaciones';

    protected $fillable = [
        'uuid',
        'cita_id',
        'daily_webhook_event_id',
        'daily_recording_id',
        'daily_room_name',
        'url_grabacion',
        'estado',
        'descargada_por_cliente',
        'primera_descarga_en',
        'ultima_descarga_en',
        'total_descargas',
        'transcripcion_procesada_en',
        'resumen_generado_en',
        'expira_en',
        'borrada_en',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'transcripcion_procesada_en' => 'datetime',
            'resumen_generado_en' => 'datetime',
            'expira_en' => 'datetime',
            'borrada_en' => 'datetime',
            'descargada_por_cliente' => 'boolean',
            'primera_descarga_en' => 'datetime',
            'ultima_descarga_en' => 'datetime',
            'total_descargas' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function dailyWebhookEvent(): BelongsTo
    {
        return $this->belongsTo(DailyWebhookEvent::class);
    }

    public function transcripcion(): HasOne
    {
        return $this->hasOne(Transcripcion::class);
    }

    public function resumen(): HasOne
    {
        return $this->hasOne(Resumen::class);
    }
}