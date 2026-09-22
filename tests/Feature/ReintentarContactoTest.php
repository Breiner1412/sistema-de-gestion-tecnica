<?php

use App\Models\Soporte;
use App\Models\SoporteHistorialEstado;

/** Deja un caso en "sin contacto" con la hora de reintento que se le indique. */
function casoSinContacto(int $intentos, $proximoIntento): Soporte
{
    $caso = soporteDePrueba();
    $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);
    $caso->cambiarEstado(Soporte::ESTADO_SIN_CONTACTO, 'El cliente no contesta.');

    $caso->forceFill([
        'intentos_contacto' => $intentos,
        'proximo_intento_at' => $proximoIntento,
    ])->saveQuietly();

    return $caso->refresh();
}

it('devuelve a la cola el caso cuyo reintento ya venció', function () {
    $caso = casoSinContacto(intentos: 1, proximoIntento: now()->subHour());

    $this->artisan('soportes:reintentar-contacto')->assertSuccessful();

    $caso->refresh();

    expect($caso->estado)->toBe(Soporte::ESTADO_EN_PROCESO)
        ->and($caso->proximo_intento_at)->toBeNull()
        ->and($caso->slaPausado())->toBeFalse();
});

it('no toca el caso cuyo reintento todavía no llega', function () {
    $caso = casoSinContacto(intentos: 1, proximoIntento: now()->addHours(3));

    $this->artisan('soportes:reintentar-contacto')->assertSuccessful();

    expect($caso->refresh()->estado)->toBe(Soporte::ESTADO_SIN_CONTACTO);
});

it('cierra el caso que ya agotó los intentos', function () {
    config(['sla.sin_contacto.max_intentos' => 3]);

    $caso = casoSinContacto(intentos: 3, proximoIntento: now()->subMinutes(30));

    $this->artisan('soportes:reintentar-contacto')->assertSuccessful();

    $caso->refresh();

    expect($caso->estado)->toBe(Soporte::ESTADO_CERRADO_SIN_CONTACTO)
        ->and($caso->estaCerrado())->toBeTrue()
        ->and($caso->fecha_cierre)->not->toBeNull();
});

it('registra la transición como automática, sin usuario', function () {
    $caso = casoSinContacto(intentos: 1, proximoIntento: now()->subHour());

    $this->artisan('soportes:reintentar-contacto')->assertSuccessful();

    $ultimo = SoporteHistorialEstado::where('soporte_id', $caso->id)->latest('id')->first();

    expect($ultimo->estado_nuevo)->toBe(Soporte::ESTADO_EN_PROCESO)
        ->and($ultimo->automatico)->toBeTrue()
        ->and($ultimo->usuario_id)->toBeNull()
        ->and($ultimo->motivo)->toContain('Reintento programado');
});

it('con --dry-run no cambia nada', function () {
    $caso = casoSinContacto(intentos: 1, proximoIntento: now()->subHour());

    $this->artisan('soportes:reintentar-contacto', ['--dry-run' => true])->assertSuccessful();

    expect($caso->refresh()->estado)->toBe(Soporte::ESTADO_SIN_CONTACTO)
        ->and($caso->proximo_intento_at)->not->toBeNull();
});

it('ignora los casos que no están sin contacto', function () {
    $caso = soporteDePrueba();
    $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);
    $caso->forceFill(['proximo_intento_at' => now()->subDay()])->saveQuietly();

    $this->artisan('soportes:reintentar-contacto')->assertSuccessful();

    expect($caso->refresh()->estado)->toBe(Soporte::ESTADO_EN_PROCESO);
});
