<?php

namespace App\Http\Requests;

use App\Support\TenantManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $tallerId = app(TenantManager::class)->tallerId();

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => [
                'required',
                'string',
                'regex:/^\+57[0-9]{10}$/', // formato colombiano E.164
                Rule::unique('clientes', 'telefono')->where('taller_id', $tallerId),
            ],
            'correo' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'telefono.regex' => 'El teléfono debe tener formato colombiano: +57 seguido de 10 dígitos.',
        ];
    }
}

