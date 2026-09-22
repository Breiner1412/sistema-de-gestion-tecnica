<?php

namespace App\Console\Commands;

use App\Models\Soporte;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cierra el círculo de la política de "sin contacto".
 *
 * Cuando el técnico marca que el cliente no contestó, el caso queda en pausa
 * con una hora de reintento. Sin este comando esa hora pasa y nadie vuelve a
 * llamar, que es exactamente lo que ocurría en el Excel con el 12% de los
 * casos: quedaban marcados "sin contacto" y ahí morían.
 *
 * Cada cuarto de hora, este comando:
 *   - devuelve a la cola del nivel 2 los casos cuyo reintento ya se cumplió,
 *     reanudando el reloj de SLA;
 *   - cierra los que ya agotaron los intentos permitidos, dejando constancia.
 */
class ReintentarContacto extends Command
{
    protected $signature = 'soportes:reintentar-contacto
                            {--dry-run : Muestra lo que haría sin tocar nada}';

    protected $description = 'Devuelve a la cola los casos sin contacto cuyo reintento venció y cierra los que agotaron los intentos';

    public function handle(): int
    {
        $maximo = (int) config('sla.sin_contacto.max_intentos', 3);
        $simulacion = (bool) $this->option('dry-run');

        $devueltos = 0;
        $cerrados = 0;

        $this->pendientes()->chunkById(100, function ($casos) use ($maximo, $simulacion, &$devueltos, &$cerrados) {
            foreach ($casos as $caso) {
                $agotado = $caso->intentos_contacto >= $maximo;

                $this->line(sprintf(
                    '  %s  %s  intento %d/%d  →  %s',
                    $caso->numero_soporte,
                    str($caso->cliente->nombre ?? '—')->limit(28)->padRight(30),
                    $caso->intentos_contacto,
                    $maximo,
                    $agotado ? 'cerrar' : 'devolver a la cola',
                ));

                if ($simulacion) {
                    $agotado ? $cerrados++ : $devueltos++;
                    continue;
                }

                if ($agotado) {
                    $this->cerrar($caso, $maximo);
                    $cerrados++;
                } else {
                    $this->devolver($caso, $maximo);
                    $devueltos++;
                }
            }
        });

        if ($devueltos === 0 && $cerrados === 0) {
            $this->info('No hay reintentos pendientes.');

            return self::SUCCESS;
        }

        $resumen = "Reintentos: {$devueltos} devueltos a la cola, {$cerrados} cerrados por agotar intentos.";

        $this->info($simulacion ? "[simulación] {$resumen}" : $resumen);

        if (!$simulacion) {
            Log::info('soportes:reintentar-contacto', ['devueltos' => $devueltos, 'cerrados' => $cerrados]);
        }

        return self::SUCCESS;
    }

    /** Casos en pausa por falta de contacto cuya hora de reintento ya pasó. */
    private function pendientes()
    {
        return Soporte::query()
            ->with('cliente:id,nombre')
            ->where('estado', Soporte::ESTADO_SIN_CONTACTO)
            ->whereNotNull('proximo_intento_at')
            ->where('proximo_intento_at', '<=', now());
    }

    private function devolver(Soporte $caso, int $maximo): void
    {
        $siguiente = $caso->intentos_contacto + 1;

        // Sin usuario: la transición queda marcada como automática.
        $caso->cambiarEstado(
            Soporte::ESTADO_EN_PROCESO,
            "Reintento programado: toca volver a contactar al cliente (intento {$siguiente} de {$maximo}).",
        );

        // Se limpia para no volver a tomarlo hasta que alguien marque otro fallo.
        $caso->forceFill(['proximo_intento_at' => null])->saveQuietly();
    }

    private function cerrar(Soporte $caso, int $maximo): void
    {
        $caso->cambiarEstado(
            Soporte::ESTADO_CERRADO_SIN_CONTACTO,
            "Cerrado automáticamente tras {$maximo} intentos de contacto sin respuesta.",
        );

        $caso->forceFill(['proximo_intento_at' => null])->saveQuietly();
    }
}
