<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DatoNatal extends Model
{
    use HasFactory;

    protected $table = 'datos_natales';

    protected $fillable = [
        'user_id',
        'fecha_nacimiento',
        'hora_nacimiento',
        'ciudad_nacimiento',
        'pais_nacimiento',
        'zona_horaria',
        'latitud',
        'longitud',
        'raw_json',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'hora_nacimiento' => 'datetime:H:i:s',
            'latitud' => 'decimal:7',
            'longitud' => 'decimal:7',
            'raw_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
