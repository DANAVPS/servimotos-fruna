<?php

namespace App\Policies;

use App\Models\CategoriaRepuesto;
use App\Models\User;

class CategoriaRepuestoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esSuperAdmin() || $user->taller_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->esAdministrativo();
    }

    public function update(User $user, CategoriaRepuesto $categoria): bool
    {
        return $user->esAdministrativo()
            && ($user->esSuperAdmin() || $user->taller_id === $categoria->taller_id);
    }
}
