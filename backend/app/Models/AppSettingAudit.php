<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppSettingAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_setting_id',
        'key',
        'old_value',
        'new_value',
        'changed_by_user_id',
        'ip_address',
        'user_agent',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function appSetting(): BelongsTo
    {
        return $this->belongsTo(AppSetting::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
