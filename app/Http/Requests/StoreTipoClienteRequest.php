<?php

namespace App\Http\Requests;

use App\Models\TipoCliente;
use App\Support\TenantManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTipoClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tipo = $this->route('tipoCliente');

        return $tipo instanceof TipoCliente
            ? $this->user()->can('update', $tipo)
            : $this->user()->can('create', TipoCliente::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'activo' => $this->boolean('activo'),
            // Acepta coma decimal: "12,5" => 12.5
            'descuento_repuestos_pct' => str_replace(',', '.', (string) $this->input('descuento_repuestos_pct')),
        ]);
    }

    public function rules(): array
    {
        $tipo = $this->route('tipoCliente');
        $tallerId = $tipo instanceof TipoCliente ? $tipo->taller_id : app(TenantManager::class)->tallerId();

        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('tipos_cliente', 'nombre')
                    ->where('taller_id', $tallerId)
                    ->ignore($tipo instanceof TipoCliente ? $tipo->id : null),
            ],
            // Más de 100% dejaría precios negativos: se bloquea.
            'descuento_repuestos_pct' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'activo' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $tipo = $this->route('tipoCliente');

                if ($tipo instanceof TipoCliente && $tipo->esRegular() && ! $this->boolean('activo')) {
                    $validator->errors()->add(
                        'activo',
                        'El tipo Regular no se puede desactivar: es el que se aplica a clientes sin tipo.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un tipo de cliente con este nombre.',
            'descuento_repuestos_pct.max' => 'El descuento no puede ser mayor a 100%: dejaría precios negativos.',
            'descuento_repuestos_pct.min' => 'El descuento no puede ser negativo.',
            'descuento_repuestos_pct.decimal' => 'El descuento admite máximo 2 decimales.',
        ];
    }

    public function attributes(): array
    {
        return ['descuento_repuestos_pct' => 'descuento'];
    }
}
