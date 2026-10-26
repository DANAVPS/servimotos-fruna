<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Dashboard ejecutivo de la administradora. El mecánico tiene su propia
 * pantalla ("Mi día") y se redirige allá.
 */
class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $usuario = $request->user();

        if (! $usuario->esAdministrativo()) {
            return redirect()->route($usuario->esMecanico() ? 'mi-dia' : 'ordenes.kanban');
        }

        $periodo = $this->periodo($request);

        return view('dashboard', [
            'datos' => $this->dashboard->resumen($periodo, $usuario->taller_id),
        ]);
    }

    /**
     * GET /api/dashboard?periodo=semanal|mensual — consulta periódica (15 s) y
     * refresco inmediato por BroadcastChannel. Vive en web.php: sesión +
     * SetTenantContext, sin Sanctum.
     */
    public function datos(Request $request): JsonResponse
    {
        abort_unless($request->user()->esAdministrativo(), 403);

        $request->validate(['periodo' => ['sometimes', Rule::in(DashboardService::PERIODOS)]]);

        return response()->json(
            $this->dashboard->resumen($this->periodo($request), $request->user()->taller_id)
        );
    }

    private function periodo(Request $request): string
    {
        $periodo = $request->query('periodo', 'semanal');

        return in_array($periodo, DashboardService::PERIODOS, true) ? $periodo : 'semanal';
    }
}
