<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'taller_id',
        'role_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function taller(): BelongsTo
    {
        return $this->belongsTo(Taller::class);
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'role_id');
    }

    public function mecanico(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Mecanico::class);
    }

    public function esSuperAdmin(): bool
    {
        return $this->rol?->slug === Rol::SUPERADMIN;
    }

    public function esAdministradora(): bool
    {
        return $this->rol?->slug === Rol::ADMINISTRADORA;
    }

    public function esMecanico(): bool
    {
        return $this->rol?->slug === Rol::MECANICO;
    }
}
