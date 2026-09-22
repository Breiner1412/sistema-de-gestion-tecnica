<?php

use App\Models\InformeMensual;
use App\Models\Soporte;
use App\Models\User;
use App\Support\MetricasTecnico;

/** Deja N casos de un técnico dentro de un mes concreto. */
function casosDelMes(User $tecnico, int $anio, int $mes, int $cantidad, string $estado = Soporte::ESTADO_SOLUCIONADO): void
{
    foreach (range(1, $cantidad) as $i) {
        $caso = soporteDePrueba();

        $caso->forceFill([
            'tecnico_soporte_id' => $tecnico->id,
            'estado' => $estado,
            'tiempo_resolucion' => 60 * $i,
            'created_at' => now()->setDate($anio, $mes, min($i, 28))->setTime(9, 0),
        ])->saveQuietly();
    }
}

it('cuenta solo los casos del técnico y del mes', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);
    $otro = usuarioCon(User::ROL_TECNICO_SOPORTE);

    casosDelMes($tecnico, 2026, 3, 4);
    casosDelMes($tecnico, 2026, 4, 2);   // otro mes
    casosDelMes($otro, 2026, 3, 5);      // otro técnico

    $cifras = MetricasTecnico::para($tecnico, 2026, 3)->calcular();

    expect($cifras['total'])->toBe(4)
        ->and($cifras['resueltos'])->toBe(4)
        ->and($cifras['tasa_resolucion'])->toBe(100);
});

it('separa el sin contacto y calcula su tasa', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);

    casosDelMes($tecnico, 2026, 3, 3);
    casosDelMes($tecnico, 2026, 3, 1, Soporte::ESTADO_CERRADO_SIN_CONTACTO);

    $cifras = MetricasTecnico::para($tecnico, 2026, 3)->calcular();

    expect($cifras['total'])->toBe(4)
        ->and($cifras['sin_contacto'])->toBe(1)
        ->and($cifras['tasa_sin_contacto'])->toBe(25);
});

it('ubica al técnico frente al equipo', function () {
    $lider = usuarioCon(User::ROL_TECNICO_SOPORTE);
    $segundo = usuarioCon(User::ROL_TECNICO_SOPORTE);

    casosDelMes($lider, 2026, 3, 6);
    casosDelMes($segundo, 2026, 3, 2);

    $cifras = MetricasTecnico::para($lider, 2026, 3)->calcular();

    expect($cifras['equipo']['tecnicos_activos'])->toBe(2)
        ->and($cifras['equipo']['posicion'])->toBe(1)
        ->and($cifras['equipo']['participacion'])->toBe(75.0);
});

it('devuelve cifras en cero para un mes sin actividad', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);

    $cifras = MetricasTecnico::para($tecnico, 2026, 3)->calcular();

    expect($cifras['total'])->toBe(0)
        ->and($cifras['tasa_resolucion'])->toBeNull()
        ->and($cifras['por_dia'])->toBe([]);
});

it('congela las cifras al publicar', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);
    casosDelMes($tecnico, 2026, 3, 3);

    $informe = InformeMensual::create([
        'tecnico_id' => $tecnico->id,
        'anio' => 2026,
        'mes' => 3,
        'observaciones' => 'Buen mes, atención al tiempo de cierre.',
    ]);

    $informe->publicar();

    // Llegan más casos del mismo mes después de publicar.
    casosDelMes($tecnico, 2026, 3, 5);

    expect($informe->fresh()->cifras()['total'])->toBe(3)
        ->and($informe->fresh()->estaPublicado())->toBeTrue();
});

it('un borrador recalcula al vuelo', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);
    casosDelMes($tecnico, 2026, 3, 2);

    $informe = InformeMensual::create(['tecnico_id' => $tecnico->id, 'anio' => 2026, 'mes' => 3]);

    expect($informe->cifras()['total'])->toBe(2);

    casosDelMes($tecnico, 2026, 3, 3);

    expect($informe->cifras()['total'])->toBe(5);
});

it('no permite dos informes del mismo técnico y mes', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);

    InformeMensual::create(['tecnico_id' => $tecnico->id, 'anio' => 2026, 'mes' => 3]);

    expect(fn() => InformeMensual::create(['tecnico_id' => $tecnico->id, 'anio' => 2026, 'mes' => 3]))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});
