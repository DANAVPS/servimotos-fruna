<?php

namespace App\Services;

use App\Models\ConfiguracionTaller;
use App\Models\DetalleOrden;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Scopes\ClienteActivoScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class FacturaService
{
    public function __construct(
        private InventarioService $inventarioService,
        private FacturaJsonService $facturaJson,
    ) {
    }

    /**
     * Paso 1 (mecánico o administradora): registra el trabajo de una orden
     * terminada — repuestos usados, cantidades y observaciones técnicas — sin
     * emitir factura. Descuenta el stock. Se puede repetir para editar
     * mientras la orden siga en `terminada`.
     *
     * @param array<int, array{repuesto_id: int, cantidad: int}> $items
     */
    public function registrarTrabajo(OrdenServicio $orden, array $items, string $observacionesTecnicas, User $user): OrdenServicio
    {
        return DB::transaction(function () use ($orden, $items, $observacionesTecnicas, $user) {
            $orden = $this->bloquearTerminada($orden);
            $repuestos = $this->reemplazarDetalles($orden, $items);

            $orden->update(['observaciones_tecnicas' => $observacionesTecnicas]);

            // Mismo estado: el evento queda como liquidación "parcial" (sin factura).
            $orden->registrarHistorial(
                'liquidacion',
                'terminada',
                $user,
                'Trabajo registrado: ' . ($repuestos ? "{$repuestos} " . ($repuestos === 1 ? 'repuesto' : 'repuestos') : 'sin repuestos') . '. Pendiente de factura.',
            );

            return $orden->fresh(['detalles.repuesto', 'cliente', 'moto', 'mecanico']);
        });
    }

    /**
     * Paso 2 (solo administradora): emite la factura. Toma la lista final de
     * repuestos (la registrada por el mecánico, ajustada si hace falta), fija
     * la mano de obra, recalcula precios al día con el descuento del cliente,
     * congela los totales con el IVA del taller, asigna el consecutivo y pasa
     * la orden a listo_para_reclamar.
     *
     * Solo procede desde `terminada`. Los repuestos se reemplazan reponiendo
     * antes el stock de los anteriores: ningún camino descuenta dos veces.
     *
     * @param array<int, array{repuesto_id: int, cantidad: int}> $items
     */
    public function liquidar(
        OrdenServicio $orden,
        array $items,
        float $manoObra,
        string $observacionesTecnicas,
        User $user,
    ): OrdenServicio {
        return DB::transaction(function () use ($orden, $items, $manoObra, $observacionesTecnicas, $user) {
            // Bloquea la orden: dos emisiones simultáneas se serializan y la
            // segunda ve el estado ya cambiado.
            $orden = $this->bloquearTerminada($orden);
            $this->reemplazarDetalles($orden, $items);

            $detalles = $orden->detalles()->get();
            $subtotalRepuestos = (float) $detalles->sum('subtotal');
            $descuentoTotal = (float) $detalles->sum(fn ($d) => ($d->precio_unitario_original - $d->precio_unitario) * $d->cantidad);

            $config = $this->configuracionBloqueada($orden->taller_id);
            $impuestos = $this->calcularImpuestos($subtotalRepuestos + $manoObra, $config);

            // Una corrección conserva el número: la factura se reemite.
            if ($orden->numero_factura === null) {
                $config->ultimo_numero_factura++;
                $config->save();
                $orden->numero_factura = $config->ultimo_numero_factura;
            }

            $orden->fill([
                'mano_obra' => $manoObra,
                'observaciones_tecnicas' => $observacionesTecnicas,
                'liquidada_at' => now(),
                'subtotal_repuestos' => $subtotalRepuestos,
                'descuento_total' => $descuentoTotal,
                'base_gravable' => $impuestos['base_gravable'],
                'iva' => $impuestos['iva'],
                'tasa_iva_aplicada' => $config->tasa_iva,
                'precios_incluian_iva' => $config->precios_incluyen_iva,
                'total' => $impuestos['total'],
                'estado' => 'listo_para_reclamar',
            ])->save();

            $orden->registrarHistorial('liquidacion', 'terminada', $user);

            $orden = $orden->fresh(['detalles.repuesto', 'cliente', 'moto']);

            // Solo si la transacción (y la exterior, si la hay) se confirma.
            DB::afterCommit(fn () => $this->conRespaldo(fn () => $this->facturaJson->guardar($orden)));

            return $orden;
        });
    }

    private function bloquearTerminada(OrdenServicio $orden): OrdenServicio
    {
        $orden = OrdenServicio::whereKey($orden->id)->lockForUpdate()->firstOrFail();

        if ($orden->estado !== 'terminada') {
            throw new RuntimeException('Solo se puede liquidar una orden en estado "Terminada".');
        }

        return $orden;
    }

    /**
     * Reemplaza los repuestos de la orden: PRIMERO repone el stock de los
     * detalles actuales y los borra, LUEGO descuenta y crea los nuevos.
     * Regla 5: el precio se recalcula siempre desde repuestos.precio_venta
     * con el descuento del tipo de cliente (nunca del frontend).
     *
     * Debe llamarse dentro de una transacción con la orden bloqueada.
     *
     * @return int cantidad de repuestos distintos
     */
    private function reemplazarDetalles(OrdenServicio $orden, array $items): int
    {
        foreach ($orden->detalles()->get() as $detalle) {
            $this->inventarioService->reponerStock($detalle->repuesto_id, $detalle->cantidad);
        }
        $orden->detalles()->delete();

        $tipoCliente = $orden->cliente()
            ->withoutGlobalScope(ClienteActivoScope::class)
            ->firstOrFail()
            ->tipoClienteEfectivo();

        $cantidades = $this->agruparPorRepuesto($items);

        foreach ($cantidades as $repuestoId => $cantidad) {
            $repuesto = $this->inventarioService->descontarStock($repuestoId, $cantidad);

            $precioOriginal = (float) $repuesto->precio_venta;
            $precioFinal = $tipoCliente->calcularPrecioConDescuento($precioOriginal);

            DetalleOrden::create([
                'taller_id' => $orden->taller_id,
                'orden_servicio_id' => $orden->id,
                'repuesto_id' => $repuesto->id,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioFinal,
                'precio_unitario_original' => $precioOriginal,
                'descuento_aplicado_pct' => $tipoCliente->descuento_repuestos_pct,
                'subtotal' => $precioFinal * $cantidad,
            ]);
        }

        return count($cantidades);
    }

    /**
     * Revierte una liquidación para volver a liquidar: repone el stock de
     * cada detalle ANTES de borrarlo, limpia los totales congelados y deja
     * la orden en `terminada`. Conserva numero_factura, mano de obra y
     * observaciones (para precargar la nueva liquidación).
     *
     * La autorización (rol + PIN) ocurre antes, en el controlador.
     */
    public function corregir(OrdenServicio $orden, string $motivo, User $user): OrdenServicio
    {
        return DB::transaction(function () use ($orden, $motivo, $user) {
            $orden = OrdenServicio::whereKey($orden->id)->lockForUpdate()->firstOrFail();

            if ($orden->estado === 'pagada') {
                throw new RuntimeException('Orden ya pagada. Para corregir, cree una nueva orden.');
            }

            if ($orden->estado !== 'listo_para_reclamar') {
                throw new RuntimeException('Solo se puede corregir la factura de una orden "Listo para reclamar".');
            }

            foreach ($orden->detalles()->get() as $detalle) {
                $this->inventarioService->reponerStock($detalle->repuesto_id, $detalle->cantidad);
            }

            $orden->detalles()->delete();

            $orden->fill([
                'liquidada_at' => null,
                'subtotal_repuestos' => 0,
                'descuento_total' => 0,
                'base_gravable' => 0,
                'iva' => 0,
                'tasa_iva_aplicada' => 0,
                'total' => 0,
                'estado' => 'terminada',
            ])->save();

            $orden->registrarHistorial('correccion', 'listo_para_reclamar', $user, $motivo);

            DB::afterCommit(fn () => $this->conRespaldo(fn () => $this->facturaJson->eliminar($orden)));

            return $orden->fresh();
        });
    }

    /**
     * IVA según la configuración del taller, calculado sobre el total ya
     * descontado. Pesos enteros, como el resto de precios.
     *  - Precios con IVA incluido: se desglosa, base = bruto / (1 + tasa). El total no cambia.
     *  - Sin IVA incluido: se suma encima.
     *
     * @return array{base_gravable: float, iva: float, total: float}
     */
    public function calcularImpuestos(float $bruto, ConfiguracionTaller $config): array
    {
        $tasa = (float) $config->tasa_iva / 100;

        if ($config->precios_incluyen_iva) {
            $base = round($bruto / (1 + $tasa));

            return ['base_gravable' => $base, 'iva' => $bruto - $base, 'total' => $bruto];
        }

        $iva = round($bruto * $tasa);

        return ['base_gravable' => $bruto, 'iva' => $iva, 'total' => $bruto + $iva];
    }

    /**
     * Totales congelados de una orden liquidada (para PDF y vistas). Nunca
     * se recalculan: cambiar el IVA del taller no altera facturas emitidas.
     *
     * @return array{subtotal_repuestos: float, descuento_total: float, mano_obra: float, subtotal: float, iva: float, total: float, tasa_iva: float, precios_incluian_iva: bool}
     */
    public function totalesCongelados(OrdenServicio $orden): array
    {
        return [
            'subtotal_repuestos' => (float) $orden->subtotal_repuestos,
            'descuento_total' => (float) $orden->descuento_total,
            'mano_obra' => (float) $orden->mano_obra,
            'subtotal' => (float) $orden->base_gravable,
            'iva' => (float) $orden->iva,
            'total' => (float) $orden->total,
            'tasa_iva' => (float) $orden->tasa_iva_aplicada,
            'precios_incluian_iva' => $orden->precios_incluian_iva,
        ];
    }

    /**
     * El JSON es un derivado de la factura: si el disco falla, la liquidación
     * ya confirmada se mantiene y el error queda reportado en el log.
     */
    private function conRespaldo(callable $accion): void
    {
        try {
            $accion();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * El mismo repuesto en dos líneas se une: una sola fila por repuesto y
     * un solo descuento de stock por la cantidad total.
     *
     * @return array<int, int> repuesto_id => cantidad
     */
    private function agruparPorRepuesto(array $items): array
    {
        $cantidades = [];

        foreach ($items as $item) {
            $id = (int) $item['repuesto_id'];
            $cantidades[$id] = ($cantidades[$id] ?? 0) + (int) $item['cantidad'];
        }

        ksort($cantidades); // orden fijo de bloqueo de filas: evita interbloqueos

        return $cantidades;
    }

    /**
     * Configuración del taller de la orden con la fila bloqueada: el
     * consecutivo de factura no se repite aunque dos liquidaciones corran
     * a la vez.
     */
    private function configuracionBloqueada(int $tallerId): ConfiguracionTaller
    {
        ConfiguracionTaller::withoutGlobalScopes()->firstOrCreate(['taller_id' => $tallerId]);

        return ConfiguracionTaller::withoutGlobalScopes()
            ->where('taller_id', $tallerId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
