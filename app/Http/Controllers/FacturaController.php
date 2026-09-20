<?php

namespace App\Http\Controllers;

use App\Http\Requests\LiquidarOrdenRequest;
use App\Models\OrdenServicio;
use App\Models\Repuesto;
use App\Services\FacturaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class FacturaController extends Controller
{
    public function __construct(private FacturaService $facturaService)
    {
    }

    public function liquidar(OrdenServicio $orden): View
    {
        $this->authorize('cambiarEstado', $orden);

        $repuestos = Repuesto::orderBy('nombre')->get();

        return view('facturas.liquidar', compact('orden', 'repuestos'));
    }

    public function generar(LiquidarOrdenRequest $request, OrdenServicio $orden): RedirectResponse
    {
        try {
            $this->facturaService->liquidar($orden, $request->validated()['items']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        return redirect()
            ->route('ordenes.show', $orden)
            ->with('status', 'Factura generada. La orden pasó a "Listo para reclamar".');
    }

    public function descargar(OrdenServicio $orden): Response
    {
        $this->authorize('verPanel', $orden);

        $orden->load(['detalles.repuesto', 'cliente', 'moto', 'taller']);
        $totales = $this->facturaService->calcularTotales($orden);

        $pdf = Pdf::loadView('facturas.pdf', compact('orden', 'totales'));

        return $pdf->stream("factura-orden-{$orden->id}.pdf");
    }
}
