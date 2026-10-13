<?php

use App\Models\User;

/*
 * Tema de la interfaz: se guarda por usuario y el servidor lo pone en <html>
 * desde la primera respuesta, para que la página no parpadee al cargar.
 */

it('guarda el tema elegido en la cuenta', function (string $tema) {
    $usuario = usuarioCon(User::ROL_CALL_CENTER);

    $this->actingAs($usuario)->postJson(route('preferencias.tema'), ['tema' => $tema])->assertNoContent();

    expect($usuario->refresh()->tema)->toBe($tema);
})->with(['claro', 'oscuro', 'sistema']);

it('rechaza un tema que no existe', function () {
    $usuario = usuarioCon(User::ROL_CALL_CENTER);

    $this->actingAs($usuario)->postJson(route('preferencias.tema'), ['tema' => 'rosado'])->assertUnprocessable();
});

it('sin sesión no se guarda nada', function () {
    $this->postJson(route('preferencias.tema'), ['tema' => 'oscuro'])->assertUnauthorized();
});

it('el tema oscuro sale ya en el html, sin esperar a JavaScript', function () {
    $usuario = usuarioCon(User::ROL_CALL_CENTER);
    $usuario->forceFill(['tema' => 'oscuro'])->save();

    $this->actingAs($usuario)->get(route('soportes.index'))
        ->assertOk()
        ->assertSee('data-tema="oscuro"', false)
        ->assertSee('class="dark"', false);
});

it('con "sistema" el servidor deja que el navegador decida antes de pintar', function () {
    $this->actingAs(usuarioCon(User::ROL_CALL_CENTER))->get(route('soportes.index'))
        ->assertOk()
        ->assertSee('data-tema="sistema"', false)
        ->assertSee("matchMedia('(prefers-color-scheme: dark)')", false);
});

it('la pantalla de campo es clara por defecto aunque el escritorio esté en oscuro', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $usuario->forceFill(['tema' => 'oscuro'])->save();

    $this->actingAs($usuario)->get(route('campo.ruta'))
        ->assertOk()
        ->assertSee('data-tema="claro"', false);
});

it('el modo noche de campo se guarda aparte del de escritorio', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);

    $this->actingAs($usuario)->postJson(route('preferencias.tema-campo'), ['tema' => 'oscuro'])->assertNoContent();
    $this->actingAs($usuario)->postJson(route('preferencias.tema-campo'), ['tema' => 'sistema'])->assertUnprocessable();

    expect($usuario->refresh()->tema_campo)->toBe('oscuro')
        ->and($usuario->tema)->toBe('sistema');
});

it('no carga fuentes de terceros', function () {
    $this->get('/login')->assertOk()->assertDontSee('fonts.bunny.net')->assertDontSee('fonts.googleapis.com');
});
