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

it('entrega el CSV como descarga con nombre y tipo correctos', function () {
    $respuesta = ExportadorCsv::descargar('casos.csv', ['A', 'B'], [['1', '2']]);

    expect($respuesta)->toBeInstanceOf(StreamedResponse::class)
        ->and($respuesta->headers->get('Content-Type'))->toContain('text/csv')
        ->and($respuesta->headers->get('Content-Disposition'))->toContain('casos.csv');
});

it('abre con BOM y separa con punto y coma para que Excel lo lea bien', function () {
    $contenido = contenidoDe(ExportadorCsv::descargar('x.csv', ['Código', 'Cliente'], [
        ['TCF004901', 'Marta Ospina'],
    ]));

    expect($contenido)->toStartWith("\xEF\xBB\xBF")
        ->and($contenido)->toContain('Código;Cliente')
        ->and($contenido)->toContain('TCF004901;Marta Ospina');
});

it('normaliza nulos, booleanos y fechas', function () {
    $contenido = contenidoDe(ExportadorCsv::descargar('x.csv', ['a', 'b', 'c', 'd'], [
        [null, true, false, new DateTimeImmutable('2026-03-10 09:05:00')],
    ]));

    expect($contenido)->toContain(';sí;no;2026-03-10 09:05');
});

it('acepta un generador sin cargarlo entero en memoria', function () {
    $filas = (function () {
        foreach (range(1, 3) as $i) {
            yield ["fila{$i}"];
        }
    })();

    $contenido = contenidoDe(ExportadorCsv::descargar('x.csv', ['col'], $filas));

    expect($contenido)->toContain('fila1')
        ->and($contenido)->toContain('fila3');
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
