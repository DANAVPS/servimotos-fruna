<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\Moto;
use App\Scopes\ClienteActivoScope;
use App\Support\Identificadores;
use App\Support\TenantManager;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de cliente CON su moto (un solo formulario).
 */
class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Cliente::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'telefono' => Identificadores::telefono($this->input('telefono')),
            'cedula_nit' => Identificadores::cedulaNit($this->input('cedula_nit')),
        ]);

        if (is_array($this->input('moto'))) {
            $this->merge(['moto' => [
                ...$this->input('moto'),
                'placa' => Identificadores::placa($this->input('moto.placa')),
            ]]);
        }
    }

    public function rules(): array
    {
        return [
            ...$this->reglasCliente(),
            'moto' => ['required', 'array'],
            'moto.placa' => $this->reglaPlaca(),
            'moto.marca' => ['required', 'string', 'max:100'],
            'moto.modelo' => ['required', 'string', 'max:100'],
            'moto.anio' => $this->reglaAnio(),
        ];
    }

    protected function reglaAnio(): array
    {
        return ['nullable', 'integer', 'min:1950', 'max:' . (now()->year + 1)];
    }

    protected function reglaPlaca(?int $ignorarMotoId = null, bool $obligatoria = true): array
    {
        return [
            $obligatoria ? 'required' : 'nullable',
            'string',
            'regex:' . Identificadores::PATRON_PLACA_MOTO,
            $this->placaDisponible($ignorarMotoId),
        ];
    }

    public function messages(): array
    {
        return [
            'telefono.regex' => 'El teléfono debe ser un celular colombiano de 10 dígitos (ej. 300 123 4567).',
            'telefono.unique' => 'Ya hay un cliente con este teléfono.',
            'cedula_nit.regex' => 'La cédula o NIT no es válida. Usa solo números (y el dígito de verificación con guion, si es NIT).',
            'cedula_nit.unique' => 'Ya hay un cliente con esta cédula o NIT.',
            'tipo_cliente_id.exists' => 'El tipo de cliente no es válido o está inactivo.',
            'moto.placa.required' => 'La placa de la moto es obligatoria.',
            'moto.placa.regex' => 'La placa debe tener formato de moto colombiana: 3 letras, 2 números y una letra final opcional (ej. ABC12D).',
            'moto.marca.required' => 'La marca de la moto es obligatoria.',
            'moto.modelo.required' => 'El modelo de la moto es obligatorio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'cedula_nit' => 'cédula o NIT',
            'tipo_cliente_id' => 'tipo de cliente',
            'moto.placa' => 'placa',
            'moto.marca' => 'marca',
            'moto.modelo' => 'modelo',
            'moto.anio' => 'año',
        ];
    }

    /**
     * Reglas de datos del cliente, compartidas con UpdateClienteRequest.
     */
    protected function reglasCliente(?Cliente $cliente = null): array
    {
        $tallerId = $cliente?->taller_id ?? app(TenantManager::class)->tallerId();

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => [
                'required',
                'string',
                'regex:/^\+57[0-9]{10}$/', // formato colombiano E.164
                Rule::unique('clientes', 'telefono')->where('taller_id', $tallerId)->ignore($cliente?->id),
            ],
            'cedula_nit' => [
                'nullable',
                'string',
                'max:20',
                'regex:' . Identificadores::PATRON_CEDULA_NIT,
                Rule::unique('clientes', 'cedula_nit')->where('taller_id', $tallerId)->ignore($cliente?->id),
            ],
            'correo' => ['nullable', 'email', 'max:255'],
            'tipo_cliente_id' => [
                'nullable',
                'integer',
                Rule::exists('tipos_cliente', 'id')
                    ->where('taller_id', $tallerId)
                    ->where(fn ($q) => $q->where('activo', true)->when(
                        $cliente?->tipo_cliente_id,
                        fn ($q2, $actual) => $q2->orWhere('id', $actual) // conservar uno ya asignado aunque esté inactivo
                    )),
            ],
        ];
    }

    /**
     * Placa única por taller, con un mensaje que dice a nombre de quién está.
     * $ignorarMotoId: al editar, la propia moto no cuenta como duplicada.
     */
    protected function placaDisponible(?int $ignorarMotoId = null): Closure
    {
        return function (string $atributo, mixed $placa, Closure $fallar) use ($ignorarMotoId) {
            $moto = Moto::where('placa', $placa)
                ->when($ignorarMotoId, fn ($q) => $q->where('id', '!=', $ignorarMotoId))
                ->first();

            if ($moto === null) {
                return;
            }

            $duenio = Cliente::withoutGlobalScope(ClienteActivoScope::class)->find($moto->cliente_id);

            $fallar($duenio
                ? "Esta moto ya está registrada a nombre de {$duenio->nombre}."
                : 'Esta moto ya está registrada.');
        };
    }
}
