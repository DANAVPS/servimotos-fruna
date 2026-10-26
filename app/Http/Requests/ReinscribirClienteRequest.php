<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reinscribir un archivado: solo el PIN (lo verifica AutorizacionService).
 */
class ReinscribirClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cliente'));
    }

    public function rules(): array
    {
        return ['pin_autorizacion' => ['required', 'string']];
    }

    public function messages(): array
    {
        return ['pin_autorizacion.required' => 'Ingresa tu PIN de autorización.'];
    }
}
