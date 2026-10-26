<?php

namespace App\Http\Requests;

use App\Models\Repuesto;
use App\Support\TenantManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $repuesto = $this->route('repuesto');

        return $repuesto instanceof Repuesto
            ? $this->user()->can('update', $repuesto)
            : $this->user()->can('create', Repuesto::class);
    }

    protected function prepareForValidation(): void
    {
        // Compatibilidad con el formulario anterior, que enviaba `modelo_compatible`.
        if (! $this->has('compatibilidades') && $this->has('modelo_compatible')) {
            $this->merge(['compatibilidades' => $this->input('modelo_compatible')]);
        }

        // El formulario marca que envió la sección de distribuidores: si la
        // dejaron vacía hay que desvincularlos todos, no ignorar el campo.
        if ($this->boolean('distribuidores_enviados') && ! $this->has('distribuidores')) {
            $this->merge(['distribuidores' => []]);
        }

        if ($this->filled('codigo_sku')) {
            $this->merge(['codigo_sku' => mb_strtoupper(trim($this->input('codigo_sku')))]);
        }
    }

    public function rules(): array
    {
        $tallerId = $this->tallerId();
        $repuesto = $this->route('repuesto');

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'marca' => ['nullable', 'string', 'max:100'],
            'categoria_repuesto_id' => [
                'nullable',
                'integer',
                Rule::exists('categorias_repuesto', 'id')->where('taller_id', $tallerId),
            ],
            'codigo_sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('repuestos', 'codigo_sku')
                    ->where('taller_id', $tallerId)
                    ->whereNull('deleted_at')
                    ->ignore($repuesto instanceof Repuesto ? $repuesto->id : null),
            ],
            'referencia_proveedor' => ['nullable', 'string', 'max:100'],
            'ubicacion_estante' => ['nullable', 'string', 'max:100'],
            'compatibilidades' => ['nullable', 'string', 'max:2000'],
            'precio_costo' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0', 'gte:precio_costo'],
            'stock_actual' => ['required', 'integer', 'min:0'],
            'stock_minimo' => ['required', 'integer', 'min:0'],

            'distribuidores' => ['nullable', 'array'],
            'distribuidores.*.distribuidor_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('distribuidores', 'id')
                    ->where('taller_id', $tallerId)
                    ->whereNull('deleted_at'),
            ],
            'distribuidores.*.codigo_proveedor' => ['nullable', 'string', 'max:100'],
            'distribuidores.*.precio_costo_proveedor' => ['nullable', 'numeric', 'min:0'],
            'distribuidores.*.tiempo_entrega_dias' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    public function messages(): array
    {
        return [
            'precio_venta.gte' => 'El precio de venta no puede ser menor al precio de costo.',
            'codigo_sku.unique' => 'Ya existe otro repuesto con este SKU en el taller.',
            'categoria_repuesto_id.exists' => 'La categoría seleccionada no es válida.',
            'distribuidores.*.distribuidor_id.exists' => 'El distribuidor seleccionado no es válido.',
            'distribuidores.*.distribuidor_id.distinct' => 'Un distribuidor está repetido.',
        ];
    }

    private function tallerId(): ?int
    {
        $repuesto = $this->route('repuesto');

        return $repuesto instanceof Repuesto
            ? $repuesto->taller_id
            : app(TenantManager::class)->tallerId();
    }
}
