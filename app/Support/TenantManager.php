<?php

namespace App\Support;

class TenantManager
{
    private ?int $tallerId = null;

    private bool $bypassActivo = false;

    public function establecer(?int $tallerId): void
    {
        $this->tallerId = $tallerId;
    }

    public function tallerId(): ?int
    {
        return $this->tallerId;
    }

    /**
     * SuperAdmin puede necesitar consultar sin restricción de tenant
     * (ej. panel de soporte). Se activa explícitamente y solo por request.
     */
    public function conBypass(callable $callback): mixed
    {
        $this->bypassActivo = true;

        try {
            return $callback();
        } finally {
            $this->bypassActivo = false;
        }
    }

    public function bypassActivo(): bool
    {
        return $this->bypassActivo;
    }
}
