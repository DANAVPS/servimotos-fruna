<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\ConfiguracionTaller;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Scopes\ClienteActivoScope;

/**
 * Datos de una orden para el Kanban y los modales (liquidar, pagar). Es la
 * única fuente: la usan el render inicial y las respuestas JSON, así la
 * tarjeta se actualiza sin recargar con exactamente los mismos campos.
 *
 * Regla 3: nunca incluye metodo_pago.
 * El mecánico no ve dinero ni datos de contacto: para él, total y mano de
 * obra van en null, el cliente solo con su primer nombre y sin PDF.
 */
class TarjetaOrden
{
    public const ETIQUETAS_ESTADO = [
        'en_espera_revision' => 'En espera de revisión',
        'en_revision' => 'En revisión',
        'reparacion' => 'Reparación',
        'espera_repuesto' => 'Espera de repuesto',
        'terminada' => 'Terminada',
        'listo_para_reclamar' => 'Listo para reclamar',
        'pagada' => 'Pagada',
    ];

    public static function datos(OrdenServicio $orden, ConfiguracionTaller $config, User $usuario): array
    {
        $verDinero = self::veDinero($usuario);

        $orden->loadMissing(['moto', 'mecanico']);

        if (! isset($orden->correcciones_count)) {
            $orden->loadCount('correcciones');
        }

        $cliente = $orden->relationLoaded('cliente') && $orden->cliente
            ? $orden->cliente
            : Cliente::withoutGlobalScope(ClienteActivoScope::class)->find($orden->cliente_id);

        $facturada = $orden->estaFacturada();

        return [
            'id' => $orden->id,
            'estado' => $orden->estado,
            'placa' => $orden->moto->placa,
            'moto' => trim("{$orden->moto->marca} {$orden->moto->modelo}"),
            'cliente' => $verDinero ? $cliente?->nombre : self::primerNombre($cliente?->nombre),
            'descuento_pct' => (float) ($cliente?->tipoClienteEfectivo()->descuento_repuestos_pct ?? 0),
            'mecanico' => $orden->mecanico ? ['id' => $orden->mecanico->id, 'nombre' => $orden->mecanico->nombre] : null,
            'dias' => $orden->diasEnTaller(),
            // Una orden pagada ya salió del taller: no tiene semáforo.
            'semaforo' => $orden->estado === 'pagada' ? null : $orden->semaforo($config),
            'numero_factura' => $orden->numero_factura,
            'correcciones' => (int) $orden->correcciones_count,
            'total' => $verDinero ? (float) $orden->total : null,
            // Para precargar una re-liquidación tras corregir la factura.
            'mano_obra' => $verDinero ? (float) $orden->mano_obra : null,
            'observaciones_tecnicas' => $orden->observaciones_tecnicas,
            // Paso 1 de la liquidación hecho (mecánico): falta que la administradora emita la factura.
            'trabajo_registrado' => self::trabajoRegistrado($orden),
            'repuestos' => $orden->estado === 'terminada' ? self::repuestos($orden, $verDinero) : [],
            'url' => route('ordenes.show', $orden),
            'url_pdf' => $facturada && $verDinero ? route('facturas.pdf', $orden) : null,
        ];
    }

    /** Orden terminada con observaciones técnicas guardadas y sin factura emitida. */
    public static function trabajoRegistrado(OrdenServicio $orden): bool
    {
        return $orden->estado === 'terminada' && filled($orden->observaciones_tecnicas);
    }

    /**
     * Repuestos registrados, para precargar el modal. `stock` es lo que se
     * puede usar al guardar de nuevo: el actual más lo ya descontado por esta orden.
     */
    private static function repuestos(OrdenServicio $orden, bool $verDinero): array
    {
        $orden->loadMissing('detalles.repuesto');

        return $orden->detalles->sortBy('id')->map(fn ($detalle) => array_filter([
            'id' => $detalle->repuesto_id,
            'nombre' => $detalle->repuesto?->nombre,
            'sku' => $detalle->repuesto?->codigo_sku,
            'cantidad' => $detalle->cantidad,
            'stock' => (int) $detalle->repuesto?->stock_actual + $detalle->cantidad,
            // Precio de lista vigente, solo para la vista previa de la administradora.
            'precio_venta' => $verDinero ? (float) $detalle->repuesto?->precio_venta : null,
        ], fn ($valor) => $valor !== null))->values()->all();
    }

    /** Dinero (precios, totales, pagos) y contacto del cliente: solo administrativos. */
    public static function veDinero(User $usuario): bool
    {
        return $usuario->esAdministrativo();
    }

    /** "Juan Pérez Gómez" → "Juan". Para pantallas del mecánico. */
    public static function primerNombre(?string $nombre): ?string
    {
        $primero = strtok(trim((string) $nombre), ' ');

        return $primero === false ? null : $primero;
    }

    /**
     * Configuración del taller dado, o una con los valores por defecto (el
     * SuperAdmin no tiene taller). Nunca crea filas.
     */
    public static function configuracionDe(?int $tallerId): ConfiguracionTaller
    {
        return ($tallerId ? ConfiguracionTaller::withoutGlobalScopes()->firstWhere('taller_id', $tallerId) : null)
            ?? new ConfiguracionTaller();
    }

    /** Lo que necesita la vista previa de la liquidación (el backend recalcula). */
    public static function iva(ConfiguracionTaller $config): array
    {
        return [
            'precios_incluyen_iva' => $config->precios_incluyen_iva,
            'discriminar_iva_en_factura' => $config->discriminar_iva_en_factura,
            'tasa_iva' => (float) $config->tasa_iva,
        ];
    }
}
