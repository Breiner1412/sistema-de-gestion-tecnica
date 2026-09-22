<?php

namespace App\Console\Commands;

use App\Models\Soporte;
use App\Models\User;
use App\Notifications\CasosEnRiesgoDeSla;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Avisa por correo antes de que un caso se pase del tiempo acordado.
 *
 * El semáforo de la bandeja ya pinta el riesgo, pero solo lo ve quien está
 * mirando la pantalla. Un caso inmediato tiene cuatro horas hábiles: si nadie
 * abre la bandeja en esas cuatro horas, el semáforo no sirvió de nada. Esto es
 * lo que convierte el SLA en algo que persigue a la gente en vez de esperarla.
 *
 * Dos avisos por caso como máximo: uno al entrar en riesgo, otro al vencerse.
 * El nivel ya notificado queda guardado en el caso, así que correr el comando
 * cada hora no llena de correos repetidos la bandeja de nadie.
 */
class AlertarSla extends Command
{
    protected $signature = 'soportes:alertar-sla
                            {--dry-run : Muestra a quién se avisaría sin enviar nada}';

    protected $description = 'Avisa por correo de los casos que están por vencerse o ya se vencieron';

    public function handle(): int
    {
        if (! config('sla.alertas.activas', true)) {
            $this->info('Las alertas de SLA están desactivadas en config/sla.php.');

            return self::SUCCESS;
        }

        $simulacion = (bool) $this->option('dry-run');

        $pendientes = $this->casosPorAvisar();

        if ($pendientes->isEmpty()) {
            $this->info('Nada por avisar: ningún caso cruzó un umbral nuevo.');

            return self::SUCCESS;
        }

        $gestion = $this->gestion();
        $buzones = $this->repartir($pendientes, $gestion);

        $avisados = $this->enviar($buzones, $simulacion);

        // Solo se marca lo que efectivamente salió: si un caso no tenía a quién
        // avisarle, se deja sin marcar para que el próximo turno lo reintente.
        $enviados = $pendientes->filter(fn($fila) => $avisados->contains($fila['caso']->id));

        if (! $simulacion) {
            $this->marcar($enviados);
        }

        $sinDestino = $pendientes->count() - $enviados->count();

        if ($sinDestino > 0) {
            $this->warn("{$sinDestino} caso(s) sin técnico asignado y sin nadie en gestión a quién avisar.");
        }

        $resumen = sprintf(
            '%d caso(s) avisados a %d persona(s).',
            $enviados->count(),
            $buzones->count(),
        );

        $this->info($simulacion ? "[simulación] {$resumen}" : $resumen);

        if (! $simulacion) {
            Log::info('soportes:alertar-sla', [
                'casos' => $enviados->count(),
                'destinatarios' => $buzones->count(),
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * Casos abiertos cuyo reloj cruzó un umbral del que todavía no se avisó.
     *
     * El porcentaje se calcula en PHP y no en SQL a propósito: el reloj es de
     * tiempo hábil, así que "85% consumido" no es una resta de fechas que la
     * base sepa hacer. Son decenas de casos abiertos, no miles.
     *
     * @return Collection<int, array{caso: Soporte, nivel: string}>
     */
    private function casosPorAvisar(): Collection
    {
        return Soporte::query()
            ->abiertos()
            ->whereNull('sla_pausado_at')
            ->whereNotNull('sla_vence_at')
            ->with(['cliente:id,nombre', 'tipoFalla:id,nombre', 'tecnicoSoporte'])
            ->get()
            ->map(fn(Soporte $caso) => ['caso' => $caso, 'nivel' => $this->nivelDe($caso)])
            ->filter(fn(array $fila) => $fila['nivel'] !== null
                && $fila['nivel'] !== $fila['caso']->alerta_sla_nivel)
            ->values();
    }

    /** 'vencido', 'riesgo' o null si todavía va con holgura. */
    private function nivelDe(Soporte $caso): ?string
    {
        if ($caso->slaVencido()) {
            return 'vencido';
        }

        return $caso->porcentajeSla() >= (int) config('sla.semaforo.riesgo', 85)
            ? 'riesgo'
            : null;
    }

    /** @return Collection<int, User> */
    private function gestion(): Collection
    {
        $roles = (array) config('sla.alertas.copia_gestion', [User::ROL_ADMIN, User::ROL_GERENTE]);

        if ($roles === []) {
            return collect();
        }

        return User::whereIn('rol', $roles)
            ->where('estado', 'activo')
            ->whereNotNull('email')
            ->get();
    }

    /**
     * Quién recibe qué.
     *
     * El técnico asignado recibe lo suyo. Gestión recibe lo vencido —que ya es
     * un incumplimiento y alguien tiene que decidir qué hacer— y lo que nadie
     * ha tomado, que si no aparecería en la bandeja de nadie.
     *
     * @param  Collection<int, array{caso: Soporte, nivel: string}>  $pendientes
     * @param  Collection<int, User>  $gestion
     * @return Collection<int, array{persona: User, vencidos: Collection, riesgo: Collection}>
     */
    private function repartir(Collection $pendientes, Collection $gestion): Collection
    {
        $buzones = collect();

        $apilar = function (User $persona, Soporte $caso, string $nivel) use ($buzones) {
            $buzon = $buzones->get($persona->id) ?? [
                'persona' => $persona,
                'vencidos' => collect(),
                'riesgo' => collect(),
            ];

            $buzon[$nivel === 'vencido' ? 'vencidos' : 'riesgo']->push($caso);

            $buzones->put($persona->id, $buzon);
        };

        foreach ($pendientes as ['caso' => $caso, 'nivel' => $nivel]) {
            $tecnico = $caso->tecnicoSoporte;

            if ($tecnico && $tecnico->email) {
                $apilar($tecnico, $caso, $nivel);
            }

            if ($nivel === 'vencido' || ! $tecnico) {
                foreach ($gestion as $persona) {
                    if ($tecnico?->id !== $persona->id) {
                        $apilar($persona, $caso, $nivel);
                    }
                }
            }
        }

        return $buzones->values();
    }

    /**
     * @param  Collection<int, array{persona: User, vencidos: Collection, riesgo: Collection}>  $buzones
     * @return Collection<int, int>  Ids de los casos que salieron en algún correo.
     */
    private function enviar(Collection $buzones, bool $simulacion): Collection
    {
        $avisados = collect();

        foreach ($buzones as $buzon) {
            $this->line(sprintf(
                '  %s  →  %d vencido(s), %d por vencerse',
                str($buzon['persona']->email)->limit(34)->padRight(36),
                $buzon['vencidos']->count(),
                $buzon['riesgo']->count(),
            ));

            if (! $simulacion) {
                $buzon['persona']->notify(new CasosEnRiesgoDeSla(
                    $buzon['vencidos'],
                    $buzon['riesgo'],
                ));
            }

            $avisados = $avisados->merge($buzon['vencidos']->pluck('id'))
                ->merge($buzon['riesgo']->pluck('id'));
        }

        return $avisados->unique()->values();
    }

    /** @param Collection<int, array{caso: Soporte, nivel: string}> $enviados */
    private function marcar(Collection $enviados): void
    {
        foreach ($enviados->groupBy('nivel') as $nivel => $filas) {
            Soporte::whereIn('id', $filas->pluck('caso.id'))->update([
                'alerta_sla_nivel' => $nivel,
                'alerta_sla_at' => now(),
            ]);
        }
    }
}
