<?php

namespace App\Http\Middleware;

use App\Support\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(private TenantManager $tenant)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            // SuperAdmin (taller_id null) navega sin restricción de tenant
            // salvo que explícitamente entre al panel de un taller específico.
            $this->tenant->establecer($request->user()->taller_id);
        }

        return $next($request);
    }
}
