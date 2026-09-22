<?php

namespace App\Models\Concerns;

use App\Support\CalendarioHabil;
use Carbon\CarbonImmutable;

/**
 * Reloj de SLA del nivel 2, medido en tiempo hábil.
 *
 * El reloj arranca cuando entra el caso y se DETIENE cuando el caso deja de
 * depender del técnico de soporte: mientras espera una visita programada,
 * mientras está en ingeniería de redes, o mientras se espera que el cliente
 * conteste. Esos tiempos tienen sus propias métricas y no se le cobran a él.
 */
trait GestionaSla
{
    public function calendario(): CalendarioHabil
    {
        return CalendarioHabil::desdeConfig();
    }

    /** Minutos hábiles que la operación se da para resolver este caso. */
    public function presupuestoSlaMinutos(): int
    {
        $config = config("sla.tiempos.{$this->criticidad}", config('sla.tiempos.normal'));

        if (($config['unidad'] ?? 'horas') === 'dias') {
            $horasPorDia = (int) config('sla.jornada.hora_fin', 18) - (int) config('sla.jornada.hora_inicio', 7);

            return (int) round($config['resolucion'] * $horasPorDia * 60);
        }

        return (int) round($config['resolucion'] * 60);
    }

    public function iniciarSla(): void
    {
        $desde = CarbonImmutable::instance($this->created_at ?? now());
        $config = config("sla.tiempos.{$this->criticidad}", config('sla.tiempos.normal'));

        $vence = ($config['unidad'] ?? 'horas') === 'dias'
            ? $this->calendario()->sumarDias($desde, (int) $config['resolucion'])
            : $this->calendario()->sumarHoras($desde, (float) $config['resolucion']);

        $this->forceFill([
            'sla_vence_at' => $vence,
            'sla_pausado_at' => null,
            'sla_minutos_restantes' => null,
        ]);
    }

    public function pausarSla(): void
    {
        if ($this->sla_pausado_at || !$this->sla_vence_at) {
            return;
        }

        $this->forceFill([
            'sla_pausado_at' => now(),
            'sla_minutos_restantes' => $this->calendario()->minutosEntre(now(), $this->sla_vence_at),
        ]);
    }

    public function reanudarSla(): void
    {
        if (!$this->sla_pausado_at) {
            return;
        }

        $this->forceFill([
            'sla_vence_at' => $this->calendario()->sumarMinutos(now(), (int) $this->sla_minutos_restantes),
            'sla_pausado_at' => null,
            'sla_minutos_restantes' => null,
        ]);
    }

    /** Minutos hábiles que quedan antes de incumplir. 0 si ya se venció. */
    public function minutosRestantesSla(): int
    {
        if ($this->estaCerrado() || !$this->sla_vence_at) {
            return 0;
        }

        if ($this->sla_pausado_at) {
            return max(0, (int) $this->sla_minutos_restantes);
        }

        return $this->calendario()->minutosEntre(now(), $this->sla_vence_at);
    }

    public function slaVencido(): bool
    {
        return !$this->estaCerrado()
            && !$this->sla_pausado_at
            && $this->sla_vence_at
            && now()->greaterThan($this->sla_vence_at);
    }

    public function slaPausado(): bool
    {
        return (bool) $this->sla_pausado_at;
    }

    /** Porcentaje del presupuesto ya consumido (0-100). */
    public function porcentajeSla(): int
    {
        $presupuesto = $this->presupuestoSlaMinutos();

        if ($presupuesto <= 0) {
            return 0;
        }

        $consumido = 100 - (int) round($this->minutosRestantesSla() / $presupuesto * 100);

        return max(0, min(100, $consumido));
    }

    /**
     * Color del semáforo para la bandeja: ok, atencion, riesgo, vencido, pausado.
     */
    public function semaforoSla(): string
    {
        if ($this->estaCerrado()) {
            return 'ok';
        }

        if ($this->slaPausado()) {
            return 'pausado';
        }

        if ($this->slaVencido()) {
            return 'vencido';
        }

        $consumido = $this->porcentajeSla();

        return match (true) {
            $consumido >= (int) config('sla.semaforo.riesgo', 85) => 'riesgo',
            $consumido >= (int) config('sla.semaforo.atencion', 60) => 'atencion',
            default => 'ok',
        };
    }

    /** "3 h 20 min" a partir de los minutos restantes. */
    public function restanteLegible(): string
    {
        $minutos = $this->minutosRestantesSla();

        if ($minutos <= 0) {
            return $this->estaCerrado() ? '—' : 'vencido';
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $horas > 0 ? "{$horas} h {$resto} min" : "{$resto} min";
    }
}
