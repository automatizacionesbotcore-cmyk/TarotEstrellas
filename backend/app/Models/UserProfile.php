<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nombre',
        'apellido',
        'telefono',
        'telefono_pais',
        'pais_residencia',
        'idioma_preferido',
        'zona_horaria',
        'genero',
        'fecha_nacimiento_publica',
        'avatar_url',
        'biografia',
        'notas_admin',
        'notas_admin_actualizadas_por',
        'notas_admin_actualizadas_en',
        'perfil_completado_en',
    ];

    protected $casts = [
        'fecha_nacimiento_publica' => 'date',
        'perfil_completado_en' => 'datetime',
        'notas_admin_actualizadas_en' => 'datetime',
    ];

    public const CAMPOS_REQUERIDOS = [
        'nombre',
        'apellido',
        'telefono',
        'telefono_pais',
        'pais_residencia',
        'fecha_nacimiento_publica',
    ];

    public function estaCompleto(): bool
    {
        if ($this->perfil_completado_en !== null) {
            return true;
        }
        foreach (self::CAMPOS_REQUERIDOS as $campo) {
            if (empty($this->$campo)) {
                return false;
            }
        }
        return true;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
