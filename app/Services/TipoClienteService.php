<?php

namespace App\Services;

use App\Models\Repuesto;
use App\Models\Taller;
use App\Models\TipoCliente;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TipoClienteService
{
    private const CAMPOS = ['nombre', 'descuento_repuestos_pct', 'descripcion', 'activo'];

    private const EJEMPLOS_BAJO_COSTO = 5;

    /**
     * Crea los tipos base que le falten al taller (idempotente).
     */
    public function crearBase(Taller $taller): void
    {
        $existentes = TipoCliente::withoutGlobalScopes()
            ->where('taller_id', $taller->id)
            ->pluck('slug')
            ->all();

        foreach (TipoCliente::BASE as $slug => $tipo) {
            if (! in_array($slug, $existentes, true)) {
                TipoCliente::create([...$tipo, 'slug' => $slug, 'taller_id' => $taller->id]);
            }
        }
    }

    public function crear(array $datos): TipoCliente
    {
        return TipoCliente::create([
            ...Arr::only($datos, self::CAMPOS),
            'slug' => $this->slugDisponible($datos['nombre']),
        ]);
    }

    /**
     * El slug no cambia al renombrar: identifica al tipo Regular de respaldo.
     */
    public function actualizar(TipoCliente $tipo, array $datos): TipoCliente
    {
        $tipo->update(Arr::only($datos, self::CAMPOS));

        return $tipo->fresh();
    }

    /**
     * Qué pasa con el inventario del taller si se aplica este descuento:
     * cuántos repuestos quedarían por debajo del costo (advertencia, no bloqueo:
     * "dejarlo a precio distribuidor" puede ser margen cero a propósito).
     *
     * @return array{porcentaje: float, total_repuestos: int, bajo_costo: int, ejemplos: array<int, array<string, mixed>>}
     */
    public function analizarDescuento(float $porcentaje): array
    {
        $tipo = new TipoCliente(['descuento_repuestos_pct' => $porcentaje]);
        $factor = 1 - min(max($porcentaje, 0), 100) / 100;

        $consulta = Repuesto::where('precio_costo', '>', 0)
            ->whereRaw('ROUND(precio_venta * ?) < precio_costo', [$factor]);

        $ejemplos = (clone $consulta)
            ->orderByRaw('(precio_costo - ROUND(precio_venta * ?)) DESC', [$factor])
            ->limit(self::EJEMPLOS_BAJO_COSTO)
            ->get(['id', 'nombre', 'precio_costo', 'precio_venta'])
            ->map(fn (Repuesto $r) => [
                'nombre' => $r->nombre,
                'precio_venta' => (float) $r->precio_venta,
                'precio_con_descuento' => $tipo->calcularPrecioConDescuento($r->precio_venta),
                'precio_costo' => (float) $r->precio_costo,
            ])
            ->all();

        return [
            'porcentaje' => $porcentaje,
            'total_repuestos' => Repuesto::count(),
            'bajo_costo' => $consulta->count(),
            'ejemplos' => $ejemplos,
        ];
    }

    private function slugDisponible(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'tipo';
        $slug = $base;
        $sufijo = 2;

        while (TipoCliente::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$sufijo}";
            $sufijo++;
        }

        return $slug;
    }
}
