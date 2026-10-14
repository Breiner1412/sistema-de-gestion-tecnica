<?php

use App\Models\InformeMensual;
use App\Models\User;
use Livewire\Volt\Volt;

/*
 * El menú le muestra "Informes" al técnico de soporte. Antes esa pantalla solo
 * la abría la gestión y al técnico le salía "No tienes permiso": ahora ve ahí
 * sus informes ya entregados, y solo los suyos.
 */

function informeDe(User $tecnico, int $anio, int $mes, bool $publicado = true): InformeMensual
{
    $informe = InformeMensual::create(['tecnico_id' => $tecnico->id, 'anio' => $anio, 'mes' => $mes]);

    if ($publicado) {
        $informe->publicar();
    }

    return $informe;
}

it('el técnico de soporte abre Informes y ve sus informes publicados', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);
    $informe = informeDe($tecnico, 2026, 8);

    $this->actingAs($tecnico)->get(route('informes.index'))
        ->assertOk()
        ->assertSee('Mis informes')
        ->assertSee('Agosto de 2026')
        ->assertSee(route('informes.show', $informe), false);
});

it('el técnico no ve los informes de otros ni los borradores', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);
    $otro = usuarioCon(User::ROL_TECNICO_SOPORTE);
    $ajeno = informeDe($otro, 2026, 8);
    $borrador = informeDe($tecnico, 2026, 9, publicado: false);

    $this->actingAs($tecnico)->get(route('informes.index'))
        ->assertOk()
        ->assertDontSee(route('informes.show', $ajeno), false)
        ->assertDontSee(route('informes.show', $borrador), false)
        ->assertSee('Todavía no tienes informes publicados');
});

it('el técnico no abre su informe por enlace directo mientras sea borrador', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);
    $borrador = informeDe($tecnico, 2026, 9, publicado: false);

    $this->actingAs($tecnico)->get(route('informes.show', $borrador))->assertForbidden();

    $borrador->publicar();

    $this->actingAs($tecnico)->get(route('informes.show', $borrador))->assertOk();
});

it('la gestión sí abre el borrador para escribirlo', function () {
    $borrador = informeDe(usuarioCon(User::ROL_TECNICO_SOPORTE), 2026, 9, publicado: false);

    $this->actingAs(usuarioCon(User::ROL_GERENTE))->get(route('informes.show', $borrador))->assertOk();
});

it('el técnico no puede generar informes desde esa pantalla', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);
    $this->actingAs($tecnico);

    Volt::test('informes.index')->call('abrir', $tecnico->id)->assertForbidden();

    expect(InformeMensual::count())->toBe(0);
});

it('la gestión sigue viendo la carga de todo el equipo', function () {
    soporteDePrueba();
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);

    $this->actingAs(usuarioCon(User::ROL_GERENTE))->get(route('informes.index'))
        ->assertOk()
        ->assertSee('Informes de rendimiento')
        ->assertSee($tecnico->name);
});

it('otros roles siguen sin acceso a los informes', function (string $rol) {
    $this->actingAs(usuarioCon($rol))->get(route('informes.index'))->assertForbidden();
})->with([User::ROL_CALL_CENTER, User::ROL_INGENIERO_REDES, User::ROL_TECNICO_CAMPO]);
