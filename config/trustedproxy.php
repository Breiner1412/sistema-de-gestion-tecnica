<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confianza
    |--------------------------------------------------------------------------
    | Detrás de Caddy, la petición llega a PHP como http y desde la IP del
    | proxy. Solo si esa IP es de confianza Laravel cree en X-Forwarded-Proto y
    | X-Forwarded-For: así genera URLs https, marca la cookie como segura y
    | limita los intentos de login por la IP real del visitante y no por la
    | del proxy, que es la misma para todos.
    |
    | Una IP o varias separadas por coma (la de Caddy en la red "proxy").
    | Vacío: no se confía en nadie, que es lo correcto sin proxy delante.
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
