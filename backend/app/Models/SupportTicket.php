<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'codigo',
        'user_id',
        'nombre',
        'email',
        'tipo_error',
        'asunto',
        'descripcion',
        'estado',
        'prioridad',
        'asignado_a',
        'ultimo_cambio_at',
        'resuelto_at',
    ];

    protected function casts(): array
    {
        return [
            'ultimo_cambio_at' => 'datetime',
            'resuelto_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $ticket) {
            $ticket->uuid ??= (string) Str::uuid();
            $ticket->codigo ??= self::newCode();
            $ticket->ultimo_cambio_at ??= now();
        });
    }

    public static function newCode(): string
    {
        do {
            $code = 'TE-SOP-' . strtoupper(Str::random(6));
        } while (self::where('codigo', $code)->exists());

        return $code;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class);
    }
}
