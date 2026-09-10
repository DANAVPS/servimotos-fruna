<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ClienteActivoScope implements Scope
{
    /**
     * Excluye por defecto a los clientes archivados de las consultas,
     * conforme a la regla de eliminación lógica (10 meses de inactividad).
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('estado_cliente', '!=', 'archivado');
    }
}
