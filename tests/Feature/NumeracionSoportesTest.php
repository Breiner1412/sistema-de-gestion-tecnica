<?php

use App\Models\Soporte;
use App\Models\TipoFalla;
use App\Models\User;

it('dos altas que piden número a la vez reciben números distintos', function () {
    // Antes era max()+1 sin bloqueo: las dos lecturas veían el mismo máximo.
    $primero = Soporte::siguienteNumero();
    $segundo = Soporte::siguienteNumero();

    expect($primero)->not->toBe($segundo)
        ->and((int) substr($segundo, -6))->toBe((int) substr($primero, -6) + 1);
});

it('el contador arranca donde va la tabla', function () {
    soporteDePrueba(['numero_soporte' => sprintf('SGT-%d-%06d', now()->year, 41)]);

    expect(Soporte::siguienteNumero())->toBe(sprintf('SGT-%d-%06d', now()->year, 42));
});

it('tras importar el histórico, la siguiente alta no choca con lo importado', function () {
    soporteDePrueba(); // el contador del año ya existe
    foreach (range(1, 2) as $i) {
        usuarioCon(User::ROL_TECNICO_SOPORTE);
        usuarioCon(User::ROL_CALL_CENTER);
    }
    TipoFalla::create(['nombre' => 'LENTITUD E INTERMITENCIA', 'servicio' => 'internet', 'critica' => false]);

    $dia = now()->subDays(3)->format('Y-m-d').' 09:00:00';
    importar([
        ['C100001', 'soporte_remoto', 'caja', 'internet', 'LENTITUD E INTERMITENCIA', '', 'solucionado', $dia, $dia, '0', '0', ''],
        ['C100002', 'soporte_remoto', 'caja', 'internet', 'LENTITUD E INTERMITENCIA', '', 'solucionado', $dia, $dia, '0', '0', ''],
    ]);

    $nuevo = soporteDePrueba();

    expect(Soporte::where('numero_soporte', $nuevo->numero_soporte)->count())->toBe(1)
        ->and($nuevo->numero_soporte)->toBe(sprintf('SGT-%d-%06d', now()->year, 4));
});
