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
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
