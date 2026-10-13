<?php

namespace App\Policies;

use App\Models\OrdenTrabajo;
use App\Models\User;

/** El técnico de campo solo ve y trabaja sus visitas; la gestión, todas. */
class OrdenTrabajoPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->tieneRol(...User::ROLES_GESTION) || $usuario->tieneRol(User::ROL_TECNICO_CAMPO);
    }

    public function view(User $usuario, OrdenTrabajo $orden): bool
    {
        return $usuario->tieneRol(...User::ROLES_GESTION) || $this->esSuya($usuario, $orden);
    }

    /** Material, observaciones, inicio y cierre de la visita. */
    public function update(User $usuario, OrdenTrabajo $orden): bool
    {
        return $this->view($usuario, $orden);
    }

    private function esSuya(User $usuario, OrdenTrabajo $orden): bool
    {
        $tecnicoId = $usuario->tecnico?->id;

        return $tecnicoId !== null && $tecnicoId === $orden->tecnico_id;
    }
}
