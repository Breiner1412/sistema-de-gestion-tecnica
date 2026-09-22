<?php

use App\Models\Soporte;
use App\Models\User;
use App\Notifications\CasosEnRiesgoDeSla;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    config([
        'sla.alertas.activas' => true,
        'sla.alertas.copia_gestion' => [User::ROL_ADMIN],
        'sla.semaforo.riesgo' => 85,
    ]);
});

/**
 * Deja un caso asignado con el vencimiento donde se le pida.
 *
 * Se escribe sla_vence_at a mano en vez de dejar correr el reloj: la prueba
 * necesita un caso al 90% sin esperar tres horas y media.
 */
function casoConVencimiento($vence, ?User $tecnico = null): Soporte
{
    $tecnico ??= usuarioCon(User::ROL_TECNICO_SOPORTE, 'Técnico asignado');

    $caso = soporteDePrueba();
    $caso->asignarTecnicoSoporte($tecnico->id);

    $caso->forceFill(['sla_vence_at' => $vence])->saveQuietly();

    return $caso->refresh();
}

it('avisa al técnico del caso que está por vencerse', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE, 'Andrés Loaiza');
    $caso = casoConVencimiento(now()->addMinutes(10), $tecnico);

    $this->artisan('soportes:alertar-sla')->assertSuccessful();

    Notification::assertSentTo($tecnico, CasosEnRiesgoDeSla::class,
        fn(CasosEnRiesgoDeSla $aviso) => $aviso->enRiesgo->contains('id', $caso->id)
            && $aviso->vencidos->isEmpty());
});

it('no avisa del caso que todavía va con holgura', function () {
    casoConVencimiento(now()->addDays(10));

    $this->artisan('soportes:alertar-sla')->assertSuccessful();

    Notification::assertNothingSent();
});

it('copia a gestión cuando el caso ya se venció', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE, 'Andrés Loaiza');
    $jefe = usuarioCon(User::ROL_ADMIN, 'Coordinación');

    $caso = casoConVencimiento(now()->subHour(), $tecnico);

    $this->artisan('soportes:alertar-sla')->assertSuccessful();

    Notification::assertSentTo($jefe, CasosEnRiesgoDeSla::class,
        fn(CasosEnRiesgoDeSla $aviso) => $aviso->vencidos->contains('id', $caso->id));

    Notification::assertSentTo($tecnico, CasosEnRiesgoDeSla::class);
});

it('avisa a gestión del caso en riesgo que nadie ha tomado', function () {
    $jefe = usuarioCon(User::ROL_ADMIN, 'Coordinación');

    $caso = soporteDePrueba();
    $caso->forceFill(['sla_vence_at' => now()->addMinutes(10)])->saveQuietly();

    $this->artisan('soportes:alertar-sla')->assertSuccessful();

    Notification::assertSentTo($jefe, CasosEnRiesgoDeSla::class,
        fn(CasosEnRiesgoDeSla $aviso) => $aviso->enRiesgo->contains('id', $caso->id));
});

it('manda un solo correo con todos los casos de la misma persona', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE, 'Andrés Loaiza');

    casoConVencimiento(now()->addMinutes(10), $tecnico);
    casoConVencimiento(now()->addMinutes(20), $tecnico);
    casoConVencimiento(now()->subHour(), $tecnico);

    $this->artisan('soportes:alertar-sla')->assertSuccessful();

    Notification::assertSentToTimes($tecnico, CasosEnRiesgoDeSla::class, 1);

    Notification::assertSentTo($tecnico, CasosEnRiesgoDeSla::class,
        fn(CasosEnRiesgoDeSla $aviso) => $aviso->enRiesgo->count() === 2
            && $aviso->vencidos->count() === 1);
});

it('no repite el aviso del mismo caso en el mismo nivel', function () {
    $caso = casoConVencimiento(now()->addMinutes(10));

    $this->artisan('soportes:alertar-sla')->assertSuccessful();
    $this->artisan('soportes:alertar-sla')->assertSuccessful();

    expect($caso->refresh()->alerta_sla_nivel)->toBe('riesgo')
        ->and($caso->alerta_sla_at)->not->toBeNull();

    Notification::assertCount(1);
});

it('vuelve a avisar cuando el caso pasa de riesgo a vencido', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE, 'Andrés Loaiza');
    $caso = casoConVencimiento(now()->addMinutes(10), $tecnico);

    $this->artisan('soportes:alertar-sla')->assertSuccessful();

    $caso->forceFill(['sla_vence_at' => now()->subMinutes(5)])->saveQuietly();

    $this->artisan('soportes:alertar-sla')->assertSuccessful();

    expect($caso->refresh()->alerta_sla_nivel)->toBe('vencido');

    Notification::assertSentToTimes($tecnico, CasosEnRiesgoDeSla::class, 2);
});

it('ignora los casos con el reloj en pausa', function () {
    $caso = casoConVencimiento(now()->addMinutes(10));

    $caso->forceFill([
        'sla_pausado_at' => now(),
        'sla_minutos_restantes' => 10,
    ])->saveQuietly();

    $this->artisan('soportes:alertar-sla')->assertSuccessful();

    Notification::assertNothingSent();
});

it('ignora los casos ya cerrados', function () {
    // asignarTecnicoSoporte ya lo dejó en proceso; desde ahí se cierra.
    $caso = casoConVencimiento(now()->subDay());
    $caso->cambiarEstado(Soporte::ESTADO_SOLUCIONADO, 'Se resolvió en la llamada.');

    $this->artisan('soportes:alertar-sla')->assertSuccessful();

    Notification::assertNothingSent();
});

it('con --dry-run no envía ni marca nada', function () {
    $caso = casoConVencimiento(now()->addMinutes(10));

    $this->artisan('soportes:alertar-sla', ['--dry-run' => true])->assertSuccessful();

    Notification::assertNothingSent();

    expect($caso->refresh()->alerta_sla_nivel)->toBeNull();
});

it('no hace nada si las alertas están desactivadas', function () {
    config(['sla.alertas.activas' => false]);

    casoConVencimiento(now()->subDay());

    $this->artisan('soportes:alertar-sla')->assertSuccessful();

    Notification::assertNothingSent();
});
