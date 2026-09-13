<?php

namespace App\Models\Concerns;

use App\Scopes\TenantScope;
use App\Support\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static void addGlobalScope(\Illuminate\Database\Eloquent\Scope|\Closure|string $scope, \Closure|null $implementation = null)
 * @method static void creating(\Closure|string $callback)
 * @method BelongsTo belongsTo(string $related, string|null $foreignKey = null, string|null $ownerKey = null, string|null $relation = null)
 */
trait PerteneceATaller
{
    protected static function bootPerteneceATaller(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model) {
            if (empty($model->taller_id)) {
                $tallerId = app(TenantManager::class)->tallerId();

                if ($tallerId !== null) {
                    $model->taller_id = $tallerId;
                }
            }
        });
    }

    public function taller(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Taller::class);
    }
}
