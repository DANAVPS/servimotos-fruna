<?php

namespace App\Http\Requests;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Paso 1 de la liquidación (mecánico o administradora): repuestos, cantidades
 * y observaciones técnicas. Sin mano de obra ni precios: el mecánico no fija
 * dinero (la mano de obra la pone la administradora al emitir la factura).
 */
class RegistrarTrabajoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('liquidar', $this->route('orden')) ?? false;
    }

    public function rules(): array
    {
        /** @var OrdenServicio $orden */
        $orden = $this->route('orden');

        return [
            // Puede no haber repuestos (servicio solo de mano de obra).
            'items' => ['nullable', 'array'],
            'items.*.repuesto_id' => [
                'required',
                'integer',
                Rule::exists('repuestos', 'id')
                    ->where('taller_id', $orden->taller_id)
                    ->whereNull('deleted_at'),
            ],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000'],
            // Regla 9: obligatorias; aparecen en la factura y el dashboard.
            'observaciones_tecnicas' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.repuesto_id.exists' => 'Uno de los repuestos no existe en el inventario.',
            'observaciones_tecnicas.required' => 'Las observaciones técnicas son obligatorias.',
            'observaciones_tecnicas.min' => 'Describe el trabajo con al menos 5 caracteres.',
        ];
    }
}
