<?php

namespace App\Models;

use App\Scopes\ClienteActivoScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'telefono',
        'correo',
        'fecha_ultima_visita',
        'estado_cliente',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ultima_visita' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new ClienteActivoScope());
    }

    public function motos(): HasMany
    {
        return $this->hasMany(Moto::class);
    }

    public function ordenesServicio(): HasMany
    {
        return $this->hasMany(OrdenServicio::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    public function historialFallas(): HasMany
    {
        return $this->hasMany(HistorialFalla::class);
    }

    public function scopeActivo($query)
    {
        return $query->where('estado_cliente', 'activo');
    }

    public function scopeHibernando($query)
    {
        return $query->where('estado_cliente', 'hibernando');
    }

    public function scopeArchivado($query)
    {
        return $query->withoutGlobalScope(ClienteActivoScope::class)
            ->where('estado_cliente', 'archivado');
    }
}
