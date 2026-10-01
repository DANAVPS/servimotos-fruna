<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATaller;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CategoriaRepuesto extends Model
{
    use HasFactory, PerteneceATaller;

    protected $table = 'categorias_repuesto';

    protected $fillable = [
        'taller_id',
        'nombre',
        'slug',
        'orden',
        'activo',
    ];

    /**
     * Categorías con las que arranca todo taller (slug => nombre).
     * El orden del arreglo es el orden de las secciones en el inventario.
     */
    public const BASE = [
        'filtros' => 'Filtros',
        'culatin' => 'Culatín',
        'culata' => 'Culata',
        'clutch' => 'Clutch',
        'tapa-bolante' => 'Tapa bolante',
        'pistas-cunas' => 'Pistas (Cunas)',
        'canastilla' => 'Canastilla',
        'cunas' => 'Cunas',
        'bujes-de-tijera' => 'Bujes de tijera',
        'fuelles-de-suspension' => 'Fuelles de suspensión',
        'balancines' => 'Balancines',
        'arbol-de-levas' => 'Árbol de levas',
        'kit-cilindro' => 'Kit cilindro',
        'cilindro-piston' => 'Cilindro pistón',
        'bobinas' => 'Bobinas',
        'relay' => 'Relay',
        'regulador' => 'Regulador',
        'conector-filtro-de-aire' => 'Conector filtro de aire',
        'bujias' => 'Bujías',
        'empaques-varios' => 'Empaques (varios)',
        'tensor' => 'Tensor',
        'cadenillas' => 'Cadenillas',
        'cadena' => 'Cadena',
        'kit-de-arrastre' => 'Kit de arrastre',
        'bateria' => 'Batería',
        'frenos' => 'Frenos',
        'lubricante' => 'Lubricante',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CategoriaRepuesto $categoria) {
            if (empty($categoria->slug)) {
                $categoria->slug = Str::slug($categoria->nombre);
            }
        });
    }

    public function repuestos(): HasMany
    {
        return $this->hasMany(Repuesto::class);
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }
}
