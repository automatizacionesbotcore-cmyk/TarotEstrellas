<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Consentimiento extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tipo',
        'version_documento',
        'otorgado',
        'otorgado_en',
        'ip_otorgamiento',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'otorgado' => 'boolean',
            'otorgado_en' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
