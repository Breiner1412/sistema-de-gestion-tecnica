<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Soporte;
use App\Models\User;

/**
 * Quién puede hacer qué con un caso, según el flujo real:
 *
 *  - primer nivel (call_center) registra, asigna y, mientras nadie lo ha
 *    tomado, puede cancelar lo que él mismo registró;
 *  - el nivel 2 (tecnico_soporte) trabaja los casos que son suyos o que
 *    todavía no son de nadie: es el dueño del reloj de SLA;
 *  - redes (ingeniero_redes) solo responde lo que le escalaron a él;
 *  - gerencia y administración pueden todo.
 *
 * La interfaz esconde los botones, pero lo que manda es esto: cada acción de
 * Livewire la vuelve a preguntar, porque una petición a /livewire/update no
 * pasa por el middleware de la ruta.
 */
class SoportePolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->tieneRol(...User::ROLES_GESTION);
    }

    public function view(User $usuario, Soporte $soporte): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(User $usuario): bool
    {
        return $usuario->tieneRol(User::ROL_ADMIN, User::ROL_GERENTE, User::ROL_CALL_CENTER, User::ROL_TECNICO_SOPORTE);
    }

    /** Elegir el responsable del nivel 2. Con una visita en curso, solo el admin. */
    public function asignar(User $usuario, Soporte $soporte): bool
    {
        if (! $this->create($usuario)) {
            return false;
        }

        return $usuario->esAdmin() || ! $soporte->tieneVisitaPendiente();
    }

    /** Resolver, marcar sin contacto, seguimiento, retomar, escalar, programar visita. */
    public function trabajar(User $usuario, Soporte $soporte): bool
    {
        if ($this->esGestion($usuario)) {
            return true;
        }

        return $usuario->tieneRol(User::ROL_TECNICO_SOPORTE)
            && ($soporte->tecnico_soporte_id === null || $soporte->tecnico_soporte_id === $usuario->id);
    }

    public function cancelar(User $usuario, Soporte $soporte): bool
    {
        if ($this->trabajar($usuario, $soporte)) {
            return true;
        }

        return $usuario->tieneRol(User::ROL_CALL_CENTER)
            && $soporte->usuario_registra_id === $usuario->id
            && $soporte->estado === Soporte::ESTADO_PENDIENTE;
    }

    /** El primer nivel también ve la urgencia al recibir la llamada. */
    public function elevarCriticidad(User $usuario, Soporte $soporte): bool
    {
        return $this->trabajar($usuario, $soporte) || $usuario->tieneRol(User::ROL_CALL_CENTER);
    }

    /** Devolver el caso desde redes: el ingeniero al que se le escaló, o el admin. */
    public function responderComoRedes(User $usuario, Soporte $soporte): bool
    {
        return $usuario->esAdmin() || $usuario->id === $soporte->escalado_a_id;
    }

    public function exportar(User $usuario): bool
    {
        return $this->viewAny($usuario);
    }

    private function esGestion(User $usuario): bool
    {
        return $usuario->tieneRol(User::ROL_ADMIN, User::ROL_GERENTE);
    }
}
