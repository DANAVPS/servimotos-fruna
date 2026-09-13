<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = ['nombre', 'slug'];

    public const SUPERADMIN = 'superadmin';
    public const ADMINISTRADORA = 'administradora';
    public const MECANICO = 'mecanico';

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
