<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\PerteneceATaller;


class Moto extends Model
{
    use HasFactory, PerteneceATaller;

    protected $fillable = [
        'placa',
        'marca',
        'modelo',
        'anio',
        'cliente_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Moto $moto) {
            $moto->placa = strtoupper($moto->placa);
        });
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
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
}
