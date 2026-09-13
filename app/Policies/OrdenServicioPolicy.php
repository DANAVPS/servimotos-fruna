<?php

namespace App\Policies;

use App\Models\OrdenServicio;
use App\Models\Rol;
use App\Models\User;

class OrdenServicioPolicy
{
    /**
     * Regla crítica: SOLO Administradora (o SuperAdmin) puede marcar
     * una orden como pagada. El Mecánico jamás debe pasar esta verificación,
     * sin importar desde qué dispositivo o vista intente hacerlo.
     */
    public function marcarPagada(User $user, OrdenServicio $orden): bool
    {
        if (! $this->perteneceAlMismoTaller($user, $orden)) {
            return false;
        }

        return $user->esSuperAdmin() || $user->esAdministradora();
    }

    public function verPanel(User $user, OrdenServicio $orden): bool
    {
        return $this->perteneceAlMismoTaller($user, $orden);
    }

    public function cambiarEstado(User $user, OrdenServicio $orden): bool
    {
        if (! $this->perteneceAlMismoTaller($user, $orden)) {
            return false;
        }

        // El mecánico solo puede mover estados operativos, nunca a "pagada".
        return $user->esSuperAdmin() || $user->esAdministradora() || $user->esMecanico();
    }

    private function perteneceAlMismoTaller(User $user, OrdenServicio $orden): bool
    {
        return $user->esSuperAdmin() || $user->taller_id === $orden->taller_id;
    }
}
