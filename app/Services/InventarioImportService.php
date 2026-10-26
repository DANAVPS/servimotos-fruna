<?php

namespace App\Services;

use App\Models\CategoriaRepuesto;
use App\Models\Repuesto;
use App\Models\Taller;
use App\Support\Inventario\InventarioCsvParser;
use App\Support\TenantManager;
use Illuminate\Support\Facades\DB;

/**
 * Carga la plantilla de inventario de un taller. Es idempotente y no
 * destructiva: si un repuesto ya existe (por SKU, o por nombre + categoría +
 * compatibilidad cuando no tiene SKU) se deja como está, para no pisar las
 * correcciones que la Administradora haya hecho desde el panel. Tampoco
 * revive repuestos eliminados.
 */
class InventarioImportService
{
    public function __construct(
        private InventarioService $inventario,
        private CategoriaRepuestoService $categorias,
        private TenantManager $tenant,
    ) {
    }

    /**
     * @param array{precios?: array<string, float|int>, fusionar?: array<int, array<int, string>>} $correcciones
     *   Ver InventarioCsvParser::aplicarCorrecciones().
     * @return array{
     *   creados: int,
     *   existentes: int,
     *   filas: array<int, array<string, mixed>>,
     *   omitidas: array<int, array{linea: int, motivo: string}>
     * }
     */
    public function importar(string $ruta, Taller $taller, int $stockMinimoPorDefecto = 1, array $correcciones = []): array
    {
        $parser = new InventarioCsvParser($stockMinimoPorDefecto);
        $resultado = $parser->aplicarCorrecciones($parser->parsearArchivo($ruta), $correcciones);

        $tallerAnterior = $this->tenant->tallerId();
        $this->tenant->establecer($taller->id);

        try {
            $this->categorias->crearBase($taller);
            $categorias = CategoriaRepuesto::pluck('id', 'slug');

            [$creados, $existentes] = DB::transaction(function () use ($resultado, $categorias) {
                $creados = 0;
                $existentes = 0;

                foreach ($resultado['filas'] as $fila) {
                    $categoriaId = $categorias[$fila['categoria_slug']] ?? null;

                    if ($this->yaExiste($fila, $categoriaId)) {
                        $existentes++;
                        continue;
                    }

                    $this->inventario->crear([
                        'categoria_repuesto_id' => $categoriaId,
                        'codigo_sku' => $fila['codigo_sku'],
                        'referencia_proveedor' => $fila['referencia_proveedor'],
                        'nombre' => $fila['nombre'],
                        'precio_costo' => $fila['precio_costo'],
                        'precio_venta' => $fila['precio_venta'],
                        'stock_actual' => $fila['stock_actual'],
                        'stock_minimo' => $fila['stock_minimo'],
                        'ubicacion_estante' => $fila['ubicacion_estante'],
                        'compatibilidades' => $fila['compatibilidades'],
                    ]);

                    $creados++;
                }

                return [$creados, $existentes];
            });
        } finally {
            $this->tenant->establecer($tallerAnterior);
        }

        return [
            'creados' => $creados,
            'existentes' => $existentes,
            'filas' => $resultado['filas'],
            'omitidas' => $resultado['omitidas'],
        ];
    }

    /**
     * @param array<string, mixed> $fila
     */
    private function yaExiste(array $fila, ?int $categoriaId): bool
    {
        $consulta = Repuesto::withTrashed();

        if ($fila['codigo_sku'] !== null) {
            return $consulta->where('codigo_sku', $fila['codigo_sku'])->exists();
        }

        $compatibilidad = $fila['compatibilidades'] === [] ? null : implode(' / ', $fila['compatibilidades']);

        return $consulta
            ->whereNull('codigo_sku')
            ->where('nombre', $fila['nombre'])
            ->where('categoria_repuesto_id', $categoriaId)
            ->where(fn ($q) => $compatibilidad === null
                ? $q->whereNull('modelo_compatible')
                : $q->where('modelo_compatible', mb_substr($compatibilidad, 0, 255)))
            ->exists();
    }
}
