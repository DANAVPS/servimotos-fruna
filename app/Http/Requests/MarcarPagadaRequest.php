<?php

namespace App\Http\Requests;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Regla 1: la Policy vive aquí (además del middleware role: de la ruta y
 * del chequeo en OrdenService). El método de pago es dato interno (regla 3).
 */
class MarcarPagadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('marcarPagada', $this->route('orden')) ?? false;
    }

    public function rules(): array
    {
        return [
            'metodo_pago' => ['required', Rule::in(array_keys(OrdenServicio::METODOS_PAGO))],
        ];
    }

    public function messages(): array
    {
        return [
            'metodo_pago.required' => 'Selecciona el método de pago.',
            'metodo_pago.in' => 'Selecciona un método de pago válido.',
        ];
    }
}
