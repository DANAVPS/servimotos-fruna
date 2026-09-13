<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$rolesPermitidos): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        $tienePermiso = $user->esSuperAdmin() || in_array($user->rol?->slug, $rolesPermitidos, true);

        abort_unless($tienePermiso, 403, 'No tienes permiso para acceder a esta sección.');

        return $next($request);
    }
}
