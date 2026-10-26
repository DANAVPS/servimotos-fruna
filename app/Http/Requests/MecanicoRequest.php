<?php

namespace App\Http\Requests;

use App\Models\Mecanico;
use App\Models\Rol;
use App\Support\Identificadores;
use App\Support\TenantManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de mecánicos. El principal solo aplica a ayudantes: debe
 * ser un titular activo del mismo taller y no puede ser el propio mecánico.
 * Un titular nunca tiene principal (también lo exige un CHECK en la base).
 */
class MecanicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mecanico = $this->route('mecanico');

        return $mecanico
            ? $this->user()->can('update', $mecanico)
            : $this->user()->can('create', Mecanico::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'telefono' => Identificadores::telefono($this->input('telefono')),
            'activo' => $this->boolean('activo'),
        ]);
    }

    public function rules(): array
    {
        /** @var Mecanico|null $mecanico */
        $mecanico = $this->route('mecanico');
        $tallerId = $mecanico?->taller_id ?? app(TenantManager::class)->tallerId();

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'regex:/^\+57[0-9]{10}$/'],
            'tipo' => ['required', Rule::in(array_keys(Mecanico::TIPOS))],
            'mecanico_principal_id' => [
                'exclude_unless:tipo,ayudante',
                'nullable',
                'integer',
                Rule::notIn(array_filter([$mecanico?->id])),
                Rule::exists('mecanicos', 'id')
                    ->where('taller_id', $tallerId)
                    ->where('tipo', 'titular')
                    ->where('activo', true),
            ],
            'activo' => ['boolean'],
            // Usuario con rol mecánico del mismo taller, sin otro mecánico vinculado.
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where('taller_id', $tallerId)
                    ->where('role_id', Rol::where('slug', Rol::MECANICO)->value('id')),
                Rule::unique('mecanicos', 'user_id')->ignore($mecanico?->id),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Mecanico|null $mecanico */
                $mecanico = $this->route('mecanico');

                if ($mecanico?->esTitular() && $this->input('tipo') === 'ayudante') {
                    $ayudantes = $mecanico->subordinados()->count();

                    if ($ayudantes > 0) {
                        $validator->errors()->add('tipo', "Tiene {$ayudantes} " . ($ayudantes === 1 ? 'ayudante' : 'ayudantes')
                            . ' a cargo. Asígnalos a otro titular antes de convertirlo en ayudante.');
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'telefono.regex' => 'El teléfono debe ser un celular colombiano de 10 dígitos (ej. 300 123 4567).',
            'mecanico_principal_id.not_in' => 'Un mecánico no puede ser su propio principal.',
            'mecanico_principal_id.exists' => 'El principal debe ser un titular activo del taller.',
            'user_id.exists' => 'El usuario debe tener rol mecánico en este taller.',
            'user_id.unique' => 'Ese usuario ya está vinculado a otro mecánico.',
        ];
    }

    public function attributes(): array
    {
        return ['mecanico_principal_id' => 'titular principal', 'user_id' => 'usuario del sistema'];
    }
}
