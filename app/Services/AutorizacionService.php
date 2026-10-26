<?php

namespace App\Services;

use App\Exceptions\AutorizacionDenegadaException;
use App\Models\AuditoriaAutorizacion;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Segunda llave para acciones sensibles: el PIN de 6 dígitos de la
 * Administradora/SuperAdmin. Cuenta intentos fallidos (cache/Redis) y deja
 * rastro en auditoria_autorizaciones de cada acción autorizada.
 *
 * El PIN nunca se registra: ni en la auditoría, ni en logs, ni en sesión.
 */
class AutorizacionService
{
    public const MAX_INTENTOS = 5;

    public const BLOQUEO_SEGUNDOS = 15 * 60;

    /**
     * @throws AutorizacionDenegadaException si el PIN falla o el usuario está bloqueado.
     */
    public function verificarPin(User $user, string $pin, string $accion, string $modelo, int $modeloId, ?int $tallerId = null): bool
    {
        $clave = $this->claveIntentos($user);

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            $segundos = RateLimiter::availableIn($clave);

            throw new AutorizacionDenegadaException(
                'Demasiados intentos fallidos de PIN. Intenta de nuevo en ' . max((int) ceil($segundos / 60), 1) . ' minutos.',
                intentosRestantes: 0,
                segundosBloqueo: $segundos,
            );
        }

        if (! $user->tienePinConfigurado() || ! $user->verificarPin($pin)) {
            RateLimiter::hit($clave, self::BLOQUEO_SEGUNDOS);
            $restantes = RateLimiter::remaining($clave, self::MAX_INTENTOS);

            throw new AutorizacionDenegadaException(
                $restantes > 0
                    ? "PIN incorrecto. Te quedan {$restantes} " . ($restantes === 1 ? 'intento' : 'intentos') . '.'
                    : 'PIN incorrecto. Se bloqueó la autorización por 15 minutos.',
                intentosRestantes: $restantes,
                segundosBloqueo: $restantes > 0 ? null : RateLimiter::availableIn($clave),
            );
        }

        RateLimiter::clear($clave);

        AuditoriaAutorizacion::create([
            'taller_id' => $tallerId ?? $user->taller_id,
            'user_id' => $user->id,
            'accion' => $accion,
            'modelo_afectado' => $modelo,
            'modelo_id' => $modeloId,
        ]);

        return true;
    }

    public function intentosFallidos(User $user): int
    {
        return RateLimiter::attempts($this->claveIntentos($user));
    }

    private function claveIntentos(User $user): string
    {
        return "pin-autorizacion:{$user->id}";
    }
}
