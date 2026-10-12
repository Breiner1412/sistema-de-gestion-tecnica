<?php

use App\Models\Cliente;
use App\Models\Soporte;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
 * Va aparte de tests/Feature porque allí cada prueba corre dentro de la
 * transacción de RefreshDatabase, y migrate:fresh no puede ejecutarse dentro
 * de una transacción en SQLite. Aquí la base se arma a mano.
 */
uses(TestCase::class);

it('en modo demo borra lo de los visitantes y vuelve a sembrar', function () {
    Artisan::call('migrate:fresh');
    config(['demo.activo' => true, 'demo.password' => 'clave-demo-de-prueba']);
    Storage::fake('public');
    Storage::fake('local');
    Storage::disk('public')->put('firmas/de-un-visitante.png', 'x');
    Storage::disk('local')->put('evidencias/9/foto.jpg', 'x');

    $ajeno = Cliente::create([
        'codigo_abonado' => 'ZZ999999', 'cedula' => '1', 'nombre' => 'Lo dejó un visitante', 'estado' => 'activo',
    ]);

    $this->artisan('demo:reiniciar', ['--sin-historico' => true])->assertSuccessful();

    expect(Cliente::where('codigo_abonado', $ajeno->codigo_abonado)->exists())->toBeFalse()
        ->and(Soporte::count())->toBeGreaterThan(0)
        ->and(Storage::disk('public')->exists('firmas/de-un-visitante.png'))->toBeFalse()
        ->and(Storage::disk('local')->exists('evidencias/9/foto.jpg'))->toBeFalse();
});
