<?php

namespace App\Services;

use App\Models\Repuesto;
use App\Models\RepuestoCompatibilidad;
use App\Support\Inventario\CompatibilidadParser;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventarioService
{
    private const CAMPOS_EDITABLES = [
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

    /**
     * @param array<string, mixed> $datos Campos del repuesto, más opcionalmente
     *   `compatibilidades` (texto "A / B" o arreglo) y `distribuidores`
     *   (lista de {distribuidor_id, codigo_proveedor, precio_costo_proveedor, tiempo_entrega_dias}).
     */
    public function crear(array $datos): Repuesto
    {
        return $this->guardar(new Repuesto(), $datos);
    }

    /**
     * Las claves ausentes en $datos no se tocan: si no llega `distribuidores`,
     * se conservan los vínculos actuales.
     */
    public function actualizar(Repuesto $repuesto, array $datos): Repuesto
    {
        return $this->guardar($repuesto, $datos);
    }

    /**
     * Borrado lógico. Los detalles de órdenes históricas siguen apuntando al
     * repuesto (DetalleOrden::repuesto usa withTrashed).
     */
    public function eliminar(Repuesto $repuesto): void
    {
        $repuesto->delete();
    }

    /**
     * Descuenta stock con bloqueo pesimista para evitar condiciones de
     * carrera cuando varias órdenes liquidan el mismo repuesto a la vez.
     */
    public function descontarStock(int $repuestoId, int $cantidad): Repuesto
    {
        return DB::transaction(function () use ($repuestoId, $cantidad) {
            $repuesto = Repuesto::lockForUpdate()->findOrFail($repuestoId);

            if ($repuesto->stock_actual < $cantidad) {
                throw new RuntimeException("Stock insuficiente para {$repuesto->nombre}.");
            }

            $repuesto->decrement('stock_actual', $cantidad);

            return $repuesto->fresh();
        });
    }

    /**
     * Devuelve unidades al inventario (corrección de factura, Bloque 3).
     * Incluye repuestos eliminados: la unidad física sí volvió al estante.
     */
    public function reponerStock(int $repuestoId, int $cantidad): Repuesto
    {
        return DB::transaction(function () use ($repuestoId, $cantidad) {
            $repuesto = Repuesto::withTrashed()->lockForUpdate()->findOrFail($repuestoId);

            $repuesto->increment('stock_actual', $cantidad);

            return $repuesto->fresh();
        });
    }

    /**
     * Repuestos que requieren alerta: el proveedor tarda de 2 a 5 días
     * hábiles, así que alertamos cuando el stock alcanza para menos de
     * 5 días de consumo promedio (regla 2.5 del prompt base).
     */
    public function repuestosConAlerta()
    {
        return Repuesto::stockBajo()->get();
    }

    /**
     * Inventario agrupado por sección. Los repuestos sin categoría quedan en
     * un grupo final con `categoria` = null.
     *
     * @return Collection<int, array{categoria: \App\Models\CategoriaRepuesto|null, repuestos: Collection<int, Repuesto>}>
     */
    public function listadoAgrupadoPorCategoria(?string $buscar = null, bool $soloStockBajo = false): Collection
    {
        $repuestos = Repuesto::query()
            ->with(['categoria', 'compatibilidades'])
            ->buscar($buscar)
            ->when($soloStockBajo, fn ($q) => $q->stockBajo())
            ->orderBy('nombre')
            ->get();

        return $repuestos
            ->groupBy(fn (Repuesto $r) => $r->categoria_repuesto_id ?? 0)
            ->map(fn (Collection $grupo) => [
                'categoria' => $grupo->first()->categoria,
                'repuestos' => $grupo->values(),
            ])
            ->sortBy([
                fn ($a, $b) => ($a['categoria'] === null) <=> ($b['categoria'] === null),
                fn ($a, $b) => ($a['categoria']?->orden ?? 0) <=> ($b['categoria']?->orden ?? 0),
                fn ($a, $b) => strcmp($a['categoria']?->nombre ?? '', $b['categoria']?->nombre ?? ''),
            ])
            ->values();
    }

    /**
     * Repuestos en stock bajo agrupados por su distribuidor preferido. Los que
     * no tienen distribuidor quedan en un grupo final con `distribuidor` = null.
     *
     * @return Collection<int, array{distribuidor: \App\Models\Distribuidor|null, repuestos: Collection<int, Repuesto>}>
     */
    public function alertasAgrupadasPorDistribuidor(): Collection
    {
        $repuestos = Repuesto::stockBajo()
            ->with(['distribuidores', 'compatibilidades', 'categoria'])
            ->orderBy('nombre')
            ->get();

        return $repuestos
            ->groupBy(fn (Repuesto $r) => $r->distribuidorPreferido()?->id ?? 0)
            ->map(fn (Collection $grupo) => [
                'distribuidor' => $grupo->first()->distribuidorPreferido(),
                'repuestos' => $grupo->values(),
            ])
            ->sortBy([
                fn ($a, $b) => ($a['distribuidor'] === null) <=> ($b['distribuidor'] === null),
                fn ($a, $b) => strcmp($a['distribuidor']?->nombre ?? '', $b['distribuidor']?->nombre ?? ''),
            ])
            ->values();
    }

    private function guardar(Repuesto $repuesto, array $datos): Repuesto
    {
        return DB::transaction(function () use ($repuesto, $datos) {
            $repuesto->fill(Arr::only($datos, self::CAMPOS_EDITABLES));
            $repuesto->save();

            if (array_key_exists('compatibilidades', $datos)) {
                $this->sincronizarCompatibilidades($repuesto, $datos['compatibilidades']);
            }

            if (isset($datos['distribuidores']) && is_array($datos['distribuidores'])) {
                $this->sincronizarDistribuidores($repuesto, $datos['distribuidores']);
            }

            $repuesto->actualizarIndiceBusqueda();

            return $repuesto->fresh(['categoria', 'compatibilidades', 'distribuidores']);
        });
    }

    /**
     * La tabla de compatibilidades es la fuente de verdad; `modelo_compatible`
     * queda como texto de presentación derivado ("Duke 200 / Pulsar NS 200").
     *
     * @param string|array<int, string>|null $entrada
     */
    private function sincronizarCompatibilidades(Repuesto $repuesto, string|array|null $entrada): void
    {
        $referencias = CompatibilidadParser::parsear($entrada);

        RepuestoCompatibilidad::where('repuesto_id', $repuesto->id)->delete();

        foreach ($referencias as $referencia) {
            RepuestoCompatibilidad::create([
                'taller_id' => $repuesto->taller_id,
                'repuesto_id' => $repuesto->id,
                'moto_referencia' => $referencia,
            ]);
        }

        $repuesto->forceFill([
            'modelo_compatible' => $referencias === [] ? null : mb_substr(implode(' / ', $referencias), 0, 255),
        ]);
    }

    /**
     * @param array<int, array{distribuidor_id: int, codigo_proveedor?: ?string, precio_costo_proveedor?: mixed, tiempo_entrega_dias?: mixed}> $vinculos
     */
    private function sincronizarDistribuidores(Repuesto $repuesto, array $vinculos): void
    {
        $mapa = [];

        foreach ($vinculos as $vinculo) {
            $mapa[(int) $vinculo['distribuidor_id']] = [
                'taller_id' => $repuesto->taller_id,
                'codigo_proveedor' => $vinculo['codigo_proveedor'] ?? null,
                'precio_costo_proveedor' => $vinculo['precio_costo_proveedor'] ?? null,
                'tiempo_entrega_dias' => $vinculo['tiempo_entrega_dias'] ?? null,
            ];
        }

        $repuesto->distribuidores()->sync($mapa);
    }
}
