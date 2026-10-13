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
    /**
     * Las cifras salen de UNA agregación de soportes por cliente que se une a
     * la tabla de clientes. Antes eran seis subconsultas correlacionadas por
     * cada uno de los 4.114 abonados.
     *
     * El motivo también se filtra aquí, en SQL. Filtrarlo en PHP después de
     * paginar dejaba páginas con menos filas y un total que no cuadraba.
     */
    public function scopeRecurrentes(Builder $query, ?int $meses = null, ?int $minimo = null, ?string $motivo = null): Builder
    {
        $meses ??= (int) config('sla.recurrencia.ventana_larga_meses', 12);
        $minimo ??= (int) config('sla.recurrencia.minimo_en_ventana_larga', 3);

        $desde = $meses > 0 ? now()->subMonths($meses) : null;
        $sinContacto = [Soporte::ESTADO_SIN_CONTACTO, Soporte::ESTADO_CERRADO_SIN_CONTACTO];
        $finales = Soporte::ESTADOS_FINALES;

        $porCliente = Soporte::query()
            ->toBase()
            ->select('cliente_id')
            ->selectRaw('count(*) as reportes')
            ->selectRaw('sum(case when estado in (?, ?) then 1 else 0 end) as sin_contacto', $sinContacto)
            ->selectRaw('sum(case when estado not in ('.implode(', ', array_fill(0, count($finales), '?')).') then 1 else 0 end) as abiertos', $finales)
            ->selectRaw('max(created_at) as ultimo_reporte')
            ->selectRaw('min(created_at) as primer_reporte')
            ->when($desde, fn ($q) => $q->where('created_at', '>=', $desde))
            ->groupBy('cliente_id')
            ->havingRaw('count(*) >= ?', [$minimo]);

        return $query
            ->joinSub($porCliente, 'recurrencia', 'recurrencia.cliente_id', '=', 'clientes.id')
            ->select('clientes.*', 'recurrencia.reportes', 'recurrencia.sin_contacto', 'recurrencia.abiertos',
                'recurrencia.ultimo_reporte', 'recurrencia.primer_reporte')
            ->when($motivo === self::MOTIVO_SIN_CONTACTO, fn ($q) => $q->whereRaw('recurrencia.sin_contacto * 2 >= recurrencia.reportes'))
            ->when($motivo === self::MOTIVO_FALLA, fn ($q) => $q->whereRaw('recurrencia.sin_contacto * 2 < recurrencia.reportes'));
    }
}
