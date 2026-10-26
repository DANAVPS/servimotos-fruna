<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Configurar o cambiar el PIN de autorización.
 *  - Primera vez: PIN + confirmación (ya está autenticado con contraseña).
 *  - Cambio: además contraseña actual y PIN anterior (este último lo verifica
 *    AutorizacionService, que cuenta intentos fallidos).
 */
class ActualizarPinRequest extends FormRequest
{
    /** PINs triviales que no se aceptan. */
    private const PINES_DEBILES = ['123456', '654321', '012345', '123123', '112233', '121212'];

    protected $errorBag = 'pinAutorizacion';

    public function authorize(): bool
    {
        return $this->user()->esAdministrativo();
    }

    public function rules(): array
    {
        $reglas = [
            'pin' => [
                'required',
                'digits:6',
                'confirmed',
                'not_in:' . implode(',', self::PINES_DEBILES),
                'not_regex:/^(\d)\1{5}$/', // 000000, 111111…
            ],
        ];

        if ($this->user()->tienePinConfigurado()) {
            $reglas['current_password'] = ['required', 'current_password'];
            $reglas['pin_actual'] = ['required', 'string'];
        }

        return $reglas;
    }

    public function messages(): array
    {
        return [
            'pin.required' => 'Escribe el PIN.',
            'pin.digits' => 'El PIN debe tener exactamente 6 dígitos numéricos.',
            'pin.confirmed' => 'La confirmación no coincide con el PIN.',
            'pin.not_in' => 'Ese PIN es muy fácil de adivinar. Elige otro.',
            'pin.not_regex' => 'El PIN no puede ser el mismo dígito repetido.',
            'current_password.required' => 'Escribe tu contraseña actual.',
            'current_password.current_password' => 'La contraseña no es correcta.',
            'pin_actual.required' => 'Escribe tu PIN actual.',
        ];
    }
}
