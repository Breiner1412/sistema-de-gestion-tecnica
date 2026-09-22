<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga de datos en CSV, transmitida fila por fila.
 *
 * No carga el resultado completo en memoria: quien llama pasa un generador y
 * las filas se escriben a medida que salen de la base. Con 5.000 casos eso es
 * la diferencia entre una descarga inmediata y agotar la memoria de PHP.
 *
 * Dos detalles pensados para Excel en español:
 *  - separador punto y coma, que es lo que espera la configuración regional
 *    de Colombia (con coma, Excel mete toda la fila en una sola celda);
 *  - BOM UTF-8 al inicio, sin el cual las tildes y las eñes salen rotas.
 */
class ExportadorCsv
{
    /**
     * @param  array<int, string>  $encabezados
     * @param  iterable<int, array<int, mixed>>  $filas
     */
    public static function descargar(string $nombre, array $encabezados, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($encabezados, $filas) {
            $salida = fopen('php://output', 'w');

            fwrite($salida, "\xEF\xBB\xBF");
            self::escribir($salida, $encabezados);

            foreach ($filas as $fila) {
                self::escribir($salida, $fila);
            }

            fclose($salida);
        }, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /** Nombre con marca de tiempo, para que dos descargas no se pisen. */
    public static function nombre(string $base): string
    {
        return $base.'-'.now()->format('Y-m-d-His').'.csv';
    }

    /** @param array<int, mixed> $fila */
    private static function escribir($salida, array $fila): void
    {
        // El escape vacío es explícito: PHP 8.4 deprecó el valor por defecto.
        fputcsv($salida, array_map(self::normalizar(...), $fila), ';', '"', '');
    }

    private static function normalizar(mixed $valor): string
    {
        return match (true) {
            $valor === null => '',
            is_bool($valor) => $valor ? 'sí' : 'no',
            $valor instanceof \DateTimeInterface => $valor->format('Y-m-d H:i'),
            default => (string) $valor,
        };
    }
}
