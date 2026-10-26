<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Doble verificación del borrado. El modal de la vista es solo interfaz:
 * aquí se exige de nuevo el checkbox y la palabra ELIMINAR.
 */
class EliminarRepuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('repuesto'));
    }

    public function rules(): array
    {
        return [
            'confirmo' => ['accepted'],
            'confirmacion' => ['required', 'string', 'in:ELIMINAR'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmo.accepted' => 'Debes marcar la casilla de confirmación.',
            'confirmacion.required' => 'Escribe ELIMINAR para confirmar.',
            'confirmacion.in' => 'Escribe ELIMINAR (en mayúsculas) para confirmar.',
        ];
    }
}
