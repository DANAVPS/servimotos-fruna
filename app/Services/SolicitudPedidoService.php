<?php

namespace App\Services;

use App\Models\Distribuidor;
use App\Models\Repuesto;

/**
 * Arma la solicitud de pedido a un distribuidor. Las cantidades pueden venir
 * del formulario, pero nombres, códigos y costos se leen siempre de la base.
 */
class SolicitudPedidoService
{
    public const SIN_CODIGO = '⚠ sin código';

    public function __construct(private DistribuidorService $distribuidores)
    {
    }

    /**
     * @param array<int|string, int|string>|null $cantidades repuesto_id => cantidad.
     *   Null = todos los repuestos bajos del distribuidor con la cantidad sugerida.
     *   Cantidades en 0 se omiten; repuestos no vinculados al distribuidor se ignoran.
     * @return array{
     *   distribuidor: Distribuidor,
     *   fecha: \Illuminate\Support\Carbon,
     *   lineas: array<int, array<string, mixed>>,
     *   total_unidades: int,
     *   total_estimado: float
     * }
     */
    public function armar(Distribuidor $distribuidor, ?array $cantidades = null): array
    {
        if ($cantidades === null) {
            $repuestos = $this->distribuidores->repuestosBajos($distribuidor);
            $cantidades = $repuestos
                ->mapWithKeys(fn (Repuesto $r) => [$r->id => $r->cantidadSugeridaPedido()])
                ->all();
        } else {
            $cantidades = array_filter(
                array_map('intval', $cantidades),
                fn (int $cantidad) => $cantidad > 0
            );

            $repuestos = $distribuidor->repuestos()
                ->whereKey(array_keys($cantidades))
                ->with('compatibilidades')
                ->orderBy('nombre')
                ->get();
        }

        $lineas = $repuestos->map(function (Repuesto $repuesto) use ($cantidades) {
            $cantidad = (int) $cantidades[$repuesto->id];
            $costoUnitario = (float) ($repuesto->pivot->precio_costo_proveedor ?? $repuesto->precio_costo);

            return [
                'repuesto_id' => $repuesto->id,
                'nombre' => $repuesto->nombre,
                'codigo_sku' => $repuesto->codigo_sku,
                'codigo_proveedor' => self::codigoParaProveedor($repuesto),
                'compatibilidad' => $repuesto->modelo_compatible,
                'stock_actual' => $repuesto->stock_actual,
                'stock_minimo' => $repuesto->stock_minimo,
                'cantidad' => $cantidad,
                'costo_unitario' => $costoUnitario,
                'subtotal' => round($costoUnitario * $cantidad, 2),
            ];
        })->values()->all();

        return [
            'distribuidor' => $distribuidor,
            'fecha' => now(),
            'lineas' => $lineas,
            'total_unidades' => array_sum(array_column($lineas, 'cantidad')),
            'total_estimado' => round(array_sum(array_column($lineas, 'subtotal')), 2),
        ];
    }

    /**
     * Código con el que el distribuidor identifica la pieza:
     *  1. el código de ESTE distribuidor (repuesto_distribuidor.codigo_proveedor),
     *  2. la referencia general del proveedor (repuestos.referencia_proveedor).
     * Nunca el SKU interno: el distribuidor no lo conoce. Sin ninguno => null.
     *
     * Requiere que el repuesto venga cargado desde la relación del distribuidor (pivot).
     */
    public static function codigoParaProveedor(Repuesto $repuesto): ?string
    {
        $codigo = trim((string) ($repuesto->pivot?->codigo_proveedor ?? ''));

        if ($codigo === '') {
            $codigo = trim((string) $repuesto->referencia_proveedor);
        }

        return $codigo === '' ? null : $codigo;
    }

    /**
     * Mensaje listo para pegar en WhatsApp Web. No incluye costos: el
     * distribuidor cotiza, y el costo interno del taller no se comparte.
     */
    public function textoWhatsApp(array $pedido): string
    {
        $distribuidor = $pedido['distribuidor'];
        $saludo = $distribuidor->contacto ?: $distribuidor->nombre;
        $taller = $distribuidor->taller?->nombre ?? 'el taller';

        $renglones = [
            "Hola {$saludo}, le saluda {$taller}.",
            '',
            'Queremos hacer el siguiente pedido (' . $pedido['fecha']->format('d/m/Y') . '):',
            '',
        ];

        foreach ($pedido['lineas'] as $indice => $linea) {
            $renglones[] = ($indice + 1) . ". {$linea['nombre']}";

            $detalle = ['Ref: ' . ($linea['codigo_proveedor'] ?? self::SIN_CODIGO)];

            // Sin código, la moto es la única pista para que el distribuidor identifique la pieza.
            if ($linea['codigo_proveedor'] === null && $linea['compatibilidad']) {
                $detalle[] = "Moto: {$linea['compatibilidad']}";
            }

            $detalle[] = "Cant: {$linea['cantidad']}";
            $renglones[] = '   ' . implode(' · ', $detalle);
        }

        $referencias = count($pedido['lineas']);

        $renglones[] = '';
        $renglones[] = "Total: {$referencias} " . ($referencias === 1 ? 'referencia' : 'referencias')
            . ", {$pedido['total_unidades']} " . ($pedido['total_unidades'] === 1 ? 'unidad' : 'unidades') . '.';
        $renglones[] = '¿Nos confirma disponibilidad y precio? ¡Gracias!';

        return implode("\n", $renglones);
    }
}
