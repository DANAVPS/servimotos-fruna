<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClienteRequest;
use App\Models\Cliente;
use App\Services\ClienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function __construct(private ClienteService $clienteService)
    {
    }

    public function index(): View
    {
        $query = Cliente::with('motos')->orderBy('nombre');

        if (request()->filled('buscar')) {
            $termino = request('buscar');
            $query->where(function ($q) use ($termino) {
                $q->where('nombre', 'ilike', "%{$termino}%")
                    ->orWhere('telefono', 'ilike', "%{$termino}%");
            });
        }

        if (request('estado') === 'hibernando') {
            $query->withoutGlobalScope(\App\Scopes\ClienteActivoScope::class)
                ->where('estado_cliente', 'hibernando');
        } elseif (request('estado') === 'archivado') {
            $query->withoutGlobalScope(\App\Scopes\ClienteActivoScope::class)
                ->where('estado_cliente', 'archivado');
        }

        $clientes = $query->paginate(15)->withQueryString();

        return view('clientes.index', compact('clientes'));
    }

    public function create(): View
    {
        return view('clientes.create');
    }

    public function store(StoreClienteRequest $request): RedirectResponse
    {
        $this->clienteService->registrar($request->validated());

        return redirect()
            ->route('clientes.index')
            ->with('status', 'Cliente registrado correctamente.');
    }

    public function show(Cliente $cliente): View
    {
        $cliente->load(['motos', 'ordenesServicio' => fn ($q) => $q->latest()->limit(10)]);

        return view('clientes.show', compact('cliente'));
    }

    public function edit(Cliente $cliente): View
    {
        return view('clientes.edit', compact('cliente'));
    }

    public function update(StoreClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($request->validated());

        return redirect()
            ->route('clientes.index')
            ->with('status', 'Cliente actualizado correctamente.');
    }

    /**
     * Reinscribe a un cliente archivado, conforme a la regla de negocio 2.4.
     */
    public function reinscribir(StoreClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $this->clienteService->reinscribir($cliente, $request->validated());

        return redirect()
            ->route('clientes.index')
            ->with('status', 'Cliente reinscrito como activo.');
    }
}
