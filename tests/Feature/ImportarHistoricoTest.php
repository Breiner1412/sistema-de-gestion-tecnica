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

/** Escribe un par de CSV temporales con el mismo formato que los del histórico. */
function csvDePrueba(array $casos): array
{
    $carpeta = sys_get_temp_dir().'/sgt-'.uniqid();
    mkdir($carpeta);

    $clientes = $carpeta.'/clientes.csv';
    $fh = fopen($clientes, 'w');
    fputcsv($fh, ['codigo_abonado', 'cedula', 'nombre', 'telefono']);
    fputcsv($fh, ['C100001', '1090000001', 'Ana Acosta Franco', '3105000001']);
    fputcsv($fh, ['C100002', '1090000002', 'Carlos Bermúdez Nieto', '3105000002']);
    fclose($fh);

    $archivo = $carpeta.'/casos.csv';
    $fh = fopen($archivo, 'w');
    fputcsv($fh, ['codigo_abonado', 'tipo_solicitud', 'canal_ingreso', 'servicio_afectado', 'falla',
        'diagnostico', 'estado', 'fecha_ingreso', 'fecha_cierre', 'asesor_idx', 'tecnico_idx', 'escalado']);

    foreach ($casos as $caso) {
        fputcsv($fh, $caso);
    }

    fclose($fh);

    return [$archivo, $clientes];
}

function importar(array $casos): void
{
    [$archivo, $clientes] = csvDePrueba($casos);

    test()->artisan('soportes:importar-historico', [
        '--casos' => $archivo,
        '--clientes' => $clientes,
    ])->assertSuccessful();
}

it('crea los clientes que no existían', function () {
    importar([
        ['C100001', 'soporte_remoto', 'whatsapp', 'internet', 'LENTITUD E INTERMITENCIA', '',
            'solucionado', '2026-03-10 09:00:00', '2026-03-10 11:30:00', '0', '0', ''],
    ]);

    expect(Cliente::count())->toBe(2)
        ->and(Cliente::where('codigo_abonado', 'C100001')->exists())->toBeTrue();
});

it('no duplica clientes al correrlo dos veces', function () {
    $caso = ['C100001', 'soporte_remoto', 'caja', 'internet', 'LENTITUD E INTERMITENCIA', '',
        'solucionado', '2026-03-10 09:00:00', '2026-03-10 11:30:00', '0', '0', ''];

    importar([$caso]);
    importar([$caso]);

    expect(Cliente::count())->toBe(2)
        ->and(Soporte::count())->toBe(2); // los clientes se reutilizan, los casos sí se repiten
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
