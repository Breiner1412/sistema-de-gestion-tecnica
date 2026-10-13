<?php

use App\Models\User;
use Livewire\Volt\Volt;

it('una cuenta desactivada no puede iniciar sesión', function () {
    $usuario = usuarioCon(User::ROL_CALL_CENTER);
    $usuario->update(['estado' => 'inactivo']);

    Volt::test('pages.auth.login')
        ->set('form.email', $usuario->email)
        ->set('form.password', 'secreto123')
        ->call('login')
        ->assertHasErrors('form.email');

    $this->assertGuest();
});

it('desactivar una cuenta saca a quien ya tenía la sesión abierta', function () {
    $usuario = usuarioCon(User::ROL_CALL_CENTER);
    $this->actingAs($usuario)->get(route('soportes.index'))->assertOk();

    $usuario->update(['estado' => 'inactivo']);

    $this->get(route('soportes.index'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('la cola del celular recibe un 401 si la cuenta se desactivó', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);
    $usuario->update(['estado' => 'inactivo']);

    $this->actingAs($usuario)
        ->postJson(route('campo.cerrar', $visita), cierreCompleto())
        ->assertUnauthorized();
});

it('una cuenta activa entra normalmente', function () {
    $usuario = usuarioCon(User::ROL_CALL_CENTER);

    Volt::test('pages.auth.login')
        ->set('form.email', $usuario->email)
        ->set('form.password', 'secreto123')
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($usuario);
});
