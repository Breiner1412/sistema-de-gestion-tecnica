<?php

return [

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
