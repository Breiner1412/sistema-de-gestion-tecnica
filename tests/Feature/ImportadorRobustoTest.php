<?php

use App\Models\Cliente;
use App\Models\Soporte;
use App\Models\TipoFalla;
use App\Models\User;

/*
 * El histórico sale de un Excel: puede llegar con BOM, en Latin-1, con filas
 * vacías, con columnas de más o con fechas imposibles. Nada de eso debe
 * tumbar la importación entera ni colarse a la base a medias.
 */

beforeEach(function () {
    foreach (range(1, 2) as $i) {
        usuarioCon(User::ROL_TECNICO_SOPORTE);
        usuarioCon(User::ROL_CALL_CENTER);
    }

    TipoFalla::create(['nombre' => 'LENTITUD E INTERMITENCIA', 'servicio' => 'internet', 'critica' => false]);
});

const ENCABEZADO_CASOS = 'codigo_abonado,tipo_solicitud,canal_ingreso,servicio_afectado,falla,diagnostico,estado,fecha_ingreso,fecha_cierre,asesor_idx,tecnico_idx,escalado';

/** Escribe los dos CSV tal cual, byte por byte, y corre el importador. */
function importarCrudo(string $clientes, string $casos)
{
    $carpeta = sys_get_temp_dir().'/sgt-crudo-'.uniqid();
    mkdir($carpeta);
    file_put_contents("{$carpeta}/clientes.csv", $clientes);
    file_put_contents("{$carpeta}/casos.csv", $casos);

    return test()->artisan('soportes:importar-historico', [
        '--casos' => "{$carpeta}/casos.csv",
        '--clientes' => "{$carpeta}/clientes.csv",
    ]);
}

function filaCaso(string $abonado, string $extra = ''): string
{
    return $extra ?: "{$abonado},soporte_remoto,caja,internet,LENTITUD E INTERMITENCIA,,solucionado,2026-03-10 09:00:00,2026-03-10 11:30:00,0,0,";
}

it('lee un CSV con BOM de Excel', function () {
    importarCrudo(
        "\xEF\xBB\xBFcodigo_abonado,cedula,nombre,telefono\nC100001,1090000001,Ana Acosta,3105000001\n",
        "\xEF\xBB\xBF".ENCABEZADO_CASOS."\n".filaCaso('C100001')."\n",
    )->assertSuccessful();

    expect(Cliente::where('codigo_abonado', 'C100001')->exists())->toBeTrue()
        ->and(Soporte::count())->toBe(1);
});

it('convierte a UTF-8 un CSV guardado en Latin-1', function () {
    $latin1 = mb_convert_encoding("codigo_abonado,cedula,nombre,telefono\nC100001,1090000001,Begoña Muñoz Peláez,3105000001\n", 'Windows-1252', 'UTF-8');

    importarCrudo($latin1, ENCABEZADO_CASOS."\n".filaCaso('C100001')."\n")->assertSuccessful();

    expect(Cliente::firstWhere('codigo_abonado', 'C100001')->nombre)->toBe('Begoña Muñoz Peláez');
});

it('salta filas vacías y cuenta las que traen columnas de más o de menos', function () {
    importarCrudo(
        "codigo_abonado,cedula,nombre,telefono\nC100001,1090000001,Ana Acosta,3105000001\n\n",
        ENCABEZADO_CASOS."\n".filaCaso('C100001')."\n\nC100001,soporte_remoto,caja\n".filaCaso('C100001')."\n",
    )->expectsOutputToContain('1 fila(s) con columnas de más o de menos')
        ->assertSuccessful();

    expect(Soporte::count())->toBe(2);
});

it('omite una fecha imposible en vez de abortar todo', function () {
    importarCrudo(
        "codigo_abonado,cedula,nombre,telefono\nC100001,1090000001,Ana Acosta,3105000001\n",
        ENCABEZADO_CASOS."\n"
            .filaCaso('C100001', 'C100001,soporte_remoto,caja,internet,,,solucionado,2026-02-31 25:61:00,,0,0,')."\n"
            .filaCaso('C100001')."\n",
    )->assertSuccessful();

    expect(Soporte::count())->toBe(1);
});

it('omite estados, tipos o canales que no existen en el sistema', function (string $fila) {
    importarCrudo(
        "codigo_abonado,cedula,nombre,telefono\nC100001,1090000001,Ana Acosta,3105000001\n",
        ENCABEZADO_CASOS."\n{$fila}\n".filaCaso('C100001')."\n",
    )->assertSuccessful();

    expect(Soporte::count())->toBe(1);
})->with([
    'estado' => 'C100001,soporte_remoto,caja,internet,,,borrado,2026-03-10 09:00:00,,0,0,',
    'tipo' => 'C100001,hackeo,caja,internet,,,solucionado,2026-03-10 09:00:00,,0,0,',
    'canal' => 'C100001,soporte_remoto,telegrama,internet,,,solucionado,2026-03-10 09:00:00,,0,0,',
    'índice negativo' => 'C100001,soporte_remoto,caja,internet,,,solucionado,2026-03-10 09:00:00,,-1,0,',
]);

it('rechaza un CSV al que le faltan columnas, sin importar nada', function () {
    importarCrudo(
        "codigo_abonado,nombre\nC100001,Ana Acosta\n",
        ENCABEZADO_CASOS."\n".filaCaso('C100001')."\n",
    )->expectsOutputToContain('Faltan columnas')
        ->assertFailed();

    expect(Cliente::count())->toBe(0)->and(Soporte::count())->toBe(0);
});

it('omite clientes sin cédula o con un nombre absurdamente largo', function () {
    importarCrudo(
        "codigo_abonado,cedula,nombre,telefono\nC100001,,Sin Cedula,3105000001\nC100002,1090000002,".str_repeat('x', 400).",3105000002\nC100003,1090000003,Ana Acosta,3105000003\n",
        ENCABEZADO_CASOS."\n".filaCaso('C100003')."\n",
    )->assertSuccessful();

    expect(Cliente::pluck('codigo_abonado')->all())->toBe(['C100003']);
});
