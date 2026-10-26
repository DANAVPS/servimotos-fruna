<?php

namespace App\Http\Requests;

use App\Models\CategoriaRepuesto;
use App\Support\TenantManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoriaRepuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $categoria = $this->route('categoria');

        return $categoria instanceof CategoriaRepuesto
            ? $this->user()->can('update', $categoria)
            : $this->user()->can('create', CategoriaRepuesto::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'activo' => $this->boolean('activo'),
        ]);
    }

    public function rules(): array
    {
        $categoria = $this->route('categoria');
        $tallerId = $categoria instanceof CategoriaRepuesto
            ? $categoria->taller_id
            : app(TenantManager::class)->tallerId();

        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categorias_repuesto', 'nombre')
                    ->where('taller_id', $tallerId)
                    ->ignore($categoria instanceof CategoriaRepuesto ? $categoria->id : null),
            ],
            'orden' => ['required', 'integer', 'min:0', 'max:999'],
            'activo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe una categoría con este nombre.',
        ];
    }
}
