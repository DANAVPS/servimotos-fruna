<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRepuestoRequest;
use App\Models\Repuesto;
use App\Services\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventarioController extends Controller
{
    public function __construct(private InventarioService $inventarioService)
    {
    }

    public function index(): View
    {
        $query = Repuesto::query();

        if (request()->filled('buscar')) {
            $termino = request('buscar');
            $query->where(function ($q) use ($termino) {
                $q->where('nombre', 'ilike', "%{$termino}%")
                    ->orWhere('marca', 'ilike', "%{$termino}%");
            });
        }

        if (request()->boolean('solo_stock_bajo')) {
            $query->stockBajo();
        }

        $repuestos = $query->orderBy('nombre')->paginate(15)->withQueryString();
        $alertas = $this->inventarioService->repuestosConAlerta();

        return view('inventario.index', compact('repuestos', 'alertas'));
    }

    public function create(): View
    {
        return view('inventario.create');
    }

    public function store(StoreRepuestoRequest $request): RedirectResponse
    {
        Repuesto::create($request->validated());

        return redirect()
            ->route('inventario.index')
            ->with('status', 'Repuesto agregado correctamente.');
    }

    public function edit(Repuesto $repuesto): View
    {
        return view('inventario.edit', compact('repuesto'));
    }

    public function update(StoreRepuestoRequest $request, Repuesto $repuesto): RedirectResponse
    {
        $repuesto->update($request->validated());

        return redirect()
            ->route('inventario.index')
            ->with('status', 'Repuesto actualizado correctamente.');
    }
}
