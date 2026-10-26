<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\PerteneceATaller;

class Mecanico extends Model
{
    use HasFactory, PerteneceATaller;

    public const TIPOS = [
        'titular' => 'Titular',
        'ayudante' => 'Ayudante',
    ];

    protected $fillable = [
        'nombre',
        'especialidad',
        'disponibilidad',
        'telefono',
        'tipo',
        'mecanico_principal_id',
        'activo',
        // Cuenta con la que entra a /mi-dia (única: un usuario, un mecánico).
        'user_id',
    ];

    // Una instancia recién creada refleja los defaults de la base sin recargarla.
    protected $attributes = [
        'tipo' => 'titular',
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'disponibilidad' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function ordenesServicio(): HasMany
    {
        return $this->hasMany(OrdenServicio::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Titular del que depende un ayudante. */
    public function principal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mecanico_principal_id');
    }

    /** Ayudantes a cargo de un titular. */
    public function subordinados(): HasMany
    {
        return $this->hasMany(self::class, 'mecanico_principal_id');
    }

    public function esTitular(): bool
    {
        return $this->tipo === 'titular';
    }

    public function esAyudante(): bool
    {
        return $this->tipo === 'ayudante';
    }

    public function scopeDisponible($query)
    {
        return $query->where('disponibilidad', true);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeTitulares($query)
    {
        return $query->where('tipo', 'titular');
    }

    public function scopeAyudantes($query)
    {
        return $query->where('tipo', 'ayudante');
    }
}
