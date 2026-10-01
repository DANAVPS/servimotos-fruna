<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solo cantidades por repuesto. Precios y códigos los pone el backend,
 * y los repuestos no vinculados al distribuidor se descartan en el servicio.
 */
class SolicitudPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('solicitarPedido', $this->route('distribuidor'));
    }

    public function rules(): array
    {
        return [
            'cantidades' => ['required', 'array', 'min:1'],
            'cantidades.*' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'cantidades.required' => 'Agrega al menos un repuesto al pedido.',
            'cantidades.*.integer' => 'Las cantidades deben ser números enteros.',
        ];
    }
}
