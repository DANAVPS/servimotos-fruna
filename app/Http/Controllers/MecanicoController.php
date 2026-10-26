<?php

namespace App\Http\Controllers;

use App\Http\Requests\MecanicoRequest;
use App\Models\Mecanico;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Sin borrado: un mecánico que se va se desactiva (activo = false), así sus
 * órdenes y comisiones históricas no quedan huérfanas.
 */
class MecanicoController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Mecanico::class);

        $mecanicos = Mecanico::with(['principal', 'usuario'])
            ->withCount(['subordinados' => fn ($q) => $q->activos()])
            ->orderByDesc('activo')
            ->orderByRaw("tipo = 'titular' desc")
            ->orderBy('nombre')
            ->get();

        return view('mecanicos.index', compact('mecanicos'));
    }

    public function create(): View
    {
        $this->authorize('create', Mecanico::class);

        return view('mecanicos.create', [
            'mecanico' => new Mecanico(),
            'titulares' => $this->titularesActivos(),
            'usuarios' => $this->usuariosDisponibles(),
        ]);
    }

    public function store(MecanicoRequest $request): RedirectResponse
    {
        $mecanico = Mecanico::create($this->datos($request));

        return redirect()
            ->route('mecanicos.index')
            ->with('status', "Mecánico {$mecanico->nombre} creado.");
    }

    public function edit(Mecanico $mecanico): View
    {
        $this->authorize('update', $mecanico);

        return view('mecanicos.edit', [
            'mecanico' => $mecanico,
            'titulares' => $this->titularesActivos($mecanico),
            'usuarios' => $this->usuariosDisponibles($mecanico),
        ]);
    }

    public function update(MecanicoRequest $request, Mecanico $mecanico): RedirectResponse
    {
        $mecanico->update($this->datos($request));

        return redirect()
            ->route('mecanicos.index')
            ->with('status', "Mecánico {$mecanico->nombre} actualizado.");
    }

    /** Un titular nunca conserva principal (el campo se excluye al validar). */
    private function datos(MecanicoRequest $request): array
    {
        $datos = $request->validated();

        return [...$datos, 'mecanico_principal_id' => $datos['mecanico_principal_id'] ?? null];
    }

    /**
     * Usuarios con rol mecánico del taller que aún no tienen mecánico
     * vinculado (más el que ya tenga este mecánico).
     */
    private function usuariosDisponibles(?Mecanico $mecanico = null)
    {
        $tallerId = $mecanico?->taller_id ?? auth()->user()->taller_id;

        return User::where('taller_id', $tallerId)
            ->where('role_id', Rol::where('slug', Rol::MECANICO)->value('id'))
            ->whereNotIn('id', Mecanico::whereNotNull('user_id')
                ->when($mecanico?->id, fn ($q, $id) => $q->where('id', '!=', $id))
                ->select('user_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /** Candidatos a principal: titulares activos del taller, sin el propio mecánico. */
    private function titularesActivos(?Mecanico $excluir = null)
    {
        return Mecanico::activos()
            ->titulares()
            ->when($excluir?->id, fn ($q, $id) => $q->where('id', '!=', $id))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }
}
