<?php

use Illuminate\Support\Facades\File;

/**
 * Lo que se prueba aquí es exactamente lo que falló la primera vez: que el
 * conjunto de nombres alcance. Un generador que produce 144 nombres para 4.114
 * personas pasa cualquier prueba que solo mire una fila.
 */

/** Escribe un CSV de clientes de prueba y devuelve su ruta. */
function csvDeClientes(int $cuantos): string
{
    $ruta = base_path('storage/framework/testing/clientes-'.uniqid().'.csv');

    File::ensureDirectoryExists(dirname($ruta));

    $lineas = ['codigo_abonado,cedula,nombre,telefono'];

    foreach (range(1, $cuantos) as $i) {
        $lineas[] = sprintf('TCF%06d,10900%05d,Nombre Repetido Siempre,31050%05d', $i, $i, $i);
    }

    File::put($ruta, implode("\n", $lineas)."\n");

    return $ruta;
}

/** @return array<int, array<string, string>> */
function leerCsv(string $ruta): array
{
    $lineas = array_filter(explode("\n", trim(File::get($ruta))));
    $encabezados = str_getcsv(array_shift($lineas), escape: '');

    return array_map(fn($l) => array_combine($encabezados, str_getcsv($l, escape: '')), $lineas);
}

afterEach(function () {
    File::cleanDirectory(base_path('storage/framework/testing'));
});

it('no repite ningún nombre en una base grande', function () {
    $ruta = csvDeClientes(4200);

    $this->artisan('datos:seudonimizar-nombres', ['--archivo' => $ruta])->assertSuccessful();

    $nombres = array_column(leerCsv($ruta), 'nombre');

    expect($nombres)->toHaveCount(4200)
        ->and(array_unique($nombres))->toHaveCount(4200);
});

it('da siempre el mismo nombre al mismo abonado', function () {
    $primera = csvDeClientes(50);
    $segunda = csvDeClientes(50);

    $this->artisan('datos:seudonimizar-nombres', ['--archivo' => $primera])->assertSuccessful();
    $this->artisan('datos:seudonimizar-nombres', ['--archivo' => $segunda])->assertSuccessful();

    expect(array_column(leerCsv($primera), 'nombre'))
        ->toBe(array_column(leerCsv($segunda), 'nombre'));
});

it('no toca la cédula, el teléfono ni el código', function () {
    $ruta = csvDeClientes(20);

    $antes = leerCsv($ruta);

    $this->artisan('datos:seudonimizar-nombres', ['--archivo' => $ruta])->assertSuccessful();

    $despues = leerCsv($ruta);

    foreach ($antes as $i => $fila) {
        expect($despues[$i]['codigo_abonado'])->toBe($fila['codigo_abonado'])
            ->and($despues[$i]['cedula'])->toBe($fila['cedula'])
            ->and($despues[$i]['telefono'])->toBe($fila['telefono']);
    }
});

it('escribe nombre y dos apellidos distintos entre sí', function () {
    $ruta = csvDeClientes(300);

    $this->artisan('datos:seudonimizar-nombres', ['--archivo' => $ruta])->assertSuccessful();

    foreach (leerCsv($ruta) as $fila) {
        $partes = explode(' ', $fila['nombre']);

        expect($partes)->toHaveCount(3)
            ->and($partes[1])->not->toBe($partes[2]);
    }
});

it('con --dry-run no escribe el archivo', function () {
    $ruta = csvDeClientes(10);

    $antes = File::get($ruta);

    $this->artisan('datos:seudonimizar-nombres', ['--archivo' => $ruta, '--dry-run' => true])
        ->assertSuccessful();

    expect(File::get($ruta))->toBe($antes);
});

it('avisa si el archivo no existe', function () {
    $this->artisan('datos:seudonimizar-nombres', ['--archivo' => 'no/existe.csv'])
        ->assertFailed();
});
