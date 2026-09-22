<?php

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Qué hizo contra la base cada petición.
 *
 * Existe por una pantalla concreta: la ficha del abonado tardaba entre cinco y
 * ocho segundos cuando el resto del sistema responde en medio. Se podía adivinar
 * —"seguro es un N+1", "seguro falta un índice"— y perder una tarde arreglando
 * lo que no era. Esto responde la pregunta con datos.
 *
 * Deja una línea por petición con lo único que hace falta para saber por dónde
 * ir: cuántas consultas, cuánto tiempo se fue en la base y cuánto en total.
 * Esas tres cifras separan los tres casos que se confunden entre sí:
 *
 *  - muchas consultas y poco tiempo en cada una → un N+1;
 *  - pocas consultas y una de ellas lenta → falta un índice o sobra un JOIN;
 *  - poco tiempo en la base y mucho en total → no es la base, es PHP o la vista.
 *
 * Se enciende con PERFIL_CONSULTAS=true y está apagado por defecto: escuchar
 * todas las consultas de una petición tiene su costo, y en producción sobra.
 */
class PerfilDeConsultas
{
    /** @var array<int, array{sql: string, ms: float}> */
    private array $consultas = [];

    private float $enLaBase = 0.0;

    public static function escuchar(): void
    {
        if (! config('depuracion.perfil_consultas')) {
            return;
        }

        (new self)->registrar();
    }

    private function registrar(): void
    {
        DB::listen(function (QueryExecuted $evento) {
            $this->enLaBase += $evento->time;
            $this->consultas[] = ['sql' => $evento->sql, 'ms' => $evento->time];
        });

        app()->terminating(fn() => $this->informar());
    }

    private function informar(): void
    {
        if ($this->consultas === []) {
            return;
        }

        $peticion = request();

        Log::debug('perfil', array_filter([
            'ruta' => $peticion->method().' '.'/'.ltrim($peticion->path(), '/'),
            'consultas' => count($this->consultas),
            'ms_base' => round($this->enLaBase, 1),
            'ms_total' => $this->msTotales(),
            'mas_lenta' => $this->masLenta(),
            'repetida' => $this->masRepetida(),
        ]));
    }

    private function msTotales(): ?float
    {
        // LARAVEL_START lo define public/index.php al arrancar la petición.
        return defined('LARAVEL_START') ? round((microtime(true) - LARAVEL_START) * 1000, 1) : null;
    }

    /** @return array{ms: float, sql: string}|null */
    private function masLenta(): ?array
    {
        $lenta = collect($this->consultas)->sortByDesc('ms')->first();

        // Por debajo de 50 ms no hay nada que mirar; sería ruido en el registro.
        if (! $lenta || $lenta['ms'] < 50) {
            return null;
        }

        return ['ms' => round($lenta['ms'], 1), 'sql' => Str::limit($lenta['sql'], 200)];
    }

    /**
     * La misma consulta repetida es la firma de un N+1.
     *
     * Se comparan las consultas con los valores ya sustituidos por '?', que es
     * como las entrega el evento: veinte búsquedas del mismo tipo con veinte ids
     * distintos son el mismo SQL, y es justo eso lo que hay que detectar.
     *
     * @return array{veces: int, sql: string}|null
     */
    private function masRepetida(): ?array
    {
        $repetida = collect($this->consultas)
            ->countBy('sql')
            ->sortDesc()
            ->take(1);

        if ($repetida->isEmpty() || $repetida->first() < 5) {
            return null;
        }

        return ['veces' => $repetida->first(), 'sql' => Str::limit($repetida->keys()->first(), 200)];
    }
}
