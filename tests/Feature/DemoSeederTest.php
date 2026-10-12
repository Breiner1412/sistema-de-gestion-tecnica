<?php

use App\Models\Soporte;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    config(['demo.password' => 'clave-demo-de-prueba']);
    $this->seed(DatabaseSeeder::class);
});

it('los casos demo nacen con número y vencimiento de SLA', function () {
    expect(Soporte::count())->toBeGreaterThan(0)
        ->and(Soporte::whereNull('numero_soporte')->count())->toBe(0)
        ->and(Soporte::whereNull('sla_vence_at')->count())->toBe(0);
});

it('los casos sin internet de la demo son de atención inmediata', function () {
    $sinInternet = Soporte::where('tipo_solicitud', Soporte::TIPO_SIN_INTERNET)->get();

    expect($sinInternet)->not->toBeEmpty()
        ->and($sinInternet->pluck('criticidad')->unique()->all())->toBe([Soporte::CRITICIDAD_INMEDIATA]);
});

it('la demo deja casos abiertos que el semáforo puede ver', function () {
    $abiertos = Soporte::whereNotIn('estado', [
        Soporte::ESTADO_SOLUCIONADO, Soporte::ESTADO_CANCELADO, Soporte::ESTADO_CERRADO_SIN_CONTACTO,
    ])->get();

    expect($abiertos)->not->toBeEmpty()
        ->and($abiertos->map->semaforoSla()->unique()->count())->toBeGreaterThan(1);
});

it('en producción solo siembra la demo si el modo demo está activo', function (bool $demo, bool $esperaCasos) {
    Soporte::query()->forceDelete();
    config(['demo.activo' => $demo]);
    app()->detectEnvironment(fn () => 'production');

    // Directo: db:seed pediría confirmación en producción.
    app(DatabaseSeeder::class)->run();

    expect(Soporte::exists())->toBe($esperaCasos);
})->with([
    'demo pública' => [true, true],
    'producción real' => [false, false],
]);
