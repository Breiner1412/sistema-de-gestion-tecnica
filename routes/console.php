<?php

use App\Support\ModoDemo;
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
| En Docker lo corre el contenedor soporte-tareas con `schedule:work`; en un
| servidor sin Docker, una entrada de cron con `schedule:run` cada minuto.
|
| El bloqueo de withoutOverlapping() vive en la caché (la base de datos,
| compartida por todos los contenedores) y caduca solo: sin un tiempo, un
| proceso que muere con el contenedor lo deja tomado 24 horas y las alertas
| dejan de salir sin que nadie lo note. onOneServer() evita el doble envío
| si algún día corren dos programadores.
*/

// Devuelve a la cola los casos sin contacto cuyo reintento ya venció.
// Cada cuarto de hora es suficiente: la política los espacia cuatro horas.
Schedule::command('soportes:reintentar-contacto')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->runInBackground();

// Avisa de lo que está por vencerse. Solo en horario hábil: el reloj está
// parado fuera de él, así que un correo de madrugada no diría nada nuevo.
Schedule::command('soportes:alertar-sla')
    ->hourly()
    ->weekdays()
    ->between('7:00', '18:00')
    ->withoutOverlapping(10)
    ->onOneServer()
    ->runInBackground();

// La cola de correos atascada (sin worker o con el SMTP caído) no da error en
// ningún lado: esto deja una línea de error en el registro si se acumula.
Schedule::command('queue:monitor', ['database:default', '--max' => 50])
    ->everyFiveMinutes()
    ->onOneServer();

// Demo pública: todo vuelve a su estado de fábrica cada noche. El login
// avisa la hora. Fuera del modo demo ni se programa (y el comando se niega).
Schedule::command('demo:reiniciar')
    ->dailyAt(config('demo.reinicio.hora'))
    ->timezone(config('demo.reinicio.zona'))
    ->when(fn () => ModoDemo::activo())
    ->withoutOverlapping(60);
