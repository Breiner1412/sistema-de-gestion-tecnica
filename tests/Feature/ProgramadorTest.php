<?php

use Illuminate\Console\Scheduling\Schedule;

function eventoProgramado(string $comando)
{
    return collect(app(Schedule::class)->events())
        ->first(fn ($evento) => str_contains($evento->command, $comando));
}

it('el bloqueo de las tareas de SLA caduca en minutos, no en un día', function (string $comando) {
    $evento = eventoProgramado($comando);

    expect($evento)->not->toBeNull()
        ->and($evento->withoutOverlapping)->toBeTrue()
        ->and($evento->expiresAt)->toBe(10)
        ->and($evento->onOneServer)->toBeTrue();
})->with(['soportes:alertar-sla', 'soportes:reintentar-contacto']);
