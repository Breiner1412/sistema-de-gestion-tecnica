<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Perfil de consultas
    |--------------------------------------------------------------------------
    | Deja una línea en el registro por cada petición, con cuántas consultas
    | hizo, cuánto tiempo se fue en la base y cuánto en total. Sirve para saber
    | si una pantalla lenta lo es por un N+1, por una consulta sin índice o por
    | algo que no tiene que ver con la base.
    |
    | Apagado por defecto. Escuchar todas las consultas cuesta, y en producción
    | no hace falta: se enciende con PERFIL_CONSULTAS=true mientras se mide y
    | se vuelve a apagar.
    */

    'perfil_consultas' => (bool) env('PERFIL_CONSULTAS', false),

];
