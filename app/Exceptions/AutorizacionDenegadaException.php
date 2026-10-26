<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * PIN incorrecto o bloqueado por intentos.
 *  - Petición JSON (el modal del PIN): 403 con el mensaje, el formulario no se pierde.
 *  - Petición normal (sin JS): vuelve al formulario con los datos escritos (sin el PIN) y el error.
 */
class AutorizacionDenegadaException extends HttpException
{
    public function __construct(
        string $mensaje,
        public readonly ?int $intentosRestantes = null,
        public readonly ?int $segundosBloqueo = null,
    ) {
        parent::__construct(403, $mensaje);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'intentos_restantes' => $this->intentosRestantes,
                'bloqueado_segundos' => $this->segundosBloqueo,
            ], 403);
        }

        return back()
            ->withInput($request->except(['pin_autorizacion', 'pin', 'pin_confirmation', 'pin_actual', 'current_password']))
            ->withErrors(['pin_autorizacion' => $this->getMessage()]);
    }
}
