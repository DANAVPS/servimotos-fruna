<?php

namespace App\Policies;

use App\Models\Repuesto;
use App\Models\User;

/**
 * Mecánico: solo lectura del inventario y búsqueda para liquidar, sin
 * ver costos. Administradora/SuperAdmin: gestión completa.
 */
class RepuestoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esSuperAdmin() || $user->taller_id !== null;
    }

    public function view(User $user, Repuesto $repuesto): bool
    {
        return $this->perteneceAlMismoTaller($user, $repuesto);
    }

    public function verCostos(User $user): bool
    {
        return $user->esAdministrativo();
    }

    public function gestionarPrecios(User $user): bool
    {
        return $user->esAdministrativo();
    }

    public function create(User $user): bool
    {
        return $user->esAdministrativo();
    }

    public function update(User $user, Repuesto $repuesto): bool
    {
        return $user->esAdministrativo() && $this->perteneceAlMismoTaller($user, $repuesto);
    }

    public function delete(User $user, Repuesto $repuesto): bool
    {
        return $this->update($user, $repuesto);
    }

    private function perteneceAlMismoTaller(User $user, Repuesto $repuesto): bool
    {
        return $user->esSuperAdmin() || $user->taller_id === $repuesto->taller_id;
    }
}
