<?php

use App\Models\Soporte;
use App\Models\Tecnico;
use App\Models\User;
use Livewire\Volt\Volt;

/*
 * Las listas desplegables solo ofrecen a quien corresponde, pero el valor
 * viaja desde el navegador: el servidor tiene que comprobarlo otra vez.
 */

it('no asigna el caso a alguien que no es técnico de soporte', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));
    $caso = soporteDePrueba();

    Volt::test('soportes.show', ['soporte' => $caso])
        ->set('tecnico_soporte_id', usuarioCon(User::ROL_CALL_CENTER)->id)
        ->call('asignar')
        ->assertHasErrors('tecnico_soporte_id');

    expect($caso->refresh()->tecnico_soporte_id)->toBeNull();
});

it('no asigna el caso a un técnico inactivo', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));
    $inactivo = usuarioCon(User::ROL_TECNICO_SOPORTE);
    $inactivo->update(['estado' => 'inactivo']);

    Volt::test('soportes.show', ['soporte' => soporteDePrueba()])
        ->set('tecnico_soporte_id', $inactivo->id)
        ->call('asignar')
        ->assertHasErrors('tecnico_soporte_id');
});

it('no escala a alguien que no es ingeniero de redes', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));
    $caso = soporteDePrueba();
    $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);

    Volt::test('soportes.show', ['soporte' => $caso->refresh()])
        ->set('ingeniero_redes_id', usuarioCon(User::ROL_TECNICO_CAMPO)->id)
        ->set('motivo_escalamiento', 'Se sospecha corte en el troncal.')
        ->call('escalar')
        ->assertHasErrors('ingeniero_redes_id');

    expect($caso->refresh()->estado)->toBe(Soporte::ESTADO_EN_PROCESO);
});

it('no programa la visita con un técnico de campo inactivo', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));
    $caso = soporteDePrueba();
    $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $inactivo = Tecnico::create(['user_id' => $usuario->id, 'nombre' => $usuario->name, 'estado' => 'inactivo']);

    Volt::test('soportes.show', ['soporte' => $caso->refresh()])
        ->set('tecnico_campo_id', $inactivo->id)
        ->call('programarVisita')
        ->assertHasErrors('tecnico_campo_id');

    expect($caso->ordenesTrabajo()->count())->toBe(0);
});

it('sí asigna a un técnico de soporte activo', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);
    $caso = soporteDePrueba();

    Volt::test('soportes.show', ['soporte' => $caso])
        ->set('tecnico_soporte_id', $tecnico->id)
        ->call('asignar')
        ->assertHasNoErrors();

    expect($caso->refresh()->tecnico_soporte_id)->toBe($tecnico->id);
});
