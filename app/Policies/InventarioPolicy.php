<?php

namespace App\Policies;

use App\Models\Inventario;
use App\Models\User;

/** La bodega es de gerencia y administración. */
class InventarioPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->tieneRol(User::ROL_ADMIN, User::ROL_GERENTE);
    }

    public function view(User $usuario, Inventario $material): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    /** Editar el material y registrar entradas o salidas de stock. */
    public function update(User $usuario, Inventario $material): bool
    {
        return $this->viewAny($usuario);
    }
}
