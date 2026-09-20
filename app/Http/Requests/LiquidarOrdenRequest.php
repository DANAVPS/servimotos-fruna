<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LiquidarOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cambiarEstado', $this->route('orden')) ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.repuesto_id' => ['required', 'exists:repuestos,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
            'items.*.mano_obra' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
