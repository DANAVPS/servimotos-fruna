<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoriaRepuestoRequest;
use App\Models\CategoriaRepuesto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Sin borrado: una categoría con repuestos se desactiva (activo = false),
 * así el histórico no queda huérfano.
 */
class CategoriaRepuestoController extends Controller
{
    public function index(): View
    {
        $this->authorize('create', CategoriaRepuesto::class);

        $categorias = CategoriaRepuesto::withCount('repuestos')->ordenadas()->get();

        return view('categorias.index', compact('categorias'));
    }

    public function create(): View
    {
        $this->authorize('create', CategoriaRepuesto::class);

        $categoria = new CategoriaRepuesto([
            'orden' => (CategoriaRepuesto::max('orden') ?? 0) + 1,
            'activo' => true,
        ]);

        return view('categorias.create', compact('categoria'));
    }

    public function store(StoreCategoriaRepuestoRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        CategoriaRepuesto::create([...$datos, 'slug' => $this->slugDisponible($datos['nombre'])]);

        return redirect()
            ->route('categorias.index')
            ->with('status', "Categoría \"{$datos['nombre']}\" creada.");
    }

    public function edit(CategoriaRepuesto $categoria): View
    {
        $this->authorize('update', $categoria);

        return view('categorias.edit', compact('categoria'));
    }

    /**
     * El slug no cambia al renombrar: el importador y las categorías base lo usan como identificador.
     */
    public function update(StoreCategoriaRepuestoRequest $request, CategoriaRepuesto $categoria): RedirectResponse
    {
        $categoria->update($request->validated());

        return redirect()
            ->route('categorias.index')
            ->with('status', "Categoría \"{$categoria->nombre}\" actualizada.");
    }

    private function slugDisponible(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'categoria';
        $slug = $base;
        $sufijo = 2;

        while (CategoriaRepuesto::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$sufijo}";
            $sufijo++;
        }

        return $slug;
    }
}
