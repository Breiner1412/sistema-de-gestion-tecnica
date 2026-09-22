<?php

use App\Support\ExportadorCsv;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Captura lo que la respuesta escribe en la salida. */
function contenidoDe(StreamedResponse $respuesta): string
{
    ob_start();
    $respuesta->sendContent();

    return ob_get_clean();
}

/**
 * Devuelve el CSV ya interpretado. Comparar contra el texto crudo es frágil:
 * fputcsv entrecomilla cualquier campo con espacios, así que "Marta Ospina"
 * sale con comillas aunque el archivo esté perfecto.
 *
 * @return array<int, array<int, string>>
 */
function filasDe(StreamedResponse $respuesta): array
{
    $contenido = ltrim(contenidoDe($respuesta), "\xEF\xBB\xBF");

    return array_map(
        fn($linea) => str_getcsv($linea, ';', '"', ''),
        array_filter(explode("\n", trim($contenido))),
    );
}

it('entrega el CSV como descarga con nombre y tipo correctos', function () {
    $respuesta = ExportadorCsv::descargar('casos.csv', ['A', 'B'], [['1', '2']]);

    expect($respuesta)->toBeInstanceOf(StreamedResponse::class)
        ->and($respuesta->headers->get('Content-Type'))->toContain('text/csv')
        ->and($respuesta->headers->get('Content-Disposition'))->toContain('casos.csv');
});

it('abre con BOM para que Excel respete las tildes', function () {
    $contenido = contenidoDe(ExportadorCsv::descargar('x.csv', ['Código'], [['Señal']]));

    expect($contenido)->toStartWith("\xEF\xBB\xBF")
        ->and($contenido)->toContain('Código')
        ->and($contenido)->toContain('Señal');
});

it('separa con punto y coma, no con coma', function () {
    $contenido = ltrim(contenidoDe(
        ExportadorCsv::descargar('x.csv', ['uno', 'dos'], [['a', 'b']])
    ), "\xEF\xBB\xBF");

    expect($contenido)->toContain('uno;dos')
        ->and($contenido)->not->toContain('uno,dos');
});

it('escribe encabezados y filas en su sitio', function () {
    $filas = filasDe(ExportadorCsv::descargar('x.csv', ['Código', 'Cliente'], [
        ['TCF004901', 'Marta Ospina'],
    ]));

    expect($filas[0])->toBe(['Código', 'Cliente'])
        ->and($filas[1])->toBe(['TCF004901', 'Marta Ospina']);
});

it('normaliza nulos, booleanos y fechas', function () {
    $filas = filasDe(ExportadorCsv::descargar('x.csv', ['a', 'b', 'c', 'd'], [
        [null, true, false, new DateTimeImmutable('2026-03-10 09:05:00')],
    ]));

    expect($filas[1])->toBe(['', 'sí', 'no', '2026-03-10 09:05']);
});

it('acepta un generador sin cargarlo entero en memoria', function () {
    $filas = (function () {
        foreach (range(1, 3) as $i) {
            yield ["fila{$i}"];
        }
    })();

    $escritas = filasDe(ExportadorCsv::descargar('x.csv', ['col'], $filas));

    expect($escritas)->toHaveCount(4) // encabezado + 3
        ->and($escritas[1])->toBe(['fila1'])
        ->and($escritas[3])->toBe(['fila3']);
});

it('pone marca de tiempo en el nombre para no pisar descargas', function () {
    expect(ExportadorCsv::nombre('soportes'))
        ->toStartWith('soportes-')
        ->toEndWith('.csv');
});

describe('mensajes en español', function () {
    it('traduce las reglas de validación', function () {
        app()->setLocale('es');

        expect(trans('validation.required', ['attribute' => 'descripción']))
            ->toBe('El campo descripción es obligatorio.');
    });

    it('usa el nombre legible del campo en vez del de la columna', function () {
        app()->setLocale('es');

        expect(trans('validation.attributes.codigo_abonado'))->toBe('código de abonado')
            ->and(trans('validation.attributes.tecnico_soporte_id'))->toBe('técnico de soporte');
    });

    it('traduce el mensaje de credenciales incorrectas', function () {
        app()->setLocale('es');

        expect(trans('auth.failed'))->toBe('El correo o la contraseña no son correctos.');
    });
});
