<?php

namespace App\Services;

use App\Models\OrdenServicio;
use App\Models\Repuesto;
use App\Scopes\ClienteActivoScope;
use App\Support\TarjetaOrden;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Datos del dashboard ejecutivo (solo administración). Todo sale ya armado
 * para JSON: la vista inicial y el endpoint de consulta periódica usan lo mismo.
 *
 * - Caja, métodos de pago, ingresos y comisiones: órdenes PAGADAS en el periodo
 *   (por `pagada_at`), porque es dinero cobrado.
 * - Cuentas por cobrar, motos en riesgo y stock: estado actual, sin periodo.
 *
 * El TenantScope filtra por el taller del usuario (el SuperAdmin ve todos).
 */
class DashboardService
{
    public const PERIODOS = ['semanal', 'mensual'];

    private const ZONA_HORARIA = 'America/Bogota';

    public function __construct(private InventarioService $inventario)
    {
    }

    public function resumen(string $periodo, ?int $tallerId): array
    {
        [$desde, $hasta] = $this->rango($periodo);
        $config = TarjetaOrden::configuracionDe($tallerId);

        // Desde/hasta en la zona de la app (UTC) para comparar con la base.
        // Columnas calificadas: algunos indicadores hacen join con clientes y mecánicos.
        $pagadas = fn () => OrdenServicio::query()
            ->where('ordenes_servicio.estado', 'pagada')
            ->whereBetween('ordenes_servicio.pagada_at', [$desde->setTimezone(config('app.timezone')), $hasta->setTimezone(config('app.timezone'))]);

        return [
            'periodo' => [
                'clave' => $periodo,
                'desde' => $desde->format('d/m/Y'),
                'hasta' => $hasta->format('d/m/Y'),
                'etiqueta' => $periodo === 'semanal' ? 'Esta semana' : 'Este mes',
            ],
            'caja' => $this->caja($pagadas()),
            'metodos_pago' => $this->metodosPago($pagadas()),
            'ingresos' => $this->ingresos($pagadas()),
            'descuentos' => $this->descuentosPorTipo($pagadas()),
            'mecanicos' => $this->rendimientoMecanicos($pagadas()),
            'por_cobrar' => $this->cuentasPorCobrar(),
            'riesgo' => $this->motosEnRiesgo($config),
            'bitacora' => $this->bitacora(),
            'stock' => $this->stockCritico(),
            'generado' => now(self::ZONA_HORARIA)->format('h:i:s a'),
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} en hora de Colombia */
    public function rango(string $periodo): array
    {
        $ahora = CarbonImmutable::now(self::ZONA_HORARIA);

        $desde = $periodo === 'mensual'
            ? $ahora->startOfMonth()
            : $ahora->startOfWeek(CarbonInterface::MONDAY);

        return [$desde, $ahora];
    }

    private function caja($pagadas): array
    {
        $fila = $pagadas->selectRaw('count(*) as ordenes, coalesce(sum(ordenes_servicio.total), 0) as total, coalesce(sum(ordenes_servicio.base_gravable), 0) as base, coalesce(sum(ordenes_servicio.iva), 0) as iva')->first();

        return [
            'ordenes' => (int) $fila->ordenes,
            'total' => (float) $fila->total,
            'sin_iva' => (float) $fila->base,
            'iva' => (float) $fila->iva,
        ];
    }

    /** Métodos en orden fijo; las pagadas antes del Bloque 3 caen en "Sin registrar". */
    private function metodosPago($pagadas): array
    {
        $filas = $pagadas->selectRaw("coalesce(metodo_pago, 'sin_registrar') as metodo, count(*) as ordenes, coalesce(sum(total), 0) as total")
            ->groupByRaw("coalesce(metodo_pago, 'sin_registrar')")
            ->get()
            ->keyBy('metodo');

        $etiquetas = OrdenServicio::METODOS_PAGO + ['sin_registrar' => 'Sin registrar'];

        return collect($etiquetas)
            ->map(fn ($etiqueta, $clave) => [
                'clave' => $clave,
                'etiqueta' => $etiqueta,
                'ordenes' => (int) ($filas[$clave]->ordenes ?? 0),
                'total' => (float) ($filas[$clave]->total ?? 0),
            ])
            // "Sin registrar" solo aparece si hay órdenes así.
            ->reject(fn ($fila) => $fila['clave'] === 'sin_registrar' && $fila['ordenes'] === 0)
            ->values()
            ->all();
    }

    /** Lo facturado y cobrado en mano de obra y en repuestos (con descuento aplicado). */
    private function ingresos($pagadas): array
    {
        $fila = $pagadas->selectRaw('coalesce(sum(mano_obra), 0) as mano_obra, coalesce(sum(subtotal_repuestos), 0) as repuestos')->first();

        return [
            'mano_obra' => (float) $fila->mano_obra,
            'repuestos' => (float) $fila->repuestos,
        ];
    }

    /** Descuento otorgado por tipo de cliente (tipo actual del cliente; el % aplicado es snapshot). */
    private function descuentosPorTipo($pagadas): array
    {
        return $pagadas
            ->where('ordenes_servicio.descuento_total', '>', 0)
            ->join('clientes', 'clientes.id', '=', 'ordenes_servicio.cliente_id')
            ->leftJoin('tipos_cliente', 'tipos_cliente.id', '=', 'clientes.tipo_cliente_id')
            ->selectRaw("coalesce(tipos_cliente.nombre, 'Regular') as tipo, count(*) as ordenes, sum(ordenes_servicio.descuento_total) as descuento")
            ->groupByRaw("coalesce(tipos_cliente.nombre, 'Regular')")
            ->orderByDesc('descuento')
            ->get()
            ->map(fn ($fila) => [
                'tipo' => $fila->tipo,
                'ordenes' => (int) $fila->ordenes,
                'descuento' => (float) $fila->descuento,
            ])
            ->all();
    }

    /** Mano de obra cobrada por mecánico (base de comisiones), de mayor a menor. */
    private function rendimientoMecanicos($pagadas): array
    {
        return $pagadas
            ->leftJoin('mecanicos', 'mecanicos.id', '=', 'ordenes_servicio.mecanico_id')
            ->selectRaw("ordenes_servicio.mecanico_id, coalesce(mecanicos.nombre, 'Sin asignar') as nombre, mecanicos.tipo, count(*) as ordenes, coalesce(sum(ordenes_servicio.mano_obra), 0) as mano_obra")
            ->groupBy('ordenes_servicio.mecanico_id', 'mecanicos.nombre', 'mecanicos.tipo')
            ->orderByDesc('mano_obra')
            ->get()
            ->map(fn ($fila) => [
                'nombre' => $fila->nombre,
                'tipo' => $fila->tipo,
                'ordenes' => (int) $fila->ordenes,
                'mano_obra' => (float) $fila->mano_obra,
            ])
            ->all();
    }

    /** Liquidadas sin pagar: dinero que el taller espera cobrar (sin periodo). */
    private function cuentasPorCobrar(): array
    {
        $fila = OrdenServicio::query()->porPagar()
            ->selectRaw('count(*) as ordenes, coalesce(sum(total), 0) as total, min(liquidada_at) as mas_antigua')
            ->first();

        return [
            'ordenes' => (int) $fila->ordenes,
            'total' => (float) $fila->total,
            'dias_mas_antigua' => $fila->mas_antigua
                ? (int) floor(Carbon::parse($fila->mas_antigua)->diffInDays(now()))
                : null,
        ];
    }

    /** Órdenes abiertas en amarillo o rojo según el semáforo del taller. */
    private function motosEnRiesgo($config): array
    {
        $abiertas = OrdenServicio::query()
            ->where('estado', '!=', 'pagada')
            ->with('moto:id,placa,marca,modelo', 'mecanico:id,nombre')
            ->orderBy('fecha_hora_ingreso')
            ->get();

        $conColor = $abiertas->map(fn (OrdenServicio $orden) => [
            'id' => $orden->id,
            'placa' => $orden->moto->placa,
            'moto' => trim("{$orden->moto->marca} {$orden->moto->modelo}"),
            'estado' => TarjetaOrden::ETIQUETAS_ESTADO[$orden->estado],
            'mecanico' => $orden->mecanico?->nombre,
            'dias' => $orden->diasEnTaller(),
            'semaforo' => $orden->semaforo($config),
            'url' => route('ordenes.show', $orden),
        ]);

        return [
            'rojas' => $conColor->where('semaforo', 'rojo')->count(),
            'amarillas' => $conColor->where('semaforo', 'amarillo')->count(),
            'umbral_rojo' => $config->dias_alerta_roja,
            // Las más demoradas primero (máximo 8).
            'lista' => $conColor->where('semaforo', 'rojo')->sortByDesc('dias')->take(8)->values()->all(),
        ];
    }

    /** Últimas 5 órdenes pagadas con sus observaciones técnicas. */
    private function bitacora(): array
    {
        return OrdenServicio::query()
            ->pagada()
            ->with([
                'moto:id,placa,marca,modelo',
                'mecanico:id,nombre',
                'cliente' => fn ($q) => $q->withoutGlobalScope(ClienteActivoScope::class)->select('id', 'nombre'),
            ])
            ->orderByDesc('pagada_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (OrdenServicio $orden) => [
                'id' => $orden->id,
                'numero_factura' => $orden->numero_factura,
                'placa' => $orden->moto->placa,
                'moto' => trim("{$orden->moto->marca} {$orden->moto->modelo}"),
                'cliente' => $orden->cliente?->nombre,
                'mecanico' => $orden->mecanico?->nombre,
                'total' => (float) $orden->total,
                'metodo_pago' => $orden->metodoPagoEtiqueta(),
                'pagada' => $orden->pagada_at?->timezone(self::ZONA_HORARIA)->format('d/m h:i a'),
                'observaciones' => $orden->observaciones_tecnicas ? Str::limit($orden->observaciones_tecnicas, 160) : null,
                'url' => route('ordenes.show', $orden),
            ])
            ->all();
    }

    /**
     * Stock crítico agrupado por distribuidor preferido (reutiliza la
     * agrupación de InventarioService). Crítico = stock ESTRICTAMENTE por
     * debajo del mínimo: en el mínimo está "en el límite" y no se lista aquí
     * (el Inventario sigue alertando con stock ≤ mínimo).
     *
     * Orden dentro de cada grupo: agotados primero, luego por proporción
     * stock/mínimo (lo más vacío arriba) y por unidades faltantes.
     */
    private function stockCritico(): array
    {
        return $this->inventario->alertasAgrupadasPorDistribuidor()
            ->map(function ($grupo) {
                $criticos = $grupo['repuestos']
                    ->filter(fn (Repuesto $r) => (int) $r->stock_actual < (int) $r->stock_minimo)
                    ->sort(fn (Repuesto $a, Repuesto $b) => [
                        $a->stock_actual > 0,
                        $a->stock_actual / max($a->stock_minimo, 1),
                        $b->stock_minimo - $b->stock_actual,
                        $a->nombre,
                    ] <=> [
                        $b->stock_actual > 0,
                        $b->stock_actual / max($b->stock_minimo, 1),
                        $a->stock_minimo - $a->stock_actual,
                        $b->nombre,
                    ])
                    ->values();

                return [
                    'distribuidor' => $grupo['distribuidor']?->nombre,
                    'url_solicitud' => $grupo['distribuidor'] ? route('distribuidores.solicitud', $grupo['distribuidor']) : null,
                    'agotados' => $criticos->where('stock_actual', 0)->count(),
                    'repuestos' => $criticos->map(fn (Repuesto $r) => [
                        'id' => $r->id,
                        'nombre' => $r->nombre,
                        // Para distinguir repuestos con el mismo nombre ("Filtro de aire" ×6).
                        'codigo' => $r->codigo_sku ?: $r->referencia_proveedor,
                        'compatibilidad' => $r->modelo_compatible ? Str::limit($r->modelo_compatible, 40) : null,
                        'stock' => (int) $r->stock_actual,
                        'minimo' => (int) $r->stock_minimo,
                    ])->all(),
                ];
            })
            ->filter(fn ($grupo) => $grupo['repuestos'] !== [])
            ->values()
            ->all();
    }
}
