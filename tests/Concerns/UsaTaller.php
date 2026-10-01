<?php

namespace Tests\Concerns;

use App\Models\Taller;
use App\Support\TenantManager;

trait UsaTaller
{
    /**
     * Fija el contexto de tenant, como lo haría SetTenantContext en un request.
     */
    protected function enTaller(?Taller $taller): void
    {
        app(TenantManager::class)->establecer($taller?->id);
    }
}
