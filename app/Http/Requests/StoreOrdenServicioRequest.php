<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Support\Identificadores;
use App\Support\TenantManager;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Orden manual desde el Kanban. Tres combinaciones:
 *  - cliente_id + moto_id: cliente y moto existentes.
 *  - cliente_id + moto[...]: cliente existente que trae una moto nueva (sin PIN: es recepción).
 *  - cliente[...] + moto[...]: cliente nuevo, SIEMPRE como Regular (tipo_cliente_id se ignora).
 *
 * Hereda de StoreClienteRequest las reglas de cliente y placa (normalización,
 * unicidad por taller, mensaje "a nombre de X").
 */
class StoreOrdenServicioRequest extends StoreClienteRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user?->can('create', OrdenServicio::class)) {
            return false;
        }

        return $this->filled('cliente_id') || $user->can('crearDesdeOrden', Cliente::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('cliente'))) {
            $this->merge(['cliente' => [
                ...$this->input('cliente'),
                'telefono' => Identificadores::telefono($this->input('cliente.telefono')),
                'cedula_nit' => Identificadores::cedulaNit($this->input('cliente.cedula_nit')),
            ]]);
        }

        if (is_array($this->input('moto'))) {
            $this->merge(['moto' => [
                ...$this->input('moto'),
                'placa' => Identificadores::placa($this->input('moto.placa')),
            ]]);
        }
    }

    public function rules(): array
    {
        $tallerId = app(TenantManager::class)->tallerId();
        $clienteExistente = $this->filled('cliente_id');
        $motoExistente = $clienteExistente && $this->filled('moto_id');

        $reglas = [
            'descripcion_falla' => ['nullable', 'string', 'max:1000'],
        ];

        if ($clienteExistente) {
            // Los archivados no se eligen: se reinscriben (con PIN) desde Clientes.
            $reglas['cliente_id'] = [
                'integer',
                Rule::exists('clientes', 'id')
                    ->where('taller_id', $tallerId)
                    ->where(fn ($q) => $q->where('estado_cliente', '!=', 'archivado')),
            ];
        } else {
            $reglas['cliente'] = ['required', 'array'];

            foreach (Arr::except($this->reglasCliente(), ['tipo_cliente_id']) as $campo => $regla) {
                $reglas["cliente.{$campo}"] = $regla;
            }
        }

        if ($motoExistente) {
            $reglas['moto_id'] = [
                'integer',
                Rule::exists('motos', 'id')
                    ->where('taller_id', $tallerId)
                    ->where('cliente_id', (int) $this->input('cliente_id')),
            ];
        } else {
            $reglas['moto'] = ['required', 'array'];
            $reglas['moto.placa'] = $this->reglaPlaca();
            $reglas['moto.marca'] = ['required', 'string', 'max:100'];
            $reglas['moto.modelo'] = ['required', 'string', 'max:100'];
            $reglas['moto.anio'] = $this->reglaAnio();
        }

        return $reglas;
    }

    public function messages(): array
    {
        $mensajes = [
            'cliente_id.exists' => 'El cliente no existe o está archivado (reinscríbelo desde Clientes).',
            'moto_id.exists' => 'La moto no pertenece a este cliente.',
            'cliente.required' => 'Elige un cliente o registra uno nuevo.',
            'moto.required' => 'Elige una moto del cliente o registra una nueva.',
        ];

        foreach (parent::messages() as $clave => $mensaje) {
            $mensajes[str_starts_with($clave, 'moto.') ? $clave : "cliente.{$clave}"] = $mensaje;
        }

        return $mensajes;
    }

    public function attributes(): array
    {
        return [
            'cliente.nombre' => 'nombre',
            'cliente.telefono' => 'teléfono',
            'cliente.cedula_nit' => 'cédula o NIT',
            'cliente.correo' => 'correo',
            ...Arr::only(parent::attributes(), ['moto.placa', 'moto.marca', 'moto.modelo', 'moto.anio']),
        ];
    }
}
