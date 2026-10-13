<?php

use App\Models\Inventario;
use App\Models\MaterialOrden;
use App\Models\OrdenTrabajo;
use App\Models\User;
use Livewire\Volt\Volt;

/** Una visita ya completada, con material consumido. */
function visitaCerrada(User $usuario): array
{
    $visita = visitaDePrueba($usuario);
    $material = Inventario::create(['nombre' => 'Patch cord 1.5 m', 'tipo' => 'cable', 'cantidad_total' => 10]);
    $consumo = MaterialOrden::create(['orden_id' => $visita->id, 'material_id' => $material->id, 'cantidad_usada' => 2]);
    $visita->update(['estado' => OrdenTrabajo::ESTADO_COMPLETADO, 'observaciones' => 'Trabajo terminado en sitio.']);

    return [$visita->refresh(), $material, $consumo];
}

it('no se reabre una visita cerrada desde la pantalla de escritorio', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    [$visita] = visitaCerrada($usuario);
    $this->actingAs($usuario);

    Volt::test('ordenes.show', ['orden' => $visita])
        ->set('estado', OrdenTrabajo::ESTADO_EN_PROGRESO)
        ->set('observaciones', 'La reabro y le cambio el texto.')
        ->call('actualizar')
        ->assertHasErrors('estado');

    expect($visita->refresh()->estado)->toBe(OrdenTrabajo::ESTADO_COMPLETADO)
        ->and($visita->observaciones)->toBe('Trabajo terminado en sitio.');
});

it('no se agrega ni se quita material de una visita cerrada', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    [$visita, $material, $consumo] = visitaCerrada($usuario);
    $this->actingAs($usuario);

    Volt::test('ordenes.show', ['orden' => $visita])
        ->call('quitarMaterial', $consumo->id)
        ->assertHasErrors('estado');

    Volt::test('ordenes.show', ['orden' => $visita])
        ->set('material_id', $material->id)
        ->set('cantidad_usada', 3)
        ->call('agregarMaterial')
        ->assertHasErrors('estado');

    expect($material->refresh()->cantidad_total)->toBe(8)
        ->and(MaterialOrden::where('orden_id', $visita->id)->count())->toBe(1);
});
