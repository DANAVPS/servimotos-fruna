<?php

namespace App\Http\Requests;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Drag & drop del Kanban. `pagada` nunca es un destino válido; el resto de
 * reglas de transición (liquidación, orígenes bloqueados) vive en OrdenService.
 */
class CambiarEstadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cambiarEstado', $this->route('orden')) ?? false;
    }

    public function rules(): array
    {
        /** @var OrdenServicio $orden */
        $orden = $this->route('orden');

        // en_espera_revision → en_revision: mecánico y observaciones iniciales obligatorios.
        $tomaRevision = $this->input('estado') === 'en_revision'
            && $orden->estado === 'en_espera_revision';

        return [
            'estado' => ['required', Rule::in(array_diff(OrdenServicio::ESTADOS, ['pagada']))],
            'mecanico_id' => [
                Rule::requiredIf($tomaRevision),
                'nullable',
                'integer',
                Rule::exists('mecanicos', 'id')
                    ->where('taller_id', $orden->taller_id)
                    ->where('activo', true),
            ],
            'observaciones' => [Rule::requiredIf($tomaRevision), 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.in' => 'Ese estado no es un destino válido.',
            'mecanico_id.required' => 'Selecciona el mecánico que revisará la moto.',
            'mecanico_id.exists' => 'El mecánico no existe o está inactivo.',
            'observaciones.required' => 'Escribe las observaciones iniciales de la revisión.',
        ];
    }
}
