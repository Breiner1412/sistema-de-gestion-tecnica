<?php

use App\Models\User;

/*
 * El service worker es JavaScript del navegador; lo que se prueba aquí es lo
 * que el servidor le entrega y las reglas escritas en el archivo.
 */

it('la página de entrada borra las copias de campo al quedar sin sesión', function () {
    $this->get('/login')->assertOk()->assertSee("startsWith('sgt-campo')", false);
});

it('con la sesión abierta no se borran las copias', function () {
    $this->actingAs(usuarioCon(User::ROL_TECNICO_CAMPO))
        ->get('/')
        ->assertOk()
        ->assertDontSee("startsWith('sgt-campo')", false);
});

it('el service worker se registra solo sobre /campo', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);

    $this->actingAs($usuario)
        ->get(route('campo.ruta'))
        ->assertOk()
        ->assertSee('{ scope: alcance }', false);
});

it('el service worker solo guarda páginas de campo y nunca las fotos privadas', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("const CACHE = 'sgt-campo-v2'")
        ->and($sw)->toContain('respuesta.redirected')
        ->and($sw)->toContain('if (!esDeCampo(url))')
        ->and($sw)->toContain('/^\/(build|iconos)\//');
});
