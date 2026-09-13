<?php

namespace App\Scopes;

use App\Support\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(TenantManager::class);

        if ($tenant->bypassActivo()) {
            return;
        }

        if ($tenant->tallerId() !== null) {
            $builder->where($model->getTable() . '.taller_id', $tenant->tallerId());
        }
    }
}
