<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Administrador inicial
    |--------------------------------------------------------------------------
    | La única cuenta con poder total la crea UsuarioSeeder a partir del .env.
    | Ni el correo ni la contraseña van en el código: el repositorio es público
    | y una cuenta admin con clave conocida es una puerta abierta.
    |
    | Sin ADMIN_EMAIL o ADMIN_PASSWORD el seeder no la crea y lo avisa.
    */

    'admin' => [
        'nombre' => env('ADMIN_NAME', 'Administrador'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /** Por debajo de esto el seeder se niega a crear el admin en producción. */
    'longitud_minima_admin' => 12,

];
