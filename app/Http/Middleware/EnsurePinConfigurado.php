<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Acciones que exigen PIN: si el usuario aún no lo tiene, se le lleva al
 * perfil a configurarlo antes de dejarle entrar al formulario.
 */
class EnsurePinConfigurado
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->tienePinConfigurado()) {
            return $next($request);
        }

        $mensaje = 'Para editar clientes primero debes configurar tu PIN de autorización.';
        $destino = route('profile.edit') . '#pin-autorizacion';

        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje, 'redirect' => $destino], 409);
        }

        return redirect()->to($destino)->with('status-pin', $mensaje);
    }
}
