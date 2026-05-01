<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantillaNotificacion extends Model
{
    protected $table = 'plantillas_notificacion';

    protected $fillable = [
        'codigo', 'canal', 'nombre', 'asunto', 'cuerpo',
        'variables_disponibles', 'version', 'activa', 'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'variables_disponibles' => 'array',
            'activa' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(PlantillaNotificacionVersion::class, 'plantilla_id');
    }
}
