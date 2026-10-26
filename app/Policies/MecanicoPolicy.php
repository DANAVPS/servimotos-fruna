<?php

namespace App\Policies;

use App\Models\Mecanico;
use App\Models\User;

/**
 * El equipo de mecánicos lo gestiona la administración. El mecánico solo los
 * ve en los selectores del Kanban (no pasa por esta Policy).
 */
class MecanicoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esAdministrativo();
    }

    /** Un mecánico pertenece a un taller: el SuperAdmin sin taller no crea. */
    public function create(User $user): bool
    {
        return $user->esAdministrativo() && $user->taller_id !== null;
    }

    public function update(User $user, Mecanico $mecanico): bool
    {
        return $user->esAdministrativo()
            && ($user->esSuperAdmin() || $user->taller_id === $mecanico->taller_id);
    }
}
