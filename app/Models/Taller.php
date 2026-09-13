<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Taller extends Model
{
    // Indicar explícitamente el nombre de la tabla en español
    protected $table = 'talleres';

    protected $fillable = [
        'nombre',
        'nit',
        'telefono_whatsapp',
        'whatsapp_phone_number_id',
        'whatsapp_token',
        'plan',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_token' => 'encrypted',
            'activo' => 'boolean',
        ];
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function mecanicos(): HasMany
    {
        return $this->hasMany(Mecanico::class);
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function configuracion(): HasOne
    {
        return $this->hasOne(ConfiguracionTaller::class);
    }
}   
