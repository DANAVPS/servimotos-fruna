<?php

namespace App\Http\Controllers;

use App\Http\Requests\SolicitudPedidoRequest;
use App\Http\Requests\StoreDistribuidorRequest;
use App\Models\ConfiguracionTaller;
use App\Models\Distribuidor;
use App\Models\Repuesto;
use App\Services\DistribuidorService;
use App\Services\SolicitudPedidoService;
use App\Support\LogoTaller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DistribuidorController extends Controller
{
    public function __construct(
        private DistribuidorService $distribuidores,
        private SolicitudPedidoService $solicitudes,
    ) {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Distribuidor::class);

        $distribuidores = Distribuidor::withCount([
            'repuestos',
            'repuestos as repuestos_bajos_count' => fn ($q) => $q->stockBajo(),
        ])
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        return view('distribuidores.index', compact('distribuidores'));
    }

    public function create(): View
    {
        $this->authorize('create', Distribuidor::class);

        return view('distribuidores.create', ['distribuidor' => new Distribuidor(['activo' => true])]);
    }

    public function store(StoreDistribuidorRequest $request): RedirectResponse
    {
        $distribuidor = $this->distribuidores->crear($request->validated());

        return redirect()
            ->route('distribuidores.index')
            ->with('status', "Distribuidor \"{$distribuidor->nombre}\" creado.");
    }

    public function edit(Distribuidor $distribuidor): View
    {
        $this->authorize('update', $distribuidor);

        return view('distribuidores.edit', compact('distribuidor'));
    }

    public function update(StoreDistribuidorRequest $request, Distribuidor $distribuidor): RedirectResponse
    {
        $this->distribuidores->actualizar($distribuidor, $request->validated());

        return redirect()
            ->route('distribuidores.index')
            ->with('status', "Distribuidor \"{$distribuidor->nombre}\" actualizado.");
    }

    /**
     * Pantalla del pedido: repuestos bajos de los que este distribuidor es el
     * preferido (cantidad sugerida), y aparte los que también surte pero se
     * piden por defecto a otro (cantidad 0, por si conviene pedirlos aquí).
     */
    public function solicitud(Distribuidor $distribuidor): View
    {
        $this->authorize('solicitarPedido', $distribuidor);

        $preferidos = $this->distribuidores->repuestosBajos($distribuidor);
        $otros = $this->distribuidores->repuestosBajos($distribuidor, soloPreferidos: false)
            ->reject(fn (Repuesto $r) => $preferidos->contains('id', $r->id))
            ->values();

        return view('distribuidores.solicitud', compact('distribuidor', 'preferidos', 'otros'));
    }

    public function solicitudPdf(SolicitudPedidoRequest $request, Distribuidor $distribuidor): Response|RedirectResponse
    {
        $pedido = $this->solicitudes->armar($distribuidor, $request->validated()['cantidades']);

        if ($pedido['lineas'] === []) {
            return back()->withErrors(['cantidades' => 'El pedido no tiene repuestos con cantidad mayor a cero.']);
        }

        $configuracion = ConfiguracionTaller::where('taller_id', $distribuidor->taller_id)->first();

        $pdf = Pdf::loadView('distribuidores.pedido-pdf', [
            'pedido' => $pedido,
            'taller' => $distribuidor->taller,
            'configuracion' => $configuracion,
            'logo' => LogoTaller::base64($configuracion),
        ]);

        return $pdf->stream('pedido-' . str($distribuidor->nombre)->slug() . '-' . now()->format('Ymd') . '.pdf');
    }

    public function solicitudWhatsapp(SolicitudPedidoRequest $request, Distribuidor $distribuidor): JsonResponse
    {
        $pedido = $this->solicitudes->armar($distribuidor, $request->validated()['cantidades']);

        if ($pedido['lineas'] === []) {
            return response()->json([
                'message' => 'El pedido no tiene repuestos con cantidad mayor a cero.',
            ], 422);
        }

        $texto = $this->solicitudes->textoWhatsApp($pedido);

        return response()->json([
            'texto' => $texto,
            'enlace' => $distribuidor->enlaceWhatsApp($texto),
            'referencias' => count($pedido['lineas']),
            'unidades' => $pedido['total_unidades'],
        ]);
    }
}
