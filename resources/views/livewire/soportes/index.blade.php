<?php

use App\Models\Soporte;
use App\Models\User;
use App\Support\ExportadorCsv;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $estado = '';

    #[Url(except: '')]
    public string $tipo = '';

    #[Url(except: '')]
    public string $canal = '';

    #[Url(except: '')]
    public string $responsable = '';

    #[Url(except: '')]
    public string $vista = ''; // '', 'mios', 'abiertos', 'vencidos', 'inmediatos'

    public function mount(): void
    {
        $this->authorize('viewAny', Soporte::class);
    }

    public function updated($propiedad): void
    {
        if ($propiedad !== 'page') {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'estado', 'tipo', 'canal', 'responsable', 'vista']);
        $this->resetPage();
    }

    /**
     * La consulta filtrada, sin paginar. La comparten el listado y la
     * exportación para que el CSV traiga exactamente lo que se ve en pantalla.
     */
    private function consulta()
    {
        $consulta = Soporte::query()
            ->with([
                // withCount en la relación evita una consulta por fila.
                'cliente' => fn($q) => $q->select('id', 'nombre', 'codigo_abonado')->withCount('soportes'),
                'usuarioRegistra:id,name',
                'tecnicoSoporte:id,name',
                'diagnostico:id,nombre',
                'tipoFalla:id,nombre',
            ])
            ->buscar($this->buscar)
            ->when($this->estado, fn($q) => $q->where('estado', $this->estado))
            ->when($this->tipo, fn($q) => $q->where('tipo_solicitud', $this->tipo))
            ->when($this->canal, fn($q) => $q->where('canal_ingreso', $this->canal))
            ->when($this->responsable, fn($q) => $q->where('tecnico_soporte_id', $this->responsable));

        $consulta = match ($this->vista) {
            'mios' => $consulta->where('tecnico_soporte_id', auth()->id())->abiertos(),
            'abiertos' => $consulta->abiertos(),
            'vencidos' => $consulta->vencidos(),
            'inmediatos' => $consulta->abiertos()->where('criticidad', Soporte::CRITICIDAD_INMEDIATA),
            default => $consulta,
        };

        // Los casos abiertos se ordenan por urgencia real, no por fecha.
        return $this->vista === ''
            ? $consulta->latest('id')
            : $consulta->orderByRaw('sla_pausado_at is not null')->orderBy('sla_vence_at')->latest('id');
    }

    /** Descarga los casos filtrados, fila por fila. */
    public function exportar()
    {
        $this->authorize('exportar', Soporte::class);

        return ExportadorCsv::descargar(
            ExportadorCsv::nombre('soportes'),
            ['N° soporte', 'Ingreso', 'Abonado', 'Cliente', 'Tipo', 'Canal', 'Criticidad',
             'Servicio', 'Falla reportada', 'Diagnóstico', 'Estado', 'Responsable',
             'Registrado por', 'Escalado a N3', 'Cierre', 'Respuesta (min)', 'Resolución (min)'],
            $this->filasParaExportar(),
        );
    }

    private function filasParaExportar(): \Generator
    {
        foreach ($this->consulta()->lazy(500) as $soporte) {
            yield [
                $soporte->numero_soporte,
                $soporte->created_at,
                $soporte->cliente->codigo_abonado ?? '',
                $soporte->cliente->nombre ?? '',
                $soporte->etiquetaTipo(),
                $soporte->etiquetaCanal(),
                Soporte::CRITICIDADES[$soporte->criticidad] ?? $soporte->criticidad,
                Soporte::SERVICIOS[$soporte->servicio_afectado] ?? '',
                $soporte->tipoFalla->nombre ?? '',
                $soporte->diagnostico->nombre ?? '',
                $soporte->etiquetaEstado(),
                $soporte->tecnicoSoporte->name ?? '',
                $soporte->usuarioRegistra->name ?? '',
                (bool) $soporte->escalado_nivel_3,
                $soporte->fecha_cierre,
                $soporte->tiempo_respuesta,
                $soporte->tiempo_resolucion,
            ];
        }
    }

    public function with(): array
    {
        $consulta = $this->consulta();

        return [
            'soportes' => $consulta->paginate(25),
            'responsables' => User::tecnicosSoporte()->get(['id', 'name']),
            'minimoRecurrencia' => (int) config('sla.recurrencia.minimo_en_ventana_larga', 3),
            'conteos' => [
                'abiertos' => Soporte::abiertos()->count(),
                'vencidos' => Soporte::vencidos()->count(),
                'mios' => Soporte::abiertos()->where('tecnico_soporte_id', auth()->id())->count(),
                'inmediatos' => Soporte::abiertos()->where('criticidad', Soporte::CRITICIDAD_INMEDIATA)->count(),
            ],
        ];
    }
}; ?>

<div class="mx-auto flex max-w-[1400px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
    <x-mensajes />

    <x-encabezado titulo="Bandeja de casos" subtitulo="Los abiertos se ordenan por lo que vence primero.">
        <x-slot:acciones>
            <x-boton wire:click="exportar" wire:loading.attr="disabled">
                <svg width="15" height="15" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 2v8m0 0 3-3m-3 3L5 7M2.5 11v2.5h11V11" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                <span wire:loading.remove wire:target="exportar">Exportar CSV</span>
                <span wire:loading wire:target="exportar">Generando…</span>
            </x-boton>
            <x-boton variante="primario" :href="route('soportes.create')" wire:navigate>
                <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
                Nuevo caso
            </x-boton>
        </x-slot:acciones>
    </x-encabezado>

    {{-- Vistas rápidas --}}
    @php
        $vistas = [
            '' => ['Todos', null],
            'mios' => ['Míos', $conteos['mios']],
            'abiertos' => ['Abiertos', $conteos['abiertos']],
            'inmediatos' => ['Inmediatos', $conteos['inmediatos']],
            'vencidos' => ['Vencidos', $conteos['vencidos']],
        ];
    @endphp
    <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <div class="inline-flex gap-1 rounded-tarjeta border border-linea bg-superficie p-1" role="tablist" aria-label="Vistas de la bandeja">
            @foreach ($vistas as $clave => [$etiqueta, $conteo])
                <button type="button" role="tab" wire:click="$set('vista', '{{ $clave }}')" aria-selected="{{ $vista === $clave ? 'true' : 'false' }}"
                    @class([
                        'inline-flex h-8 items-center gap-1.5 whitespace-nowrap rounded-control px-3 text-[13px] font-medium transition-colors',
                        'bg-menu text-white dark:bg-elevada dark:text-tinta' => $vista === $clave,
                        'text-tinta-2 hover:bg-elevada' => $vista !== $clave,
                    ])>
                    @if ($clave === 'vencidos')
                        <x-sla-glifo estado="vencido" :tamano="11" />
                    @endif
                    {{ $etiqueta }}
                    @if ($conteo !== null)
                        <span @class([
                            'font-mono text-xs',
                            'rounded-[3px] bg-sla-vencido px-1 font-semibold text-white' => $clave === 'vencidos' && $conteo > 0,
                            'opacity-70' => ! ($clave === 'vencidos' && $conteo > 0),
                        ])>{{ $conteo }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- Filtros --}}
    <div class="tarjeta grid grid-cols-2 gap-3 p-3 lg:grid-cols-[minmax(0,2fr)_repeat(4,minmax(0,1fr))]">
        <div class="col-span-2 lg:col-span-1">
            <label for="buscar" class="etiqueta text-[13px]">Buscar</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-tinta-3" width="15" height="15" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor" stroke-width="1.5"></circle><path d="m10.5 10.5 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>
                <input id="buscar" type="search" wire:model.live.debounce.400ms="buscar" class="control h-9 pl-8"
                    placeholder="N° de caso, cliente, cédula o abonado">
            </div>
        </div>

        <div>
            <label for="f-estado" class="etiqueta text-[13px]">Estado</label>
            <select id="f-estado" wire:model.live="estado" class="control h-9 py-0">
                <option value="">Todos</option>
                @foreach (\App\Models\Soporte::ESTADOS as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="f-tipo" class="etiqueta text-[13px]">Tipo</label>
            <select id="f-tipo" wire:model.live="tipo" class="control h-9 py-0">
                <option value="">Todos</option>
                @foreach (\App\Models\Soporte::TIPOS_SOLICITUD as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="f-canal" class="etiqueta text-[13px]">Canal</label>
            <select id="f-canal" wire:model.live="canal" class="control h-9 py-0">
                <option value="">Todos</option>
                @foreach (\App\Models\Soporte::CANALES as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="f-responsable" class="etiqueta text-[13px]">Responsable</label>
            <select id="f-responsable" wire:model.live="responsable" class="control h-9 py-0">
                <option value="">Todos</option>
                @foreach ($responsables as $r)
                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="flex items-center justify-between gap-3 text-[13px]">
        <span class="text-tinta-3"><span class="font-mono text-tinta">{{ $soportes->total() }}</span> {{ $soportes->total() === 1 ? 'caso' : 'casos' }}</span>
        <x-boton variante="fantasma" tamano="chico" wire:click="limpiarFiltros">Limpiar filtros</x-boton>
    </div>

    {{-- Escritorio: tabla densa. --}}
    <x-tabla minimo="980px" class="hidden md:block">
        <thead>
            <tr>
                <th class="w-[160px]">Caso</th>
                <th>Cliente</th>
                <th>Estado</th>
                <th class="w-[170px]">SLA</th>
                <th>Tipo · canal</th>
                <th>Responsable</th>
                <th class="text-right">Ingreso</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($soportes as $soporte)
                <tr wire:key="fila-{{ $soporte->id }}" class="cursor-pointer"
                    onclick="window.location='{{ route('soportes.show', $soporte) }}'">
                    <td>
                        <div class="flex flex-col items-start gap-0.5">
                            <a href="{{ route('soportes.show', $soporte) }}" class="whitespace-nowrap font-mono font-medium text-tinta no-underline hover:text-acento-texto hover:underline">
                                {{ $soporte->numero_soporte ?? $soporte->id }}
                            </a>
                            @if ($soporte->esInmediato())
                                <x-inmediata />
                            @endif
                        </div>
                    </td>
                    <td class="max-w-[260px]">
                        <div class="flex items-center gap-2">
                            <span class="truncate font-medium">{{ $soporte->cliente->nombre ?? '—' }}</span>
                            @if (($soporte->cliente->soportes_count ?? 0) >= $minimoRecurrencia)
                                <span class="shrink-0 rounded-[3px] bg-recurrente-fondo px-1.5 text-[11px] font-semibold leading-[18px] text-recurrente" title="Cliente recurrente: casos registrados">
                                    ↻ {{ $soporte->cliente->soportes_count }}
                                </span>
                            @endif
                        </div>
                        @if ($soporte->cliente?->codigo_abonado)
                            <span class="block font-mono text-xs text-tinta-3">{{ $soporte->cliente->codigo_abonado }}</span>
                        @endif
                    </td>
                    <td><x-estado :estado="$soporte->estado" :etiqueta="$soporte->etiquetaEstado()" /></td>
                    <td><x-sla :soporte="$soporte" /></td>
                    <td class="text-tinta-2">
                        {{ $soporte->etiquetaTipo() }}
                        <span class="block text-xs text-tinta-3">{{ $soporte->etiquetaCanal() }}</span>
                    </td>
                    <td @class(['text-tinta-2' => $soporte->tecnicoSoporte, 'italic text-tinta-3' => ! $soporte->tecnicoSoporte])>{{ $soporte->tecnicoSoporte->name ?? 'Sin asignar' }}</td>
                    <td class="whitespace-nowrap text-right font-mono text-xs text-tinta-3">
                        {{ $soporte->created_at->format('d/m/Y') }}
                        <span class="block">{{ $soporte->created_at->format('H:i') }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="!h-24 text-center text-tinta-3">No hay casos que coincidan con los filtros.</td>
                </tr>
            @endforelse
        </tbody>
    </x-tabla>

    {{-- Celular: una tarjeta por caso, con el semáforo arriba. --}}
    <ul class="flex flex-col gap-2 md:hidden">
        @forelse ($soportes as $soporte)
            <li wire:key="tarjeta-{{ $soporte->id }}">
                <a href="{{ route('soportes.show', $soporte) }}" @class([
                    'tarjeta block p-3 text-tinta no-underline transition-colors hover:bg-elevada',
                    'border-l-[3px] border-l-sla-vencido' => ! $soporte->estaCerrado() && $soporte->semaforoSla() === 'vencido',
                ])>
                    <div class="flex items-start justify-between gap-2">
                        <x-sla :soporte="$soporte" />
                        <span class="font-mono text-xs text-tinta-3">{{ $soporte->numero_soporte ?? $soporte->id }}</span>
                    </div>
                    <p class="mt-2 flex items-center gap-2 text-[15px] font-semibold">
                        <span class="truncate">{{ $soporte->cliente->nombre ?? '—' }}</span>
                        @if ($soporte->esInmediato())
                            <x-inmediata class="shrink-0" />
                        @endif
                    </p>
                    <p class="text-[13px] text-tinta-3">
                        {{ $soporte->etiquetaTipo() }} · {{ $soporte->tecnicoSoporte->name ?? 'Sin asignar' }}
                        @if (($soporte->cliente->soportes_count ?? 0) >= $minimoRecurrencia)
                            · <span class="font-semibold text-recurrente">↻ {{ $soporte->cliente->soportes_count }} casos</span>
                        @endif
                    </p>
                    <div class="mt-2"><x-estado :estado="$soporte->estado" :etiqueta="$soporte->etiquetaEstado()" /></div>
                </a>
            </li>
        @empty
            <li class="tarjeta p-6 text-center text-sm text-tinta-3">No hay casos que coincidan con los filtros.</li>
        @endforelse
    </ul>

    <div>{{ $soportes->links() }}</div>

    <p class="flex items-start gap-2 text-xs text-tinta-3">
        <svg class="mt-px shrink-0" width="13" height="13" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6.2" fill="none" stroke="currentColor" stroke-width="1.4"></circle><path d="M8 4.8V8l2.2 1.4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"></path></svg>
        El reloj de SLA corre solo en horario hábil y se detiene cuando el caso está esperando
        una visita programada, la respuesta de redes o que el cliente conteste.
    </p>
</div>
