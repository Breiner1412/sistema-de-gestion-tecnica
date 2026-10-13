<?php

use App\Models\Soporte;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/*
 * Va en Unit, con la base armada a mano, por lo mismo que el reinicio de la
 * demo: migrate y migrate:fresh no corren dentro de la transacción de
 * RefreshDatabase en SQLite.
 */
uses(TestCase::class);

beforeEach(fn () => Artisan::call('db:wipe'));

it('en el primer arranque de la demo carga la demo completa', function () {
    config(['demo.activo' => true, 'demo.password' => 'clave-demo']);

    $this->artisan('sistema:preparar')->assertSuccessful();

    expect(User::count())->toBeGreaterThan(0)
        ->and(Soporte::whereNotNull('origen_importacion')->count())->toBeGreaterThan(4000);
});

it('en una producción real siembra solo el administrador y los catálogos', function () {
    config(['demo.activo' => false, 'cuentas.admin.email' => 'jefe@ejemplo.test', 'cuentas.admin.password' => 'una-clave-larga']);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('sistema:preparar')->assertSuccessful();

    expect(User::where('email', 'jefe@ejemplo.test')->exists())->toBeTrue()
        ->and(Soporte::count())->toBe(0);
});

it('si ya hay datos solo migra: reiniciar el contenedor no borra ni duplica', function () {
    config(['demo.activo' => true, 'demo.password' => 'clave-demo']);
    $this->artisan('sistema:preparar')->assertSuccessful();
    $casos = Soporte::count();

    $this->artisan('sistema:preparar')->assertSuccessful();

    expect(Soporte::count())->toBe($casos);
});
