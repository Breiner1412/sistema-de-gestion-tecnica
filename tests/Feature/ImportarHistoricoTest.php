<?php

use App\Models\Cliente;
use App\Models\Soporte;
use App\Models\SoporteHistorialEstado;
use App\Models\TipoFalla;
use App\Models\User;

beforeEach(function () {
    // El importador necesita equipo y catálogo previos.
    foreach (range(1, 3) as $i) {
        usuarioCon(User::ROL_TECNICO_SOPORTE);
        usuarioCon(User::ROL_CALL_CENTER);
    }

    TipoFalla::create(['nombre' => 'LENTITUD E INTERMITENCIA', 'servicio' => 'internet', 'critica' => false]);
    TipoFalla::create(['nombre' => 'SIN SEÑAL DE TV', 'servicio' => 'tv', 'critica' => true]);
});

it('crea los clientes que no existían', function () {
    importar([
        ['C100001', 'soporte_remoto', 'whatsapp', 'internet', 'LENTITUD E INTERMITENCIA', '',
            'solucionado', '2026-03-10 09:00:00', '2026-03-10 11:30:00', '0', '0', ''],
    ]);

    expect(Cliente::count())->toBe(2)
        ->and(Cliente::where('codigo_abonado', 'C100001')->exists())->toBeTrue();
});

it('correrlo dos veces no duplica ni clientes ni casos', function () {
    $caso = ['C100001', 'soporte_remoto', 'caja', 'internet', 'LENTITUD E INTERMITENCIA', '',
        'solucionado', '2026-03-10 09:00:00', '2026-03-10 11:30:00', '0', '0', ''];

    importar([$caso]);
    importar([$caso]);

    expect(Cliente::count())->toBe(2)
        ->and(Soporte::count())->toBe(1);
});

it('dos filas idénticas del histórico siguen siendo dos casos', function () {
    $caso = ['C100001', 'soporte_remoto', 'caja', 'internet', 'LENTITUD E INTERMITENCIA', '',
        'solucionado', '2026-03-10 09:00:00', '2026-03-10 11:30:00', '0', '0', ''];

    importar([$caso, $caso]);
    importar([$caso, $caso]);

    expect(Soporte::count())->toBe(2);
});

it('--fresh borra solo lo importado y conserva los casos creados en la aplicación', function () {
    $propio = soporteDePrueba(['descripcion' => 'Caso creado a mano en la aplicación.']);
    $caso = ['C100001', 'soporte_remoto', 'caja', 'internet', 'LENTITUD E INTERMITENCIA', '',
        'solucionado', '2026-03-10 09:00:00', '2026-03-10 11:30:00', '0', '0', ''];
    importar([$caso]);

    [$archivo, $clientes] = csvDePrueba([$caso]);
    $this->artisan('soportes:importar-historico', ['--casos' => $archivo, '--clientes' => $clientes, '--fresh' => true])
        ->expectsConfirmation('Esto borra los casos importados del histórico y sus visitas. ¿Continuar?', 'yes')
        ->assertSuccessful();

    expect(Soporte::find($propio->id))->not->toBeNull()
        ->and(Soporte::whereNotNull('origen_importacion')->count())->toBe(1);
});

it('si falla a mitad no deja nada a medias', function () {
    $caso = ['C100001', 'soporte_remoto', 'caja', 'internet', 'LENTITUD E INTERMITENCIA', '',
        'solucionado', '2026-03-10 09:00:00', '2026-03-10 11:30:00', '0', '0', ''];
    User::query()->where('rol', User::ROL_TECNICO_SOPORTE)->delete(); // sin equipo, los casos fallan

    [$archivo, $clientes] = csvDePrueba([$caso]);
    $this->artisan('soportes:importar-historico', ['--casos' => $archivo, '--clientes' => $clientes])
        ->assertFailed();

    // Los clientes se importan antes que los casos: tampoco deben quedar.
    expect(Cliente::count())->toBe(0)
        ->and(Soporte::count())->toBe(0);
});

it('numera los casos de forma consecutiva y sin chocar con los existentes', function () {
    importar([
        ['C100001', 'soporte_remoto', 'caja', 'internet', 'LENTITUD E INTERMITENCIA', '',
            'solucionado', '2026-03-10 09:00:00', '2026-03-10 11:30:00', '0', '0', ''],
        ['C100002', 'soporte_remoto', 'caja', 'internet', 'LENTITUD E INTERMITENCIA', '',
            'solucionado', '2026-03-11 09:00:00', '2026-03-11 10:00:00', '1', '1', ''],
    ]);

    $numeros = Soporte::orderBy('id')->pluck('numero_soporte')->all();

    expect($numeros)->toBe(['SGT-2026-000001', 'SGT-2026-000002'])
        ->and($numeros)->toHaveCount(count(array_unique($numeros)));
});

it('deriva criticidad inmediata igual que el modelo', function () {
    importar([
        ['C100001', 'sin_internet', 'caja', 'internet', '', '',
            'solucionado', '2026-03-10 09:00:00', '2026-03-10 10:00:00', '0', '0', ''],
        ['C100002', 'soporte_remoto', 'caja', 'tv', 'SIN SEÑAL DE TV', '',
            'solucionado', '2026-03-10 09:00:00', '2026-03-10 10:00:00', '0', '0', ''],
        ['C100001', 'soporte_remoto', 'caja', 'internet', 'LENTITUD E INTERMITENCIA', '',
            'solucionado', '2026-03-10 09:00:00', '2026-03-10 10:00:00', '0', '0', ''],
    ]);

    $criticidades = Soporte::orderBy('id')->pluck('criticidad')->all();

    expect($criticidades)->toBe(['inmediata', 'inmediata', 'normal']);
});

it('calcula el tiempo de resolución en minutos hábiles y deja vacío el de respuesta', function () {
    importar([
        ['C100001', 'soporte_remoto', 'caja', 'internet', 'LENTITUD E INTERMITENCIA', '',
            'solucionado', '2026-03-10 09:00:00', '2026-03-10 11:30:00', '0', '0', ''],
    ]);

    $caso = Soporte::first();

    expect($caso->tiempo_resolucion)->toBe(150)
        ->and($caso->tiempo_respuesta)->toBeNull()
        ->and($caso->fecha_cierre)->not->toBeNull();
});

it('deja con reloj vivo los casos que llegaron abiertos', function () {
    importar([
        ['C100001', 'soporte_remoto', 'caja', 'internet', 'LENTITUD E INTERMITENCIA', '',
            'pendiente', '2026-03-10 09:00:00', '', '0', '0', ''],
    ]);

    $caso = Soporte::first();

    expect($caso->estado)->toBe(Soporte::ESTADO_PENDIENTE)
        ->and($caso->sla_vence_at)->not->toBeNull()
        ->and($caso->fecha_cierre)->toBeNull();
});

it('omite las filas con fecha futura o cliente desconocido', function () {
    importar([
        ['C999999', 'soporte_remoto', 'caja', 'internet', '', '',
            'solucionado', '2026-03-10 09:00:00', '2026-03-10 10:00:00', '0', '0', ''],
        ['C100001', 'soporte_remoto', 'caja', 'internet', '', '',
            'solucionado', now()->addMonth()->format('Y-m-d H:i:s'), '', '0', '0', ''],
    ]);

    expect(Soporte::count())->toBe(0);
});

it('marca el historial del caso importado como automático', function () {
    importar([
        ['C100001', 'soporte_remoto', 'caja', 'internet', 'LENTITUD E INTERMITENCIA', '',
            'solucionado', '2026-03-10 09:00:00', '2026-03-10 11:00:00', '0', '0', ''],
    ]);

    $registro = SoporteHistorialEstado::first();

    expect($registro->automatico)->toBeTrue()
        ->and($registro->usuario_id)->toBeNull()
        ->and($registro->estado_nuevo)->toBe(Soporte::ESTADO_SOLUCIONADO);
});

it('respeta el límite cuando se le pide', function () {
    [$archivo, $clientes] = csvDePrueba([
        ['C100001', 'soporte_remoto', 'caja', 'internet', '', '', 'solucionado', '2026-03-10 09:00:00', '2026-03-10 10:00:00', '0', '0', ''],
        ['C100002', 'soporte_remoto', 'caja', 'internet', '', '', 'solucionado', '2026-03-11 09:00:00', '2026-03-11 10:00:00', '0', '0', ''],
        ['C100001', 'soporte_remoto', 'caja', 'internet', '', '', 'solucionado', '2026-03-12 09:00:00', '2026-03-12 10:00:00', '0', '0', ''],
    ]);

    $this->artisan('soportes:importar-historico', [
        '--casos' => $archivo,
        '--clientes' => $clientes,
        '--limit' => 2,
    ])->assertSuccessful();

    expect(Soporte::count())->toBe(2);
});

it('los casos abiertos del histórico no disparan alertas de SLA', function () {
    \Illuminate\Support\Facades\Notification::fake();
    config(['sla.alertas.activas' => true]);
    usuarioCon(User::ROL_ADMIN);

    importar([
        ['C100001', 'sin_internet', 'caja', 'internet', '', '',
            'pendiente', '2026-03-10 09:00:00', '', '0', '0', ''],
        ['C100002', 'soporte_remoto', 'caja', 'internet', 'LENTITUD E INTERMITENCIA', '',
            'seguimiento', '2026-03-11 09:00:00', '', '0', '1', ''],
    ]);

    expect(Soporte::where('alerta_sla_nivel', 'vencido')->count())->toBe(2);

    $this->artisan('soportes:alertar-sla')->assertSuccessful();

    \Illuminate\Support\Facades\Notification::assertNothingSent();
});
