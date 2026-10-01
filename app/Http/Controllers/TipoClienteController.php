<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTipoClienteRequest;
use App\Models\TipoCliente;
use App\Services\TipoClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sin borrado: un tipo con clientes se desactiva (activo = false) para no
 * romper el histórico. Las facturas ya emitidas guardan su propio % aplicado.
 */
class TipoClienteController extends Controller
{
    public function __construct(private TipoClienteService $tipos)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', TipoCliente::class);

        $tipos = TipoCliente::withCount('clientes')
            ->orderByDesc('activo')
            ->orderBy('descuento_repuestos_pct')
            ->orderBy('nombre')
            ->get();

        return view('tipos-cliente.index', compact('tipos'));
    }

    public function create(): View
    {
        $this->authorize('create', TipoCliente::class);

        return view('tipos-cliente.create', [
            'tipoCliente' => new TipoCliente(['descuento_repuestos_pct' => 0, 'activo' => true]),
        ]);
    }

    public function store(StoreTipoClienteRequest $request): RedirectResponse
    {
        $tipo = $this->tipos->crear($request->validated());

        return redirect()
            ->route('tipos-cliente.index')
            ->with('status', "Tipo \"{$tipo->nombre}\" creado.")
            ->with('advertencia', $this->advertenciaBajoCosto($tipo));
    }

    public function edit(TipoCliente $tipoCliente): View
    {
        $this->authorize('update', $tipoCliente);

        $tipoCliente->loadCount('clientes');

        return view('tipos-cliente.edit', [
            'tipoCliente' => $tipoCliente,
            'analisis' => $this->tipos->analizarDescuento((float) $tipoCliente->descuento_repuestos_pct),
        ]);
    }

    public function update(StoreTipoClienteRequest $request, TipoCliente $tipoCliente): RedirectResponse
    {
        $tipo = $this->tipos->actualizar($tipoCliente, $request->validated());

        return redirect()
            ->route('tipos-cliente.index')
            ->with('status', "Tipo \"{$tipo->nombre}\" actualizado.")
            ->with('advertencia', $this->advertenciaBajoCosto($tipo));
    }

    /**
     * Para la advertencia en vivo del formulario mientras se escribe el %.
     */
    public function analizarDescuento(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TipoCliente::class);

        $validados = $request->validate([
            'porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        return response()->json($this->tipos->analizarDescuento((float) $validados['porcentaje']));
    }

    private function advertenciaBajoCosto(TipoCliente $tipo): ?string
    {
        if (! $tipo->tieneDescuento()) {
            return null;
        }

        $analisis = $this->tipos->analizarDescuento((float) $tipo->descuento_repuestos_pct);

        if ($analisis['bajo_costo'] === 0) {
            return null;
        }

        return "Con {$tipo->descuento_repuestos_pct}% de descuento, {$analisis['bajo_costo']} "
            . ($analisis['bajo_costo'] === 1 ? 'repuesto queda' : 'repuestos quedan')
            . ' por debajo del costo para los clientes de este tipo.';
    }
}
