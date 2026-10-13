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
    | Por defecto, la IP fija de Caddy en la red "proxy": 172.30.0.2, la que le
    | dan docker-compose.local.yml y el ejemplo del README. Si en la VM la red
    | "proxy" usa otra subred, TRUSTED_PROXIES la cambia (una IP o varias,
    | separadas por coma). Sin proxy delante, esa IP no existe y no hace nada.
    */

    'ip_caddy' => '172.30.0.2',

    'proxies' => env('TRUSTED_PROXIES', '172.30.0.2'),

];
