<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReinscribirClienteRequest;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use App\Models\TipoCliente;
use App\Scopes\ClienteActivoScope;
use App\Services\AutorizacionService;
use App\Services\ClienteService;
use App\Support\TarjetaOrden;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClienteController extends Controller
{
    private const RESULTADOS_BUSCADOR = 15;

    public function __construct(
        private ClienteService $clienteService,
        private AutorizacionService $autorizacion,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Cliente::class);

        // El mecánico no ve ni busca por cédula/teléfono, ni filtra por tipo (descuento).
        $verPrivado = TarjetaOrden::veDinero($request->user());

        $query = Cliente::with(['motos', 'tipoCliente'])
            ->buscar($request->query('buscar'), $verPrivado)
            ->when($verPrivado && $request->filled('tipo_cliente_id'), fn ($q) => $q->where('tipo_cliente_id', $request->integer('tipo_cliente_id')))
            ->orderBy('nombre');

        // Por defecto el ClienteActivoScope oculta archivados; los filtros los muestran explícitamente.
        if (in_array($request->query('estado'), ['activo', 'hibernando', 'archivado'], true)) {
            $query->withoutGlobalScope(ClienteActivoScope::class)
                ->where('estado_cliente', $request->query('estado'));
        }

        $clientes = $query->paginate(15)->withQueryString();
        $tiposCliente = TipoCliente::orderBy('nombre')->get();

        return view('clientes.index', compact('clientes', 'tiposCliente'));
    }

    /**
     * Selector de clientes (ej. "Crear orden manual"): nombre, teléfono,
     * cédula/NIT o placa. Disponible también para el mecánico (solo lectura).
     */
    public function buscar(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Cliente::class);

        $termino = trim((string) $request->query('q', ''));

        if (mb_strlen($termino) < 2) {
            return response()->json(['data' => []]);
        }

        $verPrivado = TarjetaOrden::veDinero($request->user());

        $clientes = Cliente::with(['motos', 'tipoCliente'])
            ->buscar($termino, $verPrivado)
            ->orderBy('nombre')
            ->limit(self::RESULTADOS_BUSCADOR)
            ->get();

        return response()->json([
            'data' => $clientes->map(function (Cliente $cliente) use ($verPrivado) {
                $motos = $cliente->motos->map(fn ($moto) => [
                    'id' => $moto->id,
                    'placa' => $moto->placa,
                    'marca' => $moto->marca,
                    'modelo' => $moto->modelo,
                    'anio' => $moto->anio,
                ])->values();

                // Mecánico: solo primer nombre y motos, sin contacto ni tipo (descuento).
                if (! $verPrivado) {
                    return [
                        'id' => $cliente->id,
                        'nombre' => TarjetaOrden::primerNombre($cliente->nombre),
                        'estado_cliente' => $cliente->estado_cliente,
                        'motos' => $motos,
                    ];
                }

                $tipo = $cliente->tipoClienteEfectivo();

                return [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'cedula_nit' => $cliente->cedula_nit,
                    'telefono' => $cliente->telefono,
                    'estado_cliente' => $cliente->estado_cliente,
                    'tipo_cliente' => [
                        'nombre' => $tipo->nombre,
                        'descuento_repuestos_pct' => (float) $tipo->descuento_repuestos_pct,
                    ],
                    'motos' => $motos,
                ];
            })->values(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Cliente::class);

        return view('clientes.create', [
            'tiposCliente' => TipoCliente::activos()->orderBy('nombre')->get(),
            'tipoPorDefecto' => TipoCliente::regularDelTaller(auth()->user()->taller_id),
        ]);
    }

    public function store(StoreClienteRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        ['cliente' => $cliente, 'moto' => $moto] = $this->clienteService->registrarConMoto($datos, $datos['moto']);

        return redirect()
            ->route('clientes.index')
            ->with('status', "Cliente {$cliente->nombre} registrado con la moto {$moto->placa}.");
    }

    public function show(Cliente $cliente): View
    {
        $this->authorize('view', $cliente);

        $cliente->load(['motos', 'tipoCliente', 'ordenesServicio' => fn ($q) => $q->latest()->limit(10)]);

        return view('clientes.show', compact('cliente'));
    }

    public function edit(Cliente $cliente): View
    {
        $this->authorize('update', $cliente);

        $cliente->load(['motos', 'tipoCliente']);

        return view('clientes.edit', [
            'cliente' => $cliente,
            // Activos, más el que ya tiene asignado aunque se haya desactivado.
            'tiposCliente' => TipoCliente::query()
                ->where(fn ($q) => $q->where('activo', true)->orWhere('id', $cliente->tipo_cliente_id))
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    /**
     * Requiere PIN (middleware `pin` + AutorizacionService). PIN y acción van en
     * la misma transacción: si la actualización falla, no queda auditoría.
     */
    public function update(UpdateClienteRequest $request, Cliente $cliente): JsonResponse|RedirectResponse
    {
        $estadoAnterior = $cliente->estado_cliente;

        $cliente = DB::transaction(function () use ($request, $cliente) {
            $this->autorizacion->verificarPin(
                $request->user(),
                $request->input('pin_autorizacion'),
                'cliente.actualizar',
                Cliente::class,
                $cliente->id,
                $cliente->taller_id,
            );

            return $this->clienteService->actualizar($cliente, $request->validated());
        });

        $mensaje = "Cliente {$cliente->nombre} actualizado.";

        if ($cliente->estado_cliente !== $estadoAnterior) {
            $mensaje .= " Estado: {$estadoAnterior} → {$cliente->estado_cliente}.";

            if ($cliente->estado_cliente === 'archivado') {
                $mensaje .= ' Ya no aparece en el listado por defecto (filtra por "Archivados" para verlo).';
            }
        }

        return $this->volverAlListado($request, $mensaje);
    }

    /**
     * Reinscribe a un cliente archivado (regla de negocio 2.4): vuelve a
     * activo con sus mismos datos. Requiere PIN.
     */
    public function reinscribir(ReinscribirClienteRequest $request, Cliente $cliente): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($request, $cliente) {
            $this->autorizacion->verificarPin(
                $request->user(),
                $request->input('pin_autorizacion'),
                'cliente.reinscribir',
                Cliente::class,
                $cliente->id,
                $cliente->taller_id,
            );

            $this->clienteService->reinscribir($cliente, []);
        });

        return $this->volverAlListado($request, "Cliente {$cliente->nombre} reinscrito como activo.");
    }

    /**
     * El modal del PIN envía con fetch: si se le devolviera un redirect, fetch lo
     * seguiría y consumiría el mensaje flash. Se responde la URL y el navegador va.
     */
    private function volverAlListado(Request $request, string $mensaje): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            session()->flash('status', $mensaje);

            return response()->json(['message' => $mensaje, 'redirect' => route('clientes.index')]);
        }

        return redirect()->route('clientes.index')->with('status', $mensaje);
    }
}
