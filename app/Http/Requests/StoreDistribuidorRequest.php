<?php

namespace App\Http\Requests;

use App\Models\Distribuidor;
use App\Support\TenantManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDistribuidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $distribuidor = $this->route('distribuidor');

        return $distribuidor instanceof Distribuidor
            ? $this->user()->can('update', $distribuidor)
            : $this->user()->can('create', Distribuidor::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['activo' => $this->boolean('activo', true)]);
    }

    public function rules(): array
    {
        $distribuidor = $this->route('distribuidor');
        $tallerId = $distribuidor instanceof Distribuidor
            ? $distribuidor->taller_id
            : app(TenantManager::class)->tallerId();

        return [
            'nombre' => [
                'required',
                'string',
                'max:150',
                Rule::unique('distribuidores', 'nombre')
                    ->where('taller_id', $tallerId)
                    ->whereNull('deleted_at')
                    ->ignore($distribuidor instanceof Distribuidor ? $distribuidor->id : null),
            ],
            'contacto' => ['nullable', 'string', 'max:150'],
            'telefono_whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'activo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un distribuidor con este nombre.',
            'telefono_whatsapp.regex' => 'El teléfono solo puede tener números, espacios, guiones y un + inicial.',
        ];
    }
}
