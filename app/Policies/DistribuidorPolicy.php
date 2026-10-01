<?php

namespace App\Policies;

use App\Models\Distribuidor;
use App\Models\User;

/**
 * Distribuidores y pedidos exponen costos y compromisos de compra:
 * solo Administradora/SuperAdmin.
 */
class DistribuidorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esAdministrativo();
    }

    public function view(User $user, Distribuidor $distribuidor): bool
    {
        return $user->esAdministrativo() && $this->perteneceAlMismoTaller($user, $distribuidor);
    }

    public function create(User $user): bool
    {
        return $user->esAdministrativo();
    }

    public function update(User $user, Distribuidor $distribuidor): bool
    {
        return $this->view($user, $distribuidor);
    }

    public function solicitarPedido(User $user, Distribuidor $distribuidor): bool
    {
        return $this->view($user, $distribuidor);
    }

    private function perteneceAlMismoTaller(User $user, Distribuidor $distribuidor): bool
    {
        return $user->esSuperAdmin() || $user->taller_id === $distribuidor->taller_id;
    }
}
