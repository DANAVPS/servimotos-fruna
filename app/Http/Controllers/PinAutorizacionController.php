<?php

namespace App\Http\Controllers;

use App\Exceptions\AutorizacionDenegadaException;
use App\Http\Requests\ActualizarPinRequest;
use App\Models\User;
use App\Services\AutorizacionService;
use Illuminate\Http\RedirectResponse;

class PinAutorizacionController extends Controller
{
    public function __construct(private AutorizacionService $autorizacion)
    {
    }

    public function update(ActualizarPinRequest $request): RedirectResponse
    {
        $user = $request->user();
        $cambio = $user->tienePinConfigurado();

        if ($cambio) {
            try {
                $this->autorizacion->verificarPin($user, $request->input('pin_actual'), 'usuario.cambiar_pin', User::class, $user->id);
            } catch (AutorizacionDenegadaException $e) {
                return back()->withErrors(['pin_actual' => $e->getMessage()], 'pinAutorizacion');
            }
        }

        $user->forceFill(['pin_autorizacion' => $request->input('pin')])->save();

        return redirect()
            ->to(route('profile.edit') . '#pin-autorizacion')
            ->with('status', $cambio ? 'pin-actualizado' : 'pin-configurado');
    }
}
