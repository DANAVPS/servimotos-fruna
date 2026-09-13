<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrdenServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'exists:clientes,id'],
            'moto_id' => ['required', 'exists:motos,id'],
            'descripcion_falla' => ['nullable', 'string', 'max:1000'],
            'placa' => ['required_without:moto_id', 'regex:/^[A-Z]{3}[0-9]{2}[A-Z]$/'],
        ];
    }
}
