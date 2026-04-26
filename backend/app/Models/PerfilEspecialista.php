<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerfilEspecialista extends Model
{
    protected $table = 'perfil_especialista';

    protected $fillable = [
        'user_id',
        'slug',
        'especialidad',
        'activo',
        'orden_display',
    ];

    protected function casts(): array
    {
        return [
            'activo'         => 'boolean',
            'orden_display'  => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
