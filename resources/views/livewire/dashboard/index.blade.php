<?php

use App\Models\OrdenTrabajo;
use App\Models\Soporte;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    #[Url(except: '30')]
    public string $rango = '30';

    private function desde()
    {
        return match ($this->rango) {
            '7' => now()->subDays(7),
            '90' => now()->subDays(90),
            '365' => now()->subDays(365),
            default => now()->subDays(30),
        };
    }

    /** Se califica la columna porque algunas métricas hacen JOIN. */
    private function base()
    {
        return Soporte::where('soportes.created_at', '>=', $this->desde());
    }

    /** Minutos hábiles de presupuesto por criticidad, según config/sla.php. */
    private function presupuestos(): array
    {
        $horasDia = (int) config('sla.jornada.hora_fin', 18) - (int) config('sla.jornada.hora_inicio', 7);

        return [
            Soporte::CRITICIDAD_INMEDIATA => (int) (config('sla.tiempos.inmediata.resolucion', 4) * 60),
            Soporte::CRITICIDAD_NORMAL => (int) (config('sla.tiempos.normal.resolucion', 5) * $horasDia * 60),
        ];
    }

    public function with(): array
    {
        [$pInmediata, $pNormal] = array_values($this->presupuestos());

        $porEstado = $this->base()
            ->select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $total = (int) $porEstado->sum();

        $tiempos = $this->base()
            ->selectRaw('avg(tiempo_respuesta) as respuesta, avg(tiempo_resolucion) as resolucion')
            ->first();

        // Cumplimiento: de los casos ya cerrados, cuántos se resolvieron dentro
        // del presupuesto que les correspondía por criticidad.
        $cumplimiento = $this->base()
            ->whereNotNull('tiempo_resolucion')
            ->selectRaw(
                'count(*) as cerrados, sum(case when tiempo_resolucion <= case when criticidad = ? then ? else ? end then 1 else 0 end) as dentro',
                [Soporte::CRITICIDAD_INMEDIATA, $pInmediata, $pNormal]
            )
            ->first();

        $visitas = OrdenTrabajo::where('created_at', '>=', $this->desde())
            ->whereNotNull('fecha_programada')
            ->whereNotNull('hora_fin')
            ->get(['fecha_programada', 'hora_fin']);

        return [
            'total' => $total,
            'porEstado' => $porEstado,
            'abiertos' => $this->base()->whereNotIn('soportes.estado', Soporte::ESTADOS_FINALES)->count(),
            'vencidos' => Soporte::vencidos()->count(),
            'escalados' => $this->base()->where('escalado_nivel_3', true)->count(),
            'inmediatos' => $this->base()->where('criticidad', Soporte::CRITICIDAD_INMEDIATA)->count(),

            'tasaSinContacto' => $total > 0
                ? round((($porEstado[Soporte::ESTADO_SIN_CONTACTO] ?? 0) + ($porEstado[Soporte::ESTADO_CERRADO_SIN_CONTACTO] ?? 0)) / $total * 100, 1)
                : 0.0,

            'cumplimientoSla' => ($cumplimiento?->cerrados ?? 0) > 0
                ? round($cumplimiento->dentro / $cumplimiento->cerrados * 100)
                : null,

            'cumplimientoVisitas' => $visitas->count() > 0
                ? round($visitas->filter(fn($v) => $v->hora_fin->isSameDay($v->fecha_programada))->count() / $visitas->count() * 100)
                : null,

            'promRespuesta' => $tiempos?->respuesta ? round($tiempos->respuesta) : null,
            'promResolucion' => $tiempos?->resolucion ? round($tiempos->resolucion) : null,

            'porTipo' => $this->base()
                ->select('tipo_solicitud', DB::raw('count(*) as total'))
                ->groupBy('tipo_solicitud')
                ->orderByDesc('total')
                ->pluck('total', 'tipo_solicitud'),

            'porCanal' => $this->base()
                ->select('canal_ingreso', DB::raw('count(*) as total'))
                ->groupBy('canal_ingreso')
                ->orderByDesc('total')
                ->pluck('total', 'canal_ingreso'),

            'topDiagnosticos' => $this->base()
                ->join('diagnosticos', 'diagnosticos.id', '=', 'soportes.diagnostico_id')
                ->select('diagnosticos.nombre', DB::raw('count(*) as total'))
                ->groupBy('diagnosticos.nombre')
                ->orderByDesc('total')
                ->limit(8)
                ->pluck('total', 'diagnosticos.nombre'),

            'porTecnico' => $this->base()
                ->join('users', 'users.id', '=', 'soportes.tecnico_soporte_id')
                ->select(
                    'users.id',
                    'users.name',
                    DB::raw('count(*) as total'),
                    DB::raw("sum(case when soportes.estado = 'solucionado' then 1 else 0 end) as solucionados"),
                    DB::raw('avg(soportes.tiempo_resolucion) as promedio'),
                )
                // Por id y no por nombre: dos personas con el mismo nombre son dos filas.
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
        ];
    }
}; ?>

<div class="mx-auto flex max-w-[1400px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">

    <x-encabezado titulo="Panel de soporte" subtitulo="Cómo va la mesa en el periodo elegido.">
        <x-slot:acciones>
            <label for="rango" class="sr-only">Periodo</label>
            <select id="rango" wire:model.live="rango" class="control h-9 w-auto py-0 pr-9">
                <option value="7">Últimos 7 días</option>
                <option value="30">Últimos 30 días</option>
                <option value="90">Últimos 90 días</option>
                <option value="365">Último año</option>
            </select>
        </x-slot:acciones>
    </x-encabezado>

    {{-- Indicadores: lo que pide acción va primero y es lo único con color. --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="tarjeta p-4">
            <p class="text-[13px] font-medium text-tinta-3">Abiertos ahora</p>
            <p class="mt-1 font-mono text-[32px] font-semibold leading-none">{{ number_format($abiertos) }}</p>
            @if ($vencidos > 0)
                <a href="{{ route('soportes.index', ['vista' => 'vencidos']) }}" wire:navigate
                   class="mt-2 inline-flex h-[22px] items-center gap-1.5 rounded bg-sla-vencido px-[7px] text-xs font-semibold text-white no-underline hover:bg-sla-vencido-oscuro">
                    <x-sla-glifo estado="vencido" :tamano="11" :invertido="true" />
                    <span class="font-mono">{{ $vencidos }}</span> con SLA vencido
                </a>
            @else
                <p class="mt-2 text-xs text-tinta-3">ninguno vencido</p>
            @endif
        </div>

        <div class="tarjeta p-4">
            <p class="text-[13px] font-medium text-tinta-3">Cumplimiento de SLA</p>
            <p class="mt-1 font-mono text-[32px] font-semibold leading-none">
                @if ($cumplimientoSla !== null)
                    {{ $cumplimientoSla }}<span class="text-lg text-tinta-3">%</span>
                @else
                    <span class="font-sans text-xl text-tinta-3">Sin datos</span>
                @endif
            </p>
            @if ($cumplimientoSla !== null)
                <div class="mt-2.5 h-1.5 rounded-full bg-elevada" aria-hidden="true">
                    <div class="h-1.5 rounded-full bg-acento" style="width: {{ $cumplimientoSla }}%"></div>
                </div>
            @endif
            <p class="mt-1.5 text-xs text-tinta-3">sobre los casos ya cerrados</p>
        </div>

        <div class="tarjeta p-4">
            <p class="text-[13px] font-medium text-tinta-3">Casos registrados</p>
            <p class="mt-1 font-mono text-[32px] font-semibold leading-none">{{ number_format($total) }}</p>
            <p class="mt-2 text-xs text-tinta-3"><span class="font-mono text-tinta">{{ $inmediatos }}</span> inmediatos · <span class="font-mono text-tinta">{{ $escalados }}</span> escalados a redes</p>
        </div>

        <div class="tarjeta p-4">
            <p class="text-[13px] font-medium text-tinta-3">Sin contacto</p>
            <p class="mt-1 font-mono text-[32px] font-semibold leading-none">{{ $tasaSinContacto }}<span class="text-lg text-tinta-3">%</span></p>
            <p class="mt-2 text-xs text-tinta-3">del total del periodo</p>
        </div>
    </div>

    {{-- Tiempos --}}
    <div class="tarjeta grid divide-y divide-linea sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        @php
            $tiempos = [
                ['Tiempo medio de respuesta', $promRespuesta !== null ? $promRespuesta : null, 'min', 'Del ingreso a la asignación, en minutos hábiles.'],
                ['Tiempo medio de resolución', $promResolucion !== null ? $promResolucion : null, 'min', 'Del ingreso al cierre, en minutos hábiles.'],
                ['Visitas en la fecha pactada', $cumplimientoVisitas, '%', 'Visitas ejecutadas el día para el que se programaron.'],
            ];
        @endphp
        @foreach ($tiempos as [$titulo, $valor, $unidad, $pie])
            <div class="p-4">
                <p class="text-[13px] font-medium text-tinta-3">{{ $titulo }}</p>
                <p class="mt-1 font-mono text-2xl font-semibold">
                    @if ($valor !== null)
                        {{ $valor }}<span class="ml-0.5 text-sm text-tinta-3">{{ $unidad }}</span>
                    @else
                        <span class="font-sans text-lg text-tinta-3">Sin datos</span>
                    @endif
                </p>
                <p class="mt-1 text-xs text-tinta-3">{{ $pie }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

        {{-- Estados --}}
        <section class="tarjeta p-4">
            <h2 class="titulo-seccion mb-3">Casos por estado</h2>
            @php $hayEstados = false; @endphp
            @foreach (\App\Models\Soporte::ESTADOS as $clave => $etiqueta)
                @php $valor = $porEstado[$clave] ?? 0; @endphp
                @if ($valor)
                    @php $hayEstados = true; @endphp
                    <x-barra :etiqueta="$etiqueta" :valor="$valor" :maximo="$total" />
                @endif
            @endforeach
            @unless ($hayEstados)
                <p class="text-sm text-tinta-3">Sin datos en el rango.</p>
            @endunless
        </section>

        {{-- Canal de ingreso --}}
        <section class="tarjeta p-4">
            <h2 class="titulo-seccion mb-3">¿Por dónde entran los casos?</h2>
            @forelse ($porCanal as $canal => $valor)
                <x-barra :etiqueta="\App\Models\Soporte::CANALES[$canal] ?? $canal" :valor="$valor" :maximo="$total" color="bg-tinta-3" />
            @empty
                <p class="text-sm text-tinta-3">Sin datos en el rango.</p>
            @endforelse
            <p class="mt-3 text-xs text-tinta-3">
                Solo se registran los casos que el primer nivel no pudo resolver en el momento.
            </p>
        </section>

        {{-- Tipos --}}
        <section class="tarjeta p-4">
            <h2 class="titulo-seccion mb-3">Casos por tipo de solicitud</h2>
            @forelse ($porTipo as $tipo => $valor)
                <x-barra :etiqueta="\App\Models\Soporte::TIPOS_SOLICITUD[$tipo] ?? $tipo" :valor="$valor" :maximo="$total" color="bg-tinta-3" />
            @empty
                <p class="text-sm text-tinta-3">Sin datos en el rango.</p>
            @endforelse
        </section>

        {{-- Diagnósticos --}}
        <section class="tarjeta p-4">
            <h2 class="titulo-seccion mb-2">Diagnósticos más frecuentes</h2>
            @forelse ($topDiagnosticos as $nombre => $valor)
                <div class="flex justify-between gap-3 border-b border-linea-suave py-1.5 text-sm last:border-0">
                    <span class="text-tinta-2">{{ $nombre }}</span>
                    <span class="font-mono font-medium">{{ $valor }}</span>
                </div>
            @empty
                <p class="text-sm text-tinta-3">Todavía no hay casos diagnosticados en este rango.</p>
            @endforelse
        </section>

        {{-- Carga por técnico --}}
        <section class="lg:col-span-2">
            <h2 class="titulo-seccion mb-2">Carga por técnico de soporte</h2>
            @if ($porTecnico->count())
                <x-tabla minimo="520px">
                    <thead>
                        <tr>
                            <th>Técnico</th>
                            <th class="text-right">Casos</th>
                            <th class="text-right">Resueltos</th>
                            <th class="text-right">Prom. min hábiles</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($porTecnico as $fila)
                            <tr>
                                <td class="font-medium">{{ $fila->name }}</td>
                                <td class="text-right font-mono">{{ $fila->total }}</td>
                                <td class="text-right font-mono text-tinta-2">{{ $fila->solucionados }}</td>
                                <td class="text-right font-mono text-tinta-2">{{ $fila->promedio ? round($fila->promedio) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tabla>
            @else
                <p class="tarjeta p-4 text-sm text-tinta-3">Sin casos asignados en el rango.</p>
            @endif
        </section>
    </div>
</div>
