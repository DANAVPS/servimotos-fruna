<?php

namespace App\Http\Requests;

use App\Models\OrdenServicio;
use Illuminate\Foundation\Http\FormRequest;

class MarcarOrdenPagadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var OrdenServicio $orden */
        $orden = $this->route('orden');

        return $this->user()?->can('marcarPagada', $orden) ?? false;
    }

    public function rules(): array
    {
        return [
            'metodo_pago' => ['required', 'in:efectivo,transferencia,tarjeta'],
        ];
    }
}
