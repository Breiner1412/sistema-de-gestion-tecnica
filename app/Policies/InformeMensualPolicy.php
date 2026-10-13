<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InformeMensual;
use App\Models\User;

/** La gestión escribe los informes; cada técnico lee el suyo. */
class InformeMensualPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->tieneRol(User::ROL_ADMIN, User::ROL_GERENTE);
    }

    public function view(User $usuario, InformeMensual $informe): bool
    {
        return $this->viewAny($usuario) || $usuario->id === $informe->tecnico_id;
    }

    /** Abrir el informe de un técnico para un mes. */
    public function create(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    /** Un informe publicado ya fue entregado: no se reescribe. */
    public function update(User $usuario, InformeMensual $informe): bool
    {
        return $this->viewAny($usuario) && ! $informe->estaPublicado();
    }
}
