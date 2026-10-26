<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarEstadoRequest;
use App\Http\Requests\MarcarPagadaRequest;
use App\Http\Requests\StoreOrdenServicioRequest;
use App\Models\Mecanico;
use App\Models\OrdenServicio;
use App\Scopes\ClienteActivoScope;
use App\Services\OrdenService;
use App\Support\TarjetaOrden;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class OrdenServicioController extends Controller
{
    /** La columna "Pagada" muestra solo las más recientes; el histórico vive en el dashboard. */
    private const PAGADAS_EN_KANBAN = 20;

    public function __construct(private OrdenService $ordenService)
    {
    }

    public function kanban(Request $request): View
    {
        $config = TarjetaOrden::configuracionDe($request->user()->taller_id);
        $relaciones = [
            // Las órdenes de un cliente archivado siguen en el tablero.
            'cliente' => fn ($q) => $q->withoutGlobalScope(ClienteActivoScope::class)->with('tipoCliente'),
            'moto',
            'mecanico',
            'detalles.repuesto', // trabajo registrado de las terminadas
        ];

        $abiertas = OrdenServicio::with($relaciones)
            ->withCount('correcciones')
            ->where('estado', '!=', 'pagada')
            ->orderBy('fecha_hora_ingreso')
            ->get();

        $pagadas = OrdenServicio::with($relaciones)
            ->withCount('correcciones')
            ->pagada()
            ->orderByDesc('pagada_at')
            ->orderByDesc('id')
            ->limit(self::PAGADAS_EN_KANBAN)
            ->get();

        return view('ordenes.kanban', [
            'columnas' => OrdenServicio::ESTADOS,
            'tarjetas' => $abiertas->concat($pagadas)->map(fn ($orden) => TarjetaOrden::datos($orden, $config, $request->user()))->values(),
            'mecanicos' => $this->mecanicosActivos(),
            'iva' => TarjetaOrden::iva($config),
        ]);
    }

    /**
     * "+ Crear orden manual" del Kanban. Responde la tarjeta para insertarla
     * en la columna sin recargar.
     */
    public function store(StoreOrdenServicioRequest $request): JsonResponse
    {
        $orden = $this->ordenService->crearManual($request->validated(), $request->user());

        return response()->json([
            'message' => "Orden creada para la moto {$orden->moto->placa}.",
            'orden' => $this->tarjeta($orden),
        ], 201);
    }

    public function show(OrdenServicio $orden): View
    {
        $this->authorize('verPanel', $orden);

        $orden->load([
            'cliente' => fn ($q) => $q->withoutGlobalScope(ClienteActivoScope::class)->with('tipoCliente'),
            'moto',
            'mecanico',
            'detalles.repuesto',
            'historialEstados.usuario',
            'historialEstados.mecanico',
        ])->loadCount('correcciones');

        $config = TarjetaOrden::configuracionDe($orden->taller_id);

        return view('ordenes.show', [
            'orden' => $orden,
            'tarjeta' => TarjetaOrden::datos($orden, $config, auth()->user()),
            'iva' => TarjetaOrden::iva($config),
        ]);
    }

    /**
     * Endpoint del drag & drop. Nunca acepta 'pagada' ni 'listo_para_reclamar'
     * como destino: se llega a ellos pagando o liquidando.
     */
    public function actualizarEstado(CambiarEstadoRequest $request, OrdenServicio $orden): JsonResponse
    {
        $validado = $request->validated();

        try {
            $orden = $this->ordenService->cambiarEstado(
                $orden,
                $validado['estado'],
                $request->user(),
                $validado['observaciones'] ?? null,
                isset($validado['mecanico_id']) ? (int) $validado['mecanico_id'] : null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Estado actualizado.',
            'orden' => $this->tarjeta($orden),
        ]);
    }

    public function asignarMecanico(Request $request, OrdenServicio $orden): JsonResponse
    {
        $this->authorize('asignarMecanico', $orden);

        $validado = $request->validate([
            'mecanico_id' => ['required', 'integer'],
        ]);

        try {
            $orden = $this->ordenService->asignarMecanico($orden, (int) $validado['mecanico_id']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Mecánico asignado.',
            'orden' => $this->tarjeta($orden),
        ]);
    }

    /**
     * Ruta exclusiva y separada para marcar como pagada. Triple candado:
     * middleware role: en la ruta, Policy en MarcarPagadaRequest::authorize()
     * y chequeo de rol en OrdenService.
     */
    public function marcarPagada(MarcarPagadaRequest $request, OrdenServicio $orden): JsonResponse
    {
        try {
            $orden = $this->ordenService->marcarPagada($orden, $request->validated()['metodo_pago'], $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Orden marcada como pagada.',
            'orden' => $this->tarjeta($orden),
        ]);
    }

    private function tarjeta(OrdenServicio $orden): array
    {
        return TarjetaOrden::datos($orden, TarjetaOrden::configuracionDe($orden->taller_id), auth()->user());
    }

    /** Para el modal de "En revisión": activos, titulares primero. */
    private function mecanicosActivos(): array
    {
        return Mecanico::activos()
            ->orderByRaw("tipo = 'titular' desc")
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo'])
            ->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->nombre, 'tipo' => $m->tipo])
            ->all();
    }
}
