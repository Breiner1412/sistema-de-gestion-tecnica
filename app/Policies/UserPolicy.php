<?php

namespace App\Policies;

use App\Models\User;

/**
 * La administración del equipo es solo del admin. Las reglas finas (nadie se
 * quita a sí mismo el rol, nunca se queda el sistema sin admin, las cuentas
 * de la demo conservan su correo) viven en el formulario, junto a los datos.
 */
class UserPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->esAdmin();
    }

    public function create(User $usuario): bool
    {
        return $usuario->esAdmin();
    }

    public function update(User $usuario, User $otro): bool
    {
        return $usuario->esAdmin();
    }
}
