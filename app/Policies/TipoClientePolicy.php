<?php

namespace App\Policies;

use App\Models\TipoCliente;
use App\Models\User;

/**
 * Los tipos de cliente definen precios: solo Administradora/SuperAdmin.
 */
class TipoClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esAdministrativo();
    }

    public function create(User $user): bool
    {
        return $user->esAdministrativo();
    }

    public function update(User $user, TipoCliente $tipo): bool
    {
        return $user->esAdministrativo()
            && ($user->esSuperAdmin() || $user->taller_id === $tipo->taller_id);
    }
}
