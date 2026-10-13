<?php

use App\Models\Soporte;
use Illuminate\Console\Scheduling\Schedule;

it('se niega a correr fuera del modo demo y no toca nada', function () {
    config(['demo.activo' => false]);
    $caso = soporteDePrueba();

    $this->artisan('demo:reiniciar')->assertFailed();

    expect(Soporte::find($caso->id))->not->toBeNull();
});

// El reinicio completo se prueba en tests/Unit/ReiniciarDemoCompletoTest.php:
// migrate:fresh no puede correr dentro de la transacción de RefreshDatabase.

it('se programa a las 03:00 hora de Colombia', function () {
    $evento = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'demo:reiniciar'));

    expect($evento)->not->toBeNull()
        ->and($evento->expression)->toBe('0 3 * * *')
        ->and($evento->timezone)->toBe('America/Bogota');
});

it('el login avisa del reinicio nocturno en modo demo', function () {
    config(['demo.activo' => true, 'demo.password' => 'clave-demo-de-prueba']);
    usuarioCon(\App\Models\User::ROL_GERENTE)->update(['email' => 'gerencia@sgt.local']);

    $this->get('/login')->assertSee('se reinician todas las noches a las 03:00');
});
