<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

/** El maestro de clientes lo consulta toda la gestión y lo edita el primer nivel. */
class ClientePolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->tieneRol(...User::ROLES_GESTION);
    }

    public function view(User $usuario, Cliente $cliente): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return $usuario->tieneRol(User::ROL_ADMIN, User::ROL_GERENTE, User::ROL_CALL_CENTER);
    }

    /** Incluye contratos y equipos instalados del cliente. */
    public function update(User $usuario, Cliente $cliente): bool
    {
        return $this->create($usuario);
    }
}
