<?php

namespace App\Http\Requests;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Paso 2 de la liquidación: emitir la factura (solo administrativos).
 * Regla 5: solo se aceptan repuesto y cantidad. Cualquier precio que llegue
 * en el request se descarta; FacturaService lo recalcula.
 */
class LiquidarOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('emitirFactura', $this->route('orden')) ?? false;
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
            'mano_obra' => ['required', 'numeric', 'min:0', 'max:99999999'],
            // Regla 9: obligatorias; aparecen en la factura y el dashboard.
            'observaciones_tecnicas' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (empty($this->input('items')) && (float) $this->input('mano_obra') <= 0) {
                    $validator->errors()->add('items', 'Agrega al menos un repuesto o el valor de la mano de obra.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.repuesto_id.exists' => 'Uno de los repuestos no existe en el inventario.',
            'mano_obra.required' => 'Ingresa el valor de la mano de obra (0 si no aplica).',
            'observaciones_tecnicas.required' => 'Las observaciones técnicas son obligatorias.',
            'observaciones_tecnicas.min' => 'Describe el trabajo con al menos 5 caracteres.',
        ];
    }
}
