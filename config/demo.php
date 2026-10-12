<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modo demo
    |--------------------------------------------------------------------------
    | Para la demo pública de portafolio. Con DEMO_MODE=true:
    |  - el correo va al registro, nunca a un buzón real;
    |  - las cuentas sembradas no se pueden borrar ni cambiarles la clave o
    |    el correo desde la aplicación;
    |  - el login publica las cuentas de prueba y avisa del reinicio nocturno.
    */

    'activo' => (bool) env('DEMO_MODE', false),

    /** Dominio de las cuentas sembradas por UsuarioSeeder. */
    'dominio' => 'sgt.local',

    /*
    | Cuentas que el login muestra en modo demo, una por rol. Ninguna es de
    | administrador: aunque se agregue una aquí, ModoDemo la descarta.
    */
    'cuentas_publicas' => [
        'gerencia@sgt.local',
        'andres.arango@sgt.local',
        'andres.duarte@sgt.local',
        'redes1@sgt.local',
        'campo1@sgt.local',
    ],

    /*
    |--------------------------------------------------------------------------
    | Contraseña de las cuentas de demostración
    |--------------------------------------------------------------------------
    | Todas las cuentas sembradas (@sgt.local) comparten esta clave. Sale del
    | .env para que no viva en el repositorio. Sin DEMO_PASSWORD, el seeder les
    | pone una clave aleatoria que nadie conoce: las cuentas existen —el
    | histórico las necesita como responsables de los casos— pero no se puede
    | entrar con ellas.
    */

    'password' => env('DEMO_PASSWORD'),

];
