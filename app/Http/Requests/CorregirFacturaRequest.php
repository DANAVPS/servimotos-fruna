<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Corrección de factura: administrativos, motivo obligatorio y PIN (lo
 * verifica AutorizacionService en el controlador, en la misma transacción).
 */
class CorregirFacturaRequest extends FormRequest
{
    public function authorize(): Response
    {
        // Response y no bool: el 403 lleva el mensaje de la Policy
        // ("Orden ya pagada. Para corregir, cree una nueva orden.").
        return Gate::forUser($this->user())->inspect('corregirFactura', $this->route('orden'));
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
            'pin_autorizacion' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Escribe el motivo de la corrección.',
            'motivo.min' => 'Describe el motivo con al menos 10 caracteres.',
            'pin_autorizacion.required' => 'Ingresa tu PIN de autorización.',
        ];
    }
}
