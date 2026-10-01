<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATaller;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tarifa fija por tipo de cliente (Regular, VIP/Flota, Distribuidor…). Cada
 * taller define sus tipos y porcentajes. El descuento aplica SOLO a repuestos,
 * nunca a la mano de obra, y se congela en detalles_orden al liquidar.
 */
class TipoCliente extends Model
{
    use HasFactory, PerteneceATaller;

    protected $table = 'tipos_cliente';

    /** Tipo de respaldo para clientes sin tipo asignado. No se puede desactivar. */
    public const SLUG_REGULAR = 'regular';

    /** Tipos con los que arranca todo taller (el % es editable después). */
    public const BASE = [
        self::SLUG_REGULAR => [
            'nombre' => 'Regular',
            'descuento_repuestos_pct' => 0,
            'descripcion' => 'Paga el precio de lista.',
        ],
        'vip-flota' => [
            'nombre' => 'VIP / Flota',
            'descuento_repuestos_pct' => 10,
            'descripcion' => 'Descuento permanente en repuestos para clientes frecuentes, flotas de empresa o distribuidores.',
        ],
    ];

    protected $fillable = [
        'taller_id',
        'nombre',
        'slug',
        'descuento_repuestos_pct',
        'descripcion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'descuento_repuestos_pct' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function esRegular(): bool
    {
        return $this->slug === self::SLUG_REGULAR;
    }

    public function tieneDescuento(): bool
    {
        return (float) $this->descuento_repuestos_pct > 0;
    }

    /**
     * ÚNICA fórmula de precio por tipo de cliente. Toda liquidación, vista
     * previa o reporte debe pasar por aquí. Se redondea a pesos enteros
     * (el COP no usa centavos) y nunca devuelve un valor negativo.
     */
    public function calcularPrecioConDescuento(float|int|string $precioLista): float
    {
        $porcentaje = min(max((float) $this->descuento_repuestos_pct, 0), 100);

        return max(round((float) $precioLista * (1 - $porcentaje / 100)), 0);
    }

    /**
     * Tipo Regular del taller. Si por alguna razón no existe, devuelve uno en
     * memoria con 0%, para que nunca se aplique un descuento por accidente.
     */
    public static function regularDelTaller(?int $tallerId): self
    {
        return static::withoutGlobalScopes()
            ->where('taller_id', $tallerId)
            ->where('slug', self::SLUG_REGULAR)
            ->first()
            ?? new static([...self::BASE[self::SLUG_REGULAR], 'slug' => self::SLUG_REGULAR, 'taller_id' => $tallerId]);
    }
}
