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
                    'users.name',
                    DB::raw('count(*) as total'),
                    DB::raw("sum(case when soportes.estado = 'solucionado' then 1 else 0 end) as solucionados"),
                    DB::raw('avg(soportes.tiempo_resolucion) as promedio'),
                )
                ->groupBy('users.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
        ];
    }
}; ?>

<div class="p-6 space-y-6">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Panel de soporte</h1>
        <select wire:model.live="rango" class="border rounded p-2 text-sm">
            <option value="7">Últimos 7 días</option>
            <option value="30">Últimos 30 días</option>
            <option value="90">Últimos 90 días</option>
            <option value="365">Último año</option>
        </select>
    </div>

    {{-- Indicadores --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $tarjetas = [
                ['Casos registrados', number_format($total), $inmediatos.' inmediatos'],
                ['Abiertos ahora', number_format($abiertos), $vencidos > 0 ? $vencidos.' con SLA vencido' : 'ninguno vencido'],
                ['Cumplimiento de SLA', $cumplimientoSla !== null ? $cumplimientoSla.'%' : 'Sin datos', 'sobre los casos ya cerrados'],
                ['Sin contacto', $tasaSinContacto.'%', 'del total del periodo'],
            ];
        @endphp

        @foreach ($tarjetas as [$titulo, $valor, $pie])
            <div class="bg-white border rounded p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">{{ $titulo }}</p>
                <p class="text-3xl font-bold text-slate-800 mt-1">{{ $valor }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ $pie }}</p>
            </div>
        @endforeach
    </div>

    {{-- Tiempos --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="bg-white border rounded p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Tiempo medio de respuesta</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">
                {{ $promRespuesta !== null ? $promRespuesta.' min' : 'Sin datos' }}
            </p>
            <p class="text-xs text-slate-500 mt-1">Del ingreso a la asignación, en minutos hábiles.</p>
        </div>
        <div class="bg-white border rounded p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Tiempo medio de resolución</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">
                {{ $promResolucion !== null ? $promResolucion.' min' : 'Sin datos' }}
            </p>
            <p class="text-xs text-slate-500 mt-1">Del ingreso al cierre, en minutos hábiles.</p>
        </div>
        <div class="bg-white border rounded p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Visitas en la fecha pactada</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">
                {{ $cumplimientoVisitas !== null ? $cumplimientoVisitas.'%' : 'Sin datos' }}
            </p>
            <p class="text-xs text-slate-500 mt-1">Visitas ejecutadas el día para el que se programaron.</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">

        {{-- Estados --}}
        <div class="bg-white border rounded p-4">
            <h2 class="font-semibold text-slate-800 mb-3">Casos por estado</h2>
            @forelse (\App\Models\Soporte::ESTADOS as $clave => $etiqueta)
                @php $valor = $porEstado[$clave] ?? 0; @endphp
                @if ($valor)
                    <div class="mb-2">
                        <div class="flex justify-between text-sm text-slate-700">
                            <span>{{ $etiqueta }}</span>
                            <span class="font-medium">{{ $valor }}</span>
                        </div>
                        <div class="h-2 bg-slate-100 rounded mt-1">
                            <div class="h-2 bg-blue-500 rounded" style="width: {{ $total ? round($valor / $total * 100) : 0 }}%"></div>
                        </div>
                    </div>
                @endif
            @empty
                <p class="text-sm text-slate-500">Sin datos en el rango.</p>
            @endforelse
        </div>

        {{-- Canal de ingreso --}}
        <div class="bg-white border rounded p-4">
            <h2 class="font-semibold text-slate-800 mb-3">¿Por dónde entran los casos?</h2>
            @forelse ($porCanal as $canal => $valor)
                <div class="mb-2">
                    <div class="flex justify-between text-sm text-slate-700">
                        <span>{{ \App\Models\Soporte::CANALES[$canal] ?? $canal }}</span>
                        <span class="font-medium">{{ $valor }}</span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded mt-1">
                        <div class="h-2 bg-emerald-500 rounded" style="width: {{ $total ? round($valor / $total * 100) : 0 }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">Sin datos en el rango.</p>
            @endforelse
            <p class="text-xs text-slate-500 mt-3">
                Solo se registran los casos que el primer nivel no pudo resolver en el momento.
            </p>
        </div>

        {{-- Tipos --}}
        <div class="bg-white border rounded p-4">
            <h2 class="font-semibold text-slate-800 mb-3">Casos por tipo de solicitud</h2>
            @forelse ($porTipo as $tipo => $valor)
                <div class="mb-2">
                    <div class="flex justify-between text-sm text-slate-700">
                        <span>{{ \App\Models\Soporte::TIPOS_SOLICITUD[$tipo] ?? $tipo }}</span>
                        <span class="font-medium">{{ $valor }}</span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded mt-1">
                        <div class="h-2 bg-indigo-500 rounded" style="width: {{ $total ? round($valor / $total * 100) : 0 }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">Sin datos en el rango.</p>
            @endforelse
        </div>

        {{-- Diagnósticos --}}
        <div class="bg-white border rounded p-4">
            <h2 class="font-semibold text-slate-800 mb-3">Diagnósticos más frecuentes</h2>
            @forelse ($topDiagnosticos as $nombre => $valor)
                <div class="flex justify-between text-sm text-slate-700 py-1 border-b last:border-0">
                    <span>{{ $nombre }}</span>
                    <span class="font-medium">{{ $valor }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">Todavía no hay casos diagnosticados en este rango.</p>
            @endforelse
        </div>

        {{-- Carga por técnico --}}
        <div class="bg-white border rounded p-4 overflow-x-auto lg:col-span-2">
            <h2 class="font-semibold text-slate-800 mb-3">Carga por técnico de soporte</h2>
            @if ($porTecnico->count())
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b">
                            <th class="py-1 font-medium">Técnico</th>
                            <th class="py-1 font-medium text-right">Casos</th>
                            <th class="py-1 font-medium text-right">Resueltos</th>
                            <th class="py-1 font-medium text-right">Prom. min hábiles</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($porTecnico as $fila)
                            <tr class="border-b last:border-0">
                                <td class="py-1">{{ $fila->name }}</td>
                                <td class="py-1 text-right">{{ $fila->total }}</td>
                                <td class="py-1 text-right">{{ $fila->solucionados }}</td>
                                <td class="py-1 text-right">{{ $fila->promedio ? round($fila->promedio) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-sm text-slate-500">Sin casos asignados en el rango.</p>
            @endif
        </div>
    </div>
</div>
