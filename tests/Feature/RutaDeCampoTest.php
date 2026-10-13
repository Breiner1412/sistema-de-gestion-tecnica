<?php

use App\Models\OrdenTrabajo;
use App\Models\User;
use Livewire\Volt\Volt;

/*
 * Los filtros por día se arman con rangos (para que MySQL use el índice de
 * fecha_programada): estas pruebas cuidan que sigan trayendo lo mismo.
 */

it('reparte las visitas entre hoy, mañana y atrasadas', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $hoy = visitaDePrueba($usuario, ['fecha_programada' => today()]);
    $tecnicoId = $hoy->tecnico_id;

    $crear = fn ($fecha) => OrdenTrabajo::create([
        'soporte_id' => soporteDePrueba()->id, 'tecnico_id' => $tecnicoId, 'tipo' => 'revision',
        'estado' => OrdenTrabajo::ESTADO_PROGRAMADA, 'fecha_programada' => $fecha, 'franja' => 'manana',
    ]);

    $manana = $crear(today()->addDay());
    $atrasada = $crear(today()->subDays(2));
    $this->actingAs($usuario);

    $ids = fn (string $cuando) => Volt::test('campo.ruta')->set('cuando', $cuando)
        ->viewData('visitas')->pluck('id')->all();

    expect($ids('hoy'))->toBe([$hoy->id])
        ->and($ids('manana'))->toBe([$manana->id])
        ->and($ids('pendientes'))->toBe([$atrasada->id]);
});

it('cuenta las visitas que se cerraron hoy', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);
    $visita->update(['estado' => OrdenTrabajo::ESTADO_COMPLETADO, 'hora_fin' => now()]);
    $this->actingAs($usuario);

    expect(Volt::test('campo.ruta')->viewData('hechasHoy'))->toBe(1);
});
