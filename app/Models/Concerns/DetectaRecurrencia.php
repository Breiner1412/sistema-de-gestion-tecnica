<?php

namespace App\Models\Concerns;

use App\Models\Soporte;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Abonados que vuelven a reportar al poco tiempo.
 *
 * En la operación se les decía "reincidentes" y se sacaban aparte porque son
 * dos problemas distintos disfrazados de uno: o hay una falla real que no se
 * está resolviendo de raíz —y seguir atendiendo síntomas no la arregla—, o el
 * cliente nunca contesta y el caso se reabre una y otra vez sin avanzar.
 * Distinguirlos es lo que hace accionable la lista.
 */
trait DetectaRecurrencia
{
    public const MOTIVO_FALLA = 'falla_recurrente';
    public const MOTIVO_SIN_CONTACTO = 'sin_contacto';

    /**
     * Cuántas veces reportó este abonado en los N días ANTERIORES a una fecha.
     *
     * Se mide contra la fecha del caso y no contra hoy: así la alerta sigue
     * siendo cierta cuando se mira un caso viejo, y funciona igual sobre el
     * histórico importado.
     */
    public function reportesPrevios(CarbonInterface $antesDe, ?int $dias = null, ?int $excluirSoporteId = null): int
    {
        $dias ??= (int) config('sla.recurrencia.ventana_dias', 60);

        return Soporte::where('cliente_id', $this->id)
            ->where('created_at', '<', $antesDe)
            ->where('created_at', '>=', $antesDe->copy()->subDays($dias))
            ->when($excluirSoporteId, fn(Builder $q) => $q->whereKeyNot($excluirSoporteId))
            ->count();
    }

    /** Total de reportes en la ventana larga, contando desde hoy hacia atrás. */
    public function reportesRecientes(?int $meses = null): int
    {
        $meses ??= (int) config('sla.recurrencia.ventana_larga_meses', 12);

        return Soporte::where('cliente_id', $this->id)
            ->where('created_at', '>=', now()->subMonths($meses))
            ->count();
    }

    public function esRecurrente(): bool
    {
        return $this->reportesRecientes() >= (int) config('sla.recurrencia.minimo_en_ventana_larga', 3);
    }

    /**
     * Por qué está en la lista. Si la mitad o más de sus casos murieron sin
     * poder contactarlo, el problema no es la red: es el canal con el cliente.
     */
    public function motivoRecurrencia(): string
    {
        $total = Soporte::where('cliente_id', $this->id)->count();

        if ($total === 0) {
            return self::MOTIVO_FALLA;
        }

        $sinContacto = Soporte::where('cliente_id', $this->id)
            ->whereIn('estado', [Soporte::ESTADO_SIN_CONTACTO, Soporte::ESTADO_CERRADO_SIN_CONTACTO])
            ->count();

        return $sinContacto >= $total / 2 ? self::MOTIVO_SIN_CONTACTO : self::MOTIVO_FALLA;
    }

    /**
     * Abonados con al menos N reportes dentro de una ventana de meses,
     * con las cifras que hacen falta para decidir qué hacer con cada uno.
     */
    public function scopeRecurrentes(Builder $query, ?int $meses = null, ?int $minimo = null): Builder
    {
        $meses ??= (int) config('sla.recurrencia.ventana_larga_meses', 12);
        $minimo ??= (int) config('sla.recurrencia.minimo_en_ventana_larga', 3);

        $desde = $meses > 0 ? now()->subMonths($meses) : null;

        $enVentana = fn(Builder $q) => $desde ? $q->where('soportes.created_at', '>=', $desde) : $q;

        return $query
            ->withCount([
                'soportes as reportes' => $enVentana,
                'soportes as sin_contacto' => fn(Builder $q) => $enVentana($q)->whereIn('soportes.estado', [
                    Soporte::ESTADO_SIN_CONTACTO,
                    Soporte::ESTADO_CERRADO_SIN_CONTACTO,
                ]),
                'soportes as abiertos' => fn(Builder $q) => $enVentana($q)
                    ->whereNotIn('soportes.estado', Soporte::ESTADOS_FINALES),
            ])
            ->withMax(['soportes as ultimo_reporte' => $enVentana], 'created_at')
            ->withMin(['soportes as primer_reporte' => $enVentana], 'created_at')
            // whereHas con operador arma la condición dentro del WHERE. Un HAVING
            // sobre el alias de un withCount funciona en MySQL pero no en SQLite,
            // que exige que la consulta sea agregada.
            ->whereHas('soportes', $enVentana, '>=', $minimo);
    }
}
