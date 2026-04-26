<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'uuid',
        'email',
        'password',
        'provider',
        'provider_id',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at'           => 'datetime',
            'deleted_at'              => 'datetime',
            'password'                => 'hashed',
            'two_factor_secret'       => 'encrypted',
        ];
    }

    protected static function booted()
    {
        static::creating(function (self $user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function datoNatal(): HasOne
    {
        return $this->hasOne(DatoNatal::class);
    }

    public function consentimientos(): HasMany
    {
        return $this->hasMany(Consentimiento::class);
    }

    public function preferenciaNotificacion(): HasOne
    {
        return $this->hasOne(PreferenciaNotificacion::class);
    }

    public function perfilEspecialista(): HasOne
    {
        return $this->hasOne(\App\Models\PerfilEspecialista::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['asignado_en', 'asignado_por']);
    }

    public function hasRole(string $roleName): bool
    {
        if (! $this->relationLoaded('roles')) {
            $this->load('roles');
        }

        /** @var Collection<int, Role> $roles */
        $roles = $this->roles;

        return $roles->contains(fn (Role $role) => $role->nombre === $roleName);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('super_admin') || $this->hasRole('admin_especialista');
    }
}
