<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'user_email', 'user_role', 'action',
        'auditable_type', 'auditable_id', 'changes',
        'route', 'method', 'url', 'ip', 'user_agent', 'payload', 'status_code',
    ];

    protected $casts = [
        'changes' => 'array',
        'payload' => 'array',
        'status_code' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
