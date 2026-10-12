<?php

namespace App\Support;

use App\Models\User;

/**
 * La demo pública comparte cuentas entre desconocidos. Lo que aquí se decide
 * es qué no puede hacer nadie con ellas, para que la demo siga entera para el
 * siguiente visitante.
 */
class ModoDemo
{
    public static function activo(): bool
    {
        return (bool) config('demo.activo');
    }

    /**
     * Ajustes que se imponen al arrancar en modo demo, por encima del .env:
     * el correo va al registro. Las alertas de SLA y los enlaces de
     * recuperación existen, pero no salen hacia buzones reales.
     */
    public static function aplicar(): void
    {
        if (! self::activo()) {
            return;
        }

        config(['mail.default' => 'log']);
    }

    /** Cuentas que siembra el sistema: las del dominio demo y el admin del .env. */
    public static function esCuentaSemilla(User $usuario): bool
    {
        $dominio = '@'.config('demo.dominio');

        return str_ends_with(mb_strtolower($usuario->email), $dominio)
            || mb_strtolower($usuario->email) === mb_strtolower((string) config('cuentas.admin.email'));
    }

    /** ¿Se protege esta cuenta de cambios hechos desde la propia demo? */
    public static function protege(User $usuario): bool
    {
        return self::activo() && self::esCuentaSemilla($usuario);
    }
}
