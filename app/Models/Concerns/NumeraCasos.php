<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Consecutivo de los casos: SGT-AAAA-NNNNNN, sin choques ni con altas
 * simultáneas ni con lo que inserta el importador del histórico.
 */
trait NumeraCasos
{
    /**
     * Reserva el siguiente consecutivo del año con la fila de su contador
     * bloqueada: dos altas simultáneas nunca reciben el mismo número. Si el
     * alta falla después, el número queda sin usar; un hueco es preferible a
     * un choque.
     */
    public static function siguienteNumero(): string
    {
        $anio = now()->year;
        $clave = "soportes-{$anio}";

        $consecutivo = DB::transaction(function () use ($anio, $clave) {
            // La primera vez en el año el contador arranca donde va la tabla.
            DB::table('secuencias')->insertOrIgnore([
                'clave' => $clave,
                'valor' => static::ultimoConsecutivo($anio),
            ]);

            $valor = (int) DB::table('secuencias')->where('clave', $clave)->lockForUpdate()->value('valor') + 1;

            DB::table('secuencias')->where('clave', $clave)->update(['valor' => $valor]);

            return $valor;
        });

        return sprintf('SGT-%d-%06d', $anio, $consecutivo);
    }

    /** Último consecutivo usado en la tabla para ese año (0 si no hay). */
    public static function ultimoConsecutivo(int $anio): int
    {
        $ultimo = static::withTrashed()
            ->where('numero_soporte', 'like', "SGT-{$anio}-%")
            ->orderByDesc('numero_soporte')
            ->value('numero_soporte');

        return $ultimo ? (int) substr($ultimo, -6) : 0;
    }

    /**
     * Pone el contador al día con la tabla. Lo usa quien inserta casos con
     * número propio, como el importador del histórico.
     */
    public static function sincronizarSecuencia(int $anio): void
    {
        $clave = "soportes-{$anio}";
        $ultimo = static::ultimoConsecutivo($anio);

        DB::table('secuencias')->insertOrIgnore(['clave' => $clave, 'valor' => $ultimo]);

        // Solo hacia adelante y en una sola sentencia: nunca baja el contador.
        DB::table('secuencias')->where('clave', $clave)->where('valor', '<', $ultimo)->update(['valor' => $ultimo]);
    }
}
