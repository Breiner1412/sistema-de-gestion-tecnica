<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| En el servidor hace falta una sola entrada de cron:
|
|   * * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
|
| En local se puede dejar corriendo `php artisan schedule:work`.
*/

// Devuelve a la cola los casos sin contacto cuyo reintento ya venció.
// Cada cuarto de hora es suficiente: la política los espacia cuatro horas.
Schedule::command('soportes:reintentar-contacto')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();
