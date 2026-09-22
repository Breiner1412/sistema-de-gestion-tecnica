<?php

namespace App\Support;

use App\Models\InformeMensual;
use App\Models\Soporte;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cifras del mes de un técnico de soporte.
 *
 * Es el cálculo detrás del informe de rendimiento que la operación hacía una
 * vez al mes con cada persona. Vive aparte de la vista porque también se
 * congela como snapshot al publicar el informe: el documento firmado no puede
 * cambiar después porque un caso viejo se haya movido.
 */
class MetricasTecnico
{
    public function __construct(
        private readonly User $tecnico,
        private readonly int $anio,
        private readonly int $mes,
    ) {}

    public static function para(User $tecnico, int $anio, int $mes): self
    {
        return new self($tecnico, $anio, $mes);
    }

    private function inicio(): CarbonImmutable
    {
        return CarbonImmutable::create($this->anio, $this->mes, 1)->startOfMonth();
    }

    private function fin(): CarbonImmutable
    {
        return $this->inicio()->endOfMonth();
    }

    /** Casos atendidos por el técnico en el mes. */
    private function base()
    {
        return Soporte::where('tecnico_soporte_id', $this->tecnico->id)
            ->whereBetween('soportes.created_at', [$this->inicio(), $this->fin()]);
    }

    /** @return array<string, mixed> */
    public function calcular(): array
    {
        $total = (clone $this->base())->count();

        $porEstado = (clone $this->base())
            ->select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        // El agrupado por día se hace en PHP a propósito: day() es de MySQL y
        // rompe en SQLite, que es lo que usan las pruebas. Son unos cientos de
        // filas por técnico y mes, así que no vale la pena ramificar por motor.
        $porDia = (clone $this->base())
            ->pluck('created_at')
            ->countBy(fn($fecha) => (int) $fecha->day)
            ->sortKeys()
            ->all();

        $porFalla = (clone $this->base())
            ->join('tipos_falla', 'tipos_falla.id', '=', 'soportes.tipo_falla_id')
            ->select('tipos_falla.nombre', DB::raw('count(*) as total'))
            ->groupBy('tipos_falla.nombre')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'tipos_falla.nombre')
            ->all();

        $porDiagnostico = (clone $this->base())
            ->join('diagnosticos', 'diagnosticos.id', '=', 'soportes.diagnostico_id')
            ->select('diagnosticos.nombre', DB::raw('count(*) as total'))
            ->groupBy('diagnosticos.nombre')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'diagnosticos.nombre')
            ->all();

        $resueltos = $porEstado[Soporte::ESTADO_SOLUCIONADO] ?? 0;
        $sinContacto = ($porEstado[Soporte::ESTADO_SIN_CONTACTO] ?? 0)
            + ($porEstado[Soporte::ESTADO_CERRADO_SIN_CONTACTO] ?? 0);

        $promedio = (clone $this->base())->whereNotNull('tiempo_resolucion')->avg('tiempo_resolucion');

        return [
            'anio' => $this->anio,
            'mes' => $this->mes,
            'periodo' => (InformeMensual::MESES[$this->mes] ?? $this->mes).' de '.$this->anio,
            'tecnico' => $this->tecnico->name,

            'total' => $total,
            'resueltos' => $resueltos,
            'sin_contacto' => $sinContacto,
            'escalados' => (clone $this->base())->where('escalado_nivel_3', true)->count(),
            'enviados_campo' => (clone $this->base())->whereHas('ordenesTrabajo')->count(),
            'inmediatos' => (clone $this->base())->where('criticidad', Soporte::CRITICIDAD_INMEDIATA)->count(),

            // Enteros a propósito: son porcentajes, y además se guardan tal cual
            // en el snapshot JSON del informe publicado.
            'tasa_resolucion' => $total > 0 ? (int) round($resueltos / $total * 100) : null,
            'tasa_sin_contacto' => $total > 0 ? (int) round($sinContacto / $total * 100) : null,
            'promedio_resolucion' => $promedio ? (int) round($promedio) : null,

            'dias_trabajados' => count($porDia),
            'mejor_dia' => $porDia ? array_search(max($porDia), $porDia, true) : null,
            'promedio_diario' => $porDia ? round($total / count($porDia), 1) : null,

            'por_dia' => $porDia,
            'por_estado' => $porEstado,
            'por_falla' => $porFalla,
            'por_diagnostico' => $porDiagnostico,

            'equipo' => $this->promediosDelEquipo(),
        ];
    }

    /**
     * Referencia del equipo en el mismo mes: una cifra suelta no dice nada sin
     * saber qué hicieron los demás.
     */
    private function promediosDelEquipo(): array
    {
        $porTecnico = Soporte::whereNotNull('tecnico_soporte_id')
            ->whereBetween('soportes.created_at', [$this->inicio(), $this->fin()])
            ->select('tecnico_soporte_id', DB::raw('count(*) as total'))
            ->groupBy('tecnico_soporte_id')
            ->pluck('total', 'tecnico_soporte_id');

        $promedioResolucion = Soporte::whereNotNull('tecnico_soporte_id')
            ->whereNotNull('tiempo_resolucion')
            ->whereBetween('soportes.created_at', [$this->inicio(), $this->fin()])
            ->avg('tiempo_resolucion');

        $mios = $porTecnico[$this->tecnico->id] ?? 0;
        $ordenados = $porTecnico->sortDesc()->keys()->all();

        return [
            'tecnicos_activos' => $porTecnico->count(),
            'casos_totales' => (int) $porTecnico->sum(),
            'promedio_casos' => $porTecnico->count() > 0 ? round($porTecnico->avg(), 1) : null,
            'promedio_resolucion' => $promedioResolucion ? (int) round($promedioResolucion) : null,
            'posicion' => ($indice = array_search($this->tecnico->id, $ordenados, true)) !== false ? $indice + 1 : null,
            'participacion' => $porTecnico->sum() > 0 ? round($mios / $porTecnico->sum() * 100, 1) : null,
        ];
    }
}
