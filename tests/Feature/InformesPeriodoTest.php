<?php

use App\Models\User;
use Livewire\Volt\Volt;

it('el listado de informes abre también sobre SQLite (sin YEAR() de MySQL)', function () {
    soporteDePrueba();
    $this->actingAs(usuarioCon(User::ROL_GERENTE));

    $this->get(route('informes.index'))->assertOk();
});

// Es lo que hace Livewire con un #[Url] manipulado: asignar la propiedad.
it('un mes inválido en la URL vuelve al mes pasado', function (string $mes) {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));

    Volt::test('informes.index')->set('mes', $mes)->assertSet('mes', (string) now()->subMonth()->month);
})->with(['13', '0', 'abc', '-1']);

it('un año inválido en la URL vuelve al del mes pasado', function (string $anio) {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));

    Volt::test('informes.index')->set('anio', $anio)->assertSet('anio', (string) now()->subMonth()->year);
})->with(['abc', '1999', '99999']);

it('un periodo válido se respeta', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));

    Volt::test('informes.index')->set('mes', '3')->assertSet('mes', '3');
});

it('no se abre un informe para alguien que no es técnico de soporte', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));

    Volt::test('informes.index')->call('abrir', usuarioCon(User::ROL_CALL_CENTER)->id)->assertNotFound();
});

it('sí se abre el informe de un técnico de soporte', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);

    Volt::test('informes.index')->call('abrir', $tecnico->id)->assertRedirect();
});
