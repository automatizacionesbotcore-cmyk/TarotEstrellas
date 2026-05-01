<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantillaNotificacionVersion extends Model
{
    protected $table = 'plantillas_notificacion_versiones';

    protected $fillable = ['plantilla_id', 'version', 'asunto', 'cuerpo', 'autor_id', 'creado_en'];

    protected function casts(): array
    {
        return ['creado_en' => 'datetime', 'version' => 'integer'];
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaNotificacion::class, 'plantilla_id');
    }
}
