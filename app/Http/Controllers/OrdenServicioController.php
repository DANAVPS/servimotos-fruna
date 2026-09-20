<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarcarOrdenPagadaRequest;
use App\Models\OrdenServicio;
use App\Services\OrdenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class OrdenServicioController extends Controller
{
    private const ESTADOS_KANBAN = [
        'en_espera_revision',
        'en_revision',
        'reparacion',
        'espera_repuesto',
        'terminada',
        'listo_para_reclamar',
        'pagada',
    ];

    public function __construct(private OrdenService $ordenService)
    {
    }

    public function kanban(): View
    {
        $ordenes = OrdenServicio::with(['cliente', 'moto', 'mecanico'])
            ->get()
            ->groupBy('estado');

        return view('ordenes.kanban', [
            'columnas' => self::ESTADOS_KANBAN,
            'ordenes' => $ordenes,
        ]);
    }

    public function show(OrdenServicio $orden): View
    {
        $this->authorize('verPanel', $orden);

        $orden->load(['cliente', 'moto', 'mecanico', 'detalles.repuesto']);

        return view('ordenes.show', compact('orden'));
    }

    /**
     * Endpoint del drag & drop. Nunca acepta 'pagada' como destino:
     * esa transición SOLO ocurre por el endpoint marcarPagada().
     */
    public function actualizarEstado(Request $request, OrdenServicio $orden): JsonResponse
    {
        $this->authorize('cambiarEstado', $orden);

        $validado = $request->validate([
            'estado' => ['required', 'in:' . implode(',', array_diff(self::ESTADOS_KANBAN, ['pagada']))],
        ]);

        try {
            $orden = $this->ordenService->cambiarEstado($orden, $validado['estado']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Estado actualizado.',
            'estado' => $orden->estado,
        ]);
    }

    /**
     * Ruta exclusiva y separada para marcar como pagada. Doble candado:
     * la Policy vive dentro de MarcarOrdenPagadaRequest::authorize().
     */
    public function marcarPagada(MarcarOrdenPagadaRequest $request, OrdenServicio $orden): JsonResponse
    {
        try {
            $orden = $this->ordenService->marcarPagada($orden);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Orden marcada como pagada.',
            'estado' => $orden->estado,
        ]);
    }
}
