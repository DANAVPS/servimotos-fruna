<?php

namespace App\Http\Controllers;

use App\Exceptions\AutorizacionDenegadaException;
use App\Http\Requests\CorregirFacturaRequest;
use App\Http\Requests\LiquidarOrdenRequest;
use App\Http\Requests\RegistrarTrabajoRequest;
use App\Models\OrdenServicio;
use App\Scopes\ClienteActivoScope;
use App\Services\AutorizacionService;
use App\Services\FacturaService;
use App\Support\LogoTaller;
use App\Support\TarjetaOrden;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class FacturaController extends Controller
{
    public function __construct(
        private FacturaService $facturaService,
        private AutorizacionService $autorizacion,
    ) {
    }

    /**
     * La liquidación es un modal en el detalle de la orden (y en el Kanban).
     * Esta URL se conserva y abre ese modal.
     */
    public function liquidar(OrdenServicio $orden): RedirectResponse
    {
        $this->authorize('liquidar', $orden);

        return redirect()->route('ordenes.show', [$orden, 'liquidar' => 1]);
    }

    /**
     * Paso 1: el mecánico (o la administradora) registra repuestos, cantidades
     * y observaciones. La orden sigue en "Terminada" hasta que se emita la factura.
     */
    public function registrarTrabajo(RegistrarTrabajoRequest $request, OrdenServicio $orden): JsonResponse
    {
        $validado = $request->validated();

        try {
            $orden = $this->facturaService->registrarTrabajo(
                $orden,
                $validado['items'] ?? [],
                $validado['observaciones_tecnicas'],
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Trabajo registrado en la moto {$orden->moto->placa}. La administradora emitirá la factura.",
            'redirect' => route('ordenes.show', $orden),
            'orden' => TarjetaOrden::datos($orden, TarjetaOrden::configuracionDe($orden->taller_id), $request->user()),
        ]);
    }

    /** Paso 2 (solo administradora): fija la mano de obra y emite la factura. */
    public function generar(LiquidarOrdenRequest $request, OrdenServicio $orden): JsonResponse|RedirectResponse
    {
        $validado = $request->validated();

        try {
            $orden = $this->facturaService->liquidar(
                $orden,
                $validado['items'] ?? [],
                (float) $validado['mano_obra'],
                $validado['observaciones_tecnicas'],
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withInput()->withErrors(['stock' => $e->getMessage()]);
        }

        $mensaje = "Factura N.º {$orden->numero_factura} generada. La orden pasó a \"Listo para reclamar\".";

        return $request->expectsJson()
            ? response()->json([
                'message' => $mensaje,
                'redirect' => route('ordenes.show', $orden),
                'orden' => TarjetaOrden::datos($orden, TarjetaOrden::configuracionDe($orden->taller_id), $request->user()),
            ])
            : redirect()->route('ordenes.show', $orden)->with('status', $mensaje);
    }

    /**
     * Corrección de factura: PIN y acción en una sola transacción (si la
     * corrección falla, no queda auditoría). El modal de PIN envía con fetch
     * y recibe {redirect}.
     */
    public function corregir(CorregirFacturaRequest $request, OrdenServicio $orden): JsonResponse|RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $orden) {
                $this->autorizacion->verificarPin(
                    $request->user(),
                    $request->input('pin_autorizacion'),
                    'factura.corregir',
                    OrdenServicio::class,
                    $orden->id,
                    $orden->taller_id,
                );

                $this->facturaService->corregir($orden, $request->validated()['motivo'], $request->user());
            });
        } catch (AutorizacionDenegadaException $e) {
            throw $e; // se renderiza sola: 403 con intentos restantes
        } catch (RuntimeException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['motivo' => $e->getMessage()]);
        }

        $mensaje = 'Factura reversada: el stock fue repuesto y la orden volvió a "Terminada". Liquídala de nuevo.';
        session()->flash('status', $mensaje);

        // Vuelve al detalle con el modal de liquidación abierto (mismo número de factura).
        $destino = route('ordenes.show', [$orden, 'liquidar' => 1]);

        return $request->expectsJson()
            ? response()->json(['message' => $mensaje, 'redirect' => $destino])
            : redirect()->to($destino);
    }

    public function descargar(OrdenServicio $orden): Response
    {
        // La factura tiene precios y datos de contacto: el mecánico no la ve.
        $this->authorize('verFactura', $orden);

        abort_unless($orden->estaFacturada(), 404);

        $orden->load([
            'detalles.repuesto',
            'cliente' => fn ($q) => $q->withoutGlobalScope(ClienteActivoScope::class)->with('tipoCliente'),
            'moto',
            'taller',
        ]);
        $configuracion = TarjetaOrden::configuracionDe($orden->taller_id);

        // Regla 3: a la vista del cliente solo llegan datos de la factura;
        // nunca el método de pago. Totales congelados, no recalculados.
        $pdf = Pdf::loadView('facturas.pdf', [
            'orden' => $orden,
            'totales' => $this->facturaService->totalesCongelados($orden),
            'configuracion' => $configuracion,
            'logo' => LogoTaller::base64($configuracion),
        ]);

        return $pdf->stream("factura-{$orden->numero_factura}.pdf");
    }
}
