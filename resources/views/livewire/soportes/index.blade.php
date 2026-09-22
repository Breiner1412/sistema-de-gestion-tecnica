<?php

use App\Models\Soporte;
use App\Models\User;
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

    public function with(): array
    {
        $consulta = Soporte::query()
            ->with([
                // withCount en la relación evita una consulta por fila.
                'cliente' => fn($q) => $q->select('id', 'nombre', 'codigo_abonado')->withCount('soportes'),
                'usuarioRegistra:id,name',
                'tecnicoSoporte:id,name',
                'diagnostico:id,nombre',
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
        $consulta = $this->vista === ''
            ? $consulta->latest('id')
            : $consulta->orderByRaw('sla_pausado_at is not null')->orderBy('sla_vence_at')->latest('id');

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

<div class="p-6">
    @if (session('mensaje'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('mensaje') }}</div>
    @endif

    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold text-slate-800">Bandeja de soportes</h1>
        <a href="{{ route('soportes.create') }}" wire:navigate class="bg-blue-600 text-white px-4 py-2 rounded">
            + Nuevo soporte
        </a>
    </div>

    {{-- Vistas rápidas --}}
    <div class="flex flex-wrap gap-2 mb-4">
        @php
            $vistas = [
                '' => ['Todos', null],
                'mios' => ['Míos', $conteos['mios']],
                'abiertos' => ['Abiertos', $conteos['abiertos']],
                'inmediatos' => ['Inmediatos', $conteos['inmediatos']],
                'vencidos' => ['Vencidos', $conteos['vencidos']],
            ];
        @endphp

        @foreach ($vistas as $clave => [$etiqueta, $conteo])
            <button wire:click="$set('vista', '{{ $clave }}')" @class([
                'px-3 py-1.5 rounded text-sm border',
                'bg-slate-800 text-white border-slate-800' => $vista === $clave,
                'bg-white text-slate-700 hover:bg-slate-50' => $vista !== $clave,
                'border-red-300 text-red-700' => $vista !== $clave && $clave === 'vencidos' && $conteo > 0,
            ])>
                {{ $etiqueta }}
                @if ($conteo !== null)
                    <span class="ml-1 opacity-70">{{ $conteo }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Filtros --}}
    <div class="bg-white border rounded p-4 mb-4 grid gap-3 md:grid-cols-5">
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Buscar</label>
            <input type="search" wire:model.live.debounce.400ms="buscar" class="w-full border rounded p-2 text-sm"
                placeholder="N° soporte, cliente, cédula o abonado">
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Estado</label>
            <select wire:model.live="estado" class="w-full border rounded p-2 text-sm">
                <option value="">Todos</option>
                @foreach (\App\Models\Soporte::ESTADOS as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Tipo</label>
            <select wire:model.live="tipo" class="w-full border rounded p-2 text-sm">
                <option value="">Todos</option>
                @foreach (\App\Models\Soporte::TIPOS_SOLICITUD as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Canal</label>
            <select wire:model.live="canal" class="w-full border rounded p-2 text-sm">
                <option value="">Todos</option>
                @foreach (\App\Models\Soporte::CANALES as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Responsable</label>
            <select wire:model.live="responsable" class="w-full border rounded p-2 text-sm">
                <option value="">Todos</option>
                @foreach ($responsables as $r)
                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-3 flex items-center gap-4">
            <button wire:click="limpiarFiltros" class="text-sm text-blue-600">Limpiar filtros</button>
            <span class="text-sm text-slate-500 ml-auto">{{ $soportes->total() }} caso(s)</span>
        </div>
    </div>

    <div class="overflow-x-auto bg-white border rounded shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-700 text-white">
                <tr>
                    <th class="p-2 text-left font-semibold">N°</th>
                    <th class="p-2 text-left font-semibold">Cliente</th>
                    <th class="p-2 text-left font-semibold">Tipo</th>
                    <th class="p-2 text-left font-semibold">Canal</th>
                    <th class="p-2 text-left font-semibold">Estado</th>
                    <th class="p-2 text-left font-semibold">SLA</th>
                    <th class="p-2 text-left font-semibold">Responsable</th>
                    <th class="p-2 text-left font-semibold">Ingreso</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($soportes as $soporte)
                    @php $semaforo = $soporte->semaforoSla(); @endphp
                    <tr class="hover:bg-slate-50 cursor-pointer"
                        onclick="window.location='{{ route('soportes.show', $soporte) }}'">
                        <td class="p-2 font-mono text-xs text-slate-600">
                            {{ $soporte->numero_soporte ?? $soporte->id }}
                            @if ($soporte->esInmediato())
                                <span class="block mt-0.5 text-[10px] font-sans font-semibold text-red-700">INMEDIATO</span>
                            @endif
                        </td>
                        <td class="p-2">
                            {{ $soporte->cliente->nombre ?? '—' }}
                            @if ($soporte->cliente?->codigo_abonado)
                                <span class="block text-xs text-slate-500">{{ $soporte->cliente->codigo_abonado }}</span>
                            @endif
                            @if (($soporte->cliente->soportes_count ?? 0) >= $minimoRecurrencia)
                                <span class="inline-block mt-1 px-1.5 py-0.5 rounded bg-amber-200 text-amber-900 text-[10px] font-semibold">
                                    Recurrente · {{ $soporte->cliente->soportes_count }}
                                </span>
                            @endif
                        </td>
                        <td class="p-2 text-slate-600">{{ $soporte->etiquetaTipo() }}</td>
                        <td class="p-2 text-slate-600 text-xs">{{ $soporte->etiquetaCanal() }}</td>
                        <td class="p-2">
                            <span @class([
                                'px-2 py-1 rounded text-xs whitespace-nowrap',
                                'bg-yellow-200 text-yellow-900' => $soporte->estado === 'pendiente',
                                'bg-blue-200 text-blue-900' => $soporte->estado === 'en_proceso',
                                'bg-cyan-200 text-cyan-900' => $soporte->estado === 'seguimiento',
                                'bg-purple-200 text-purple-900' => $soporte->estado === 'enviado_tecnico',
                                'bg-slate-800 text-white' => $soporte->estado === 'escalado_n3',
                                'bg-orange-200 text-orange-900' => $soporte->estado === 'sin_contacto',
                                'bg-green-200 text-green-900' => $soporte->estado === 'solucionado',
                                'bg-red-200 text-red-900' => $soporte->estado === 'cancelado',
                                'bg-slate-200 text-slate-700' => $soporte->estado === 'cerrado_sin_contacto',
                            ])>{{ $soporte->etiquetaEstado() }}</span>
                        </td>
                        <td class="p-2 whitespace-nowrap">
                            @if ($soporte->estaCerrado())
                                <span class="text-xs text-slate-400">—</span>
                            @else
                                <span class="inline-flex items-center gap-1.5">
                                    <span @class([
                                        'inline-block w-2 h-2 rounded-full',
                                        'bg-green-500' => $semaforo === 'ok',
                                        'bg-yellow-500' => $semaforo === 'atencion',
                                        'bg-orange-500' => $semaforo === 'riesgo',
                                        'bg-red-600' => $semaforo === 'vencido',
                                        'bg-slate-400' => $semaforo === 'pausado',
                                    ])></span>
                                    <span @class([
                                        'text-xs',
                                        'text-red-700 font-semibold' => $semaforo === 'vencido',
                                        'text-slate-500' => $semaforo === 'pausado',
                                        'text-slate-700' => !in_array($semaforo, ['vencido', 'pausado']),
                                    ])>
                                        {{ $semaforo === 'pausado' ? 'en pausa' : $soporte->restanteLegible() }}
                                    </span>
                                </span>
                            @endif
                        </td>
                        <td class="p-2 text-slate-600">{{ $soporte->tecnicoSoporte->name ?? 'Sin asignar' }}</td>
                        <td class="p-2 text-slate-500 text-xs whitespace-nowrap">
                            {{ $soporte->created_at->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-6 text-center text-slate-500">
                            No hay casos que coincidan con los filtros.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $soportes->links() }}</div>

    <p class="mt-3 text-xs text-slate-500">
        El reloj de SLA corre solo en horario hábil y se detiene cuando el caso está esperando
        una visita programada, la respuesta de redes o que el cliente conteste.
    </p>
</div>
