<?php

namespace App\Models;

use App\Support\Identificadores;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /** "abc-12d" se guarda como "ABC12D", igual que se busca. */
    protected function placa(): Attribute
    {
        return Attribute::make(set: fn (?string $valor) => Identificadores::placa($valor));
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
