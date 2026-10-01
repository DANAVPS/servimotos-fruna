<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATaller;
use App\Support\TextoBusqueda;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Gate;

class Repuesto extends Model
{
    use HasFactory, PerteneceATaller, SoftDeletes;

    /**
     * Campos que solo la Administradora/SuperAdmin puede modificar.
     * Protegidos en el modelo además de la Policy (regla de negocio 8).
     */
    public const CAMPOS_PRECIO = ['precio_costo', 'precio_venta'];

    /** Campos que alimentan el índice de búsqueda. */
    private const CAMPOS_BUSQUEDA = ['nombre', 'marca', 'codigo_sku', 'referencia_proveedor'];

    protected $fillable = [
        'taller_id',
        'categoria_repuesto_id',
        'codigo_sku',
        'referencia_proveedor',
        'nombre',
        'marca',
        'precio_costo',
        'precio_venta',
        'stock_actual',
        'stock_minimo',
        'ubicacion_estante',
    ];

    protected $hidden = ['busqueda'];

    protected function casts(): array
    {
        return [
            'precio_costo' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'stock_actual' => 'integer',
            'stock_minimo' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Repuesto $repuesto) {
            $repuesto->verificarPermisoSobrePrecios();

            if (! $repuesto->exists || $repuesto->isDirty(self::CAMPOS_BUSQUEDA)) {
                $repuesto->busqueda = $repuesto->construirIndiceBusqueda();
            }
        });
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaRepuesto::class, 'categoria_repuesto_id');
    }

    public function compatibilidades(): HasMany
    {
        return $this->hasMany(RepuestoCompatibilidad::class)->orderBy('moto_referencia');
    }

    public function distribuidores(): BelongsToMany
    {
        return $this->belongsToMany(Distribuidor::class, 'repuesto_distribuidor')
            ->withPivot(['taller_id', 'codigo_proveedor', 'precio_costo_proveedor', 'tiempo_entrega_dias'])
            ->withTimestamps();
    }

    public function detallesOrden(): HasMany
    {
        return $this->hasMany(DetalleOrden::class);
    }

    public function historialMantenimiento(): HasMany
    {
        return $this->hasMany(HistorialMantenimiento::class);
    }

    /**
     * Repuestos cuyo stock está por debajo del mínimo configurado.
     * La lógica predictiva de "5 días hábiles" vive en InventarioService (Fase 2),
     * este scope cubre el caso simple de stock bajo el umbral fijo.
     */
    public function scopeStockBajo($query)
    {
        return $query->whereColumn(
            $query->getModel()->qualifyColumn('stock_actual'),
            '<=',
            $query->getModel()->qualifyColumn('stock_minimo')
        );
    }

    /**
     * Buscador único: nombre (que suele traer la marca del producto), marca,
     * SKU, referencia del proveedor y motos compatibles. Cada palabra escrita
     * debe aparecer en el índice, así "filtro duke 200" funciona. Se aplica
     * dentro de un `where` agrupado para no interferir con el TenantScope.
     */
    public function scopeBuscar($query, ?string $consulta)
    {
        $terminos = TextoBusqueda::terminos($consulta);

        if ($terminos === []) {
            return $query;
        }

        return $query->where(function ($q) use ($terminos) {
            foreach ($terminos as $termino) {
                $q->where($this->qualifyColumn('busqueda'), 'like', "%{$termino}%");
            }
        });
    }

    public function estaEnStockBajo(): bool
    {
        return $this->stock_actual <= $this->stock_minimo;
    }

    /**
     * Cantidad por defecto al pedir al distribuidor: reponer hasta el doble
     * del mínimo, y siempre al menos una unidad.
     */
    public function cantidadSugeridaPedido(): int
    {
        return max($this->stock_minimo * 2 - $this->stock_actual, 1);
    }

    /**
     * Distribuidor al que se le pide por defecto: el que entrega más rápido;
     * a igual tiempo, el más barato. Usa la relación cargada si existe.
     */
    public function distribuidorPreferido(): ?Distribuidor
    {
        return $this->distribuidores
            ->filter(fn (Distribuidor $d) => $d->activo)
            ->sortBy([
                fn ($a, $b) => ($a->pivot->tiempo_entrega_dias ?? PHP_INT_MAX) <=> ($b->pivot->tiempo_entrega_dias ?? PHP_INT_MAX),
                fn ($a, $b) => ($a->pivot->precio_costo_proveedor ?? PHP_INT_MAX) <=> ($b->pivot->precio_costo_proveedor ?? PHP_INT_MAX),
                fn ($a, $b) => $a->id <=> $b->id,
            ])
            ->first();
    }

    /**
     * Recalcula el índice incluyendo las compatibilidades actuales. Se llama
     * después de sincronizar compatibilidades (el evento `saving` no las ve).
     */
    public function actualizarIndiceBusqueda(): void
    {
        $this->busqueda = $this->construirIndiceBusqueda();
        $this->saveQuietly();
    }

    private function construirIndiceBusqueda(): string
    {
        $referencias = $this->exists
            ? $this->compatibilidades()->pluck('moto_referencia')->all()
            : [];

        return TextoBusqueda::indice([
            $this->nombre,
            $this->marca,
            $this->codigo_sku,
            $this->referencia_proveedor,
            ...$referencias,
        ]);
    }

    /**
     * Última línea de defensa de la regla 8: aunque un endpoint futuro olvide
     * la Policy, un usuario sin permiso no puede crear ni alterar precios.
     * Procesos sin usuario (seeders, jobs, consola) no pasan por aquí.
     */
    private function verificarPermisoSobrePrecios(): void
    {
        $usuario = auth()->user();

        if ($usuario === null || ! $this->isDirty(self::CAMPOS_PRECIO)) {
            return;
        }

        if (Gate::forUser($usuario)->denies('gestionarPrecios', self::class)) {
            throw new AuthorizationException('No tienes permiso para modificar precios de repuestos.');
        }
    }
}
