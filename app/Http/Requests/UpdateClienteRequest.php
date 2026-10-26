<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Support\Identificadores;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Edición del cliente, cambio manual de estado y edición/alta de sus motos.
 * Exige el PIN de autorización (lo verifica AutorizacionService en el controlador).
 *
 * Motos: `motos[{id}][placa|marca|modelo|anio]` para las existentes y
 * `nueva_moto[...]` (opcional) para agregar una.
 */
class UpdateClienteRequest extends StoreClienteRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cliente'));
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (is_array($this->input('motos'))) {
            $this->merge(['motos' => collect($this->input('motos'))
                ->map(fn ($moto) => is_array($moto) ? [...$moto, 'placa' => Identificadores::placa($moto['placa'] ?? null)] : $moto)
                ->all()]);
        }

        if (is_array($this->input('nueva_moto'))) {
            $this->merge(['nueva_moto' => [
                ...$this->input('nueva_moto'),
                'placa' => Identificadores::placa($this->input('nueva_moto.placa')),
            ]]);
        }
    }

    public function rules(): array
    {
        $reglas = [
            ...$this->reglasCliente($this->route('cliente')),
            'estado_cliente' => ['sometimes', 'string', Rule::in(Cliente::ESTADOS)],
            'pin_autorizacion' => ['required', 'string'],

            'motos' => ['sometimes', 'array'],
            'nueva_moto' => ['sometimes', 'array'],
            'nueva_moto.placa' => $this->reglaPlaca(obligatoria: false),
            'nueva_moto.marca' => ['nullable', 'required_with:nueva_moto.placa', 'string', 'max:100'],
            'nueva_moto.modelo' => ['nullable', 'required_with:nueva_moto.placa', 'string', 'max:100'],
            'nueva_moto.anio' => $this->reglaAnio(),
        ];

        // Una regla por moto: su propia placa no cuenta como duplicada.
        foreach (array_keys((array) $this->input('motos', [])) as $id) {
            $reglas["motos.{$id}.placa"] = $this->reglaPlaca((int) $id);
            $reglas["motos.{$id}.marca"] = ['required', 'string', 'max:100'];
            $reglas["motos.{$id}.modelo"] = ['required', 'string', 'max:100'];
            $reglas["motos.{$id}.anio"] = $this->reglaAnio();
        }

        return $reglas;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $cliente = $this->route('cliente');
                $propias = $cliente->motos()->pluck('id')->map(fn ($id) => (string) $id)->all();
                $enviadas = array_map('strval', array_keys((array) $this->input('motos', [])));

                if (array_diff($enviadas, $propias) !== []) {
                    $validator->errors()->add('motos', 'Una de las motos no pertenece a este cliente.');
                }

                // Dos motos del mismo formulario con la misma placa.
                $placas = array_filter([
                    ...array_column((array) $this->input('motos', []), 'placa'),
                    $this->input('nueva_moto.placa'),
                ]);

                if (count($placas) !== count(array_unique($placas))) {
                    $validator->errors()->add('motos', 'Hay dos motos con la misma placa en el formulario.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'estado_cliente.in' => 'El estado debe ser activo, hibernando o archivado.',
            'pin_autorizacion.required' => 'Ingresa tu PIN de autorización.',
            'motos.*.placa.required' => 'La placa es obligatoria.',
            'motos.*.placa.regex' => 'La placa debe tener formato de moto colombiana: 3 letras, 2 números y una letra final opcional (ej. ABC12D).',
            'motos.*.marca.required' => 'La marca de la moto es obligatoria.',
            'motos.*.modelo.required' => 'El modelo de la moto es obligatorio.',
            'nueva_moto.placa.regex' => 'La placa debe tener formato de moto colombiana: 3 letras, 2 números y una letra final opcional (ej. ABC12D).',
            'nueva_moto.marca.required_with' => 'Indica la marca de la moto nueva.',
            'nueva_moto.modelo.required_with' => 'Indica el modelo de la moto nueva.',
        ];
    }

    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'motos.*.placa' => 'placa',
            'motos.*.anio' => 'año',
            'nueva_moto.placa' => 'placa',
            'nueva_moto.anio' => 'año',
        ];
    }
}
