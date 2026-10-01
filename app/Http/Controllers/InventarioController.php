<?php

namespace App\Http\Controllers;

use App\Http\Requests\EliminarRepuestoRequest;
use App\Http\Requests\StoreRepuestoRequest;
use App\Models\CategoriaRepuesto;
use App\Models\Distribuidor;
use App\Models\Repuesto;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventarioController extends Controller
{
    private const RESULTADOS_BUSCADOR = 20;

    public function __construct(private InventarioService $inventarioService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Repuesto::class);

        $buscar = trim((string) $request->query('buscar', ''));
        $soloStockBajo = $request->boolean('solo_stock_bajo');

        $grupos = $this->inventarioService->listadoAgrupadoPorCategoria($buscar ?: null, $soloStockBajo);
        $alertas = $this->inventarioService->alertasAgrupadasPorDistribuidor();

        return view('inventario.index', [
            'grupos' => $grupos,
            'alertas' => $alertas,
            'buscar' => $buscar,
            'soloStockBajo' => $soloStockBajo,
            'totalRepuestos' => $grupos->sum(fn ($grupo) => $grupo['repuestos']->count()),
            'totalAlertas' => $alertas->sum(fn ($grupo) => $grupo['repuestos']->count()),
            'filtrando' => $buscar !== '' || $soloStockBajo,
        ]);
    }

    /**
     * Buscador para la liquidación (y cualquier selector de repuestos).
     * Nunca devuelve el costo a quien no puede verlo (mecánico).
     */
    public function buscar(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Repuesto::class);

        $consulta = trim((string) $request->query('q', ''));

        if (mb_strlen($consulta) < 2) {
            return response()->json(['data' => []]);
        }

        $verCostos = $request->user()->can('verCostos', Repuesto::class);

        $repuestos = Repuesto::buscar($consulta)
            ->with('categoria')
            ->orderByRaw('stock_actual > 0 desc')
            ->orderBy('nombre')
            ->limit(self::RESULTADOS_BUSCADOR)
            ->get();

        return response()->json([
            'data' => $repuestos->map(function (Repuesto $repuesto) use ($verCostos) {
                $datos = [
                    'id' => $repuesto->id,
                    'nombre' => $repuesto->nombre,
                    'codigo_sku' => $repuesto->codigo_sku,
                    'categoria' => $repuesto->categoria?->nombre,
                    'compatibilidad' => $repuesto->modelo_compatible,
                    'ubicacion_estante' => $repuesto->ubicacion_estante,
                    'stock_actual' => $repuesto->stock_actual,
                    'disponible' => $repuesto->stock_actual > 0,
                    'precio_venta' => (float) $repuesto->precio_venta,
                ];

                if ($verCostos) {
                    $datos['precio_costo'] = (float) $repuesto->precio_costo;
                }

                return $datos;
            })->values(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Repuesto::class);

        return view('inventario.create', $this->datosFormulario(new Repuesto()));
    }

    public function store(StoreRepuestoRequest $request): RedirectResponse
    {
        $this->inventarioService->crear($request->validated());

        return redirect()
            ->route('inventario.index')
            ->with('status', 'Repuesto agregado correctamente.');
    }

    public function edit(Repuesto $repuesto): View
    {
        $this->authorize('update', $repuesto);

        $repuesto->load(['compatibilidades', 'distribuidores']);

        return view('inventario.edit', $this->datosFormulario($repuesto));
    }

    public function update(StoreRepuestoRequest $request, Repuesto $repuesto): RedirectResponse
    {
        $this->inventarioService->actualizar($repuesto, $request->validated());

        return redirect()
            ->route('inventario.index')
            ->with('status', 'Repuesto actualizado correctamente.');
    }

    public function destroy(EliminarRepuestoRequest $request, Repuesto $repuesto): RedirectResponse
    {
        $this->inventarioService->eliminar($repuesto);

        return redirect()
            ->route('inventario.index')
            ->with('status', "Repuesto \"{$repuesto->nombre}\" eliminado del inventario.");
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Repuesto $repuesto): array
    {
        return [
            'repuesto' => $repuesto,
            // Las inactivas no se ofrecen, salvo la que el repuesto ya tiene.
            'categorias' => CategoriaRepuesto::query()
                ->where(fn ($q) => $q->where('activo', true)->orWhere('id', $repuesto->categoria_repuesto_id))
                ->ordenadas()
                ->get(),
            'distribuidores' => Distribuidor::activos()->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }
}
