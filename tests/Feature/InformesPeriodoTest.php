<?php

use App\Models\User;
use Livewire\Volt\Volt;

it('el listado de informes abre también sobre SQLite (sin YEAR() de MySQL)', function () {
    soporteDePrueba();
    $this->actingAs(usuarioCon(User::ROL_GERENTE));

    $this->get(route('informes.index'))->assertOk();
});

it('un mes o un año inválido en la URL vuelve al mes pasado', function (string $anio, string $mes) {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));
    $anterior = now()->subMonth();

    Volt::withQueryParams(['anio' => $anio, 'mes' => $mes])
        ->test('informes.index')
        ->assertSet('anio', (string) $anterior->year)
        ->assertSet('mes', (string) $anterior->month);
})->with([
    'mes 13' => [(string) now()->year, '13'],
    'mes 0' => [(string) now()->year, '0'],
    'año con letras' => ['abc', '3'],
    'año sin casos' => ['1999', '3'],
]);

it('no se abre un informe para alguien que no es técnico de soporte', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));

    Volt::test('informes.index')->call('abrir', usuarioCon(User::ROL_CALL_CENTER)->id)->assertNotFound();
});

it('sí se abre el informe de un técnico de soporte', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);

    Volt::test('informes.index')->call('abrir', $tecnico->id)->assertRedirect();
});
