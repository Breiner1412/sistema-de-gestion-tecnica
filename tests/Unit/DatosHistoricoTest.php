<?php

/*
 * El histórico es público. Estas pruebas no confían en que se revisó a mano:
 * verifican sobre el archivo que nada en él pueda ser un dato real.
 */

function filasCsv(string $archivo): array
{
    $ruta = dirname(__DIR__, 2)."/database/data/{$archivo}";
    $lineas = array_map('str_getcsv', file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $encabezado = array_shift($lineas);

    return array_map(fn ($fila) => array_combine($encabezado, $fila), $lineas);
}

it('los códigos de abonado llevan un prefijo de zona corto, no un nombre', function () {
    foreach (['historico_clientes.csv', 'historico_casos.csv'] as $archivo) {
        $raros = collect(filasCsv($archivo))
            ->pluck('codigo_abonado')
            ->reject(fn ($codigo) => preg_match('/^[A-Z]{1,3}1\d{5}$/', $codigo))
            ->unique()
            ->values();

        expect($raros->all())->toBe([], "Códigos sospechosos en {$archivo}");
    }
});

it('las cédulas y los teléfonos del histórico son los sintéticos', function () {
    $raros = collect(filasCsv('historico_clientes.csv'))->reject(
        fn ($fila) => preg_match('/^109000\d{4}$/', $fila['cedula'])
            && preg_match('/^3105\d{6}$/', $fila['telefono'])
    );

    expect($raros->count())->toBe(0);
});
