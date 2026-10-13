<?php

use App\Models\OrdenTrabajo;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $estado = '';

    public function mount(): void
    {
        $this->authorize('viewAny', OrdenTrabajo::class);
    }

    public function updated($propiedad): void
    {
        if ($propiedad !== 'page') {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        $usuario = auth()->user();

        $query = OrdenTrabajo::query()
            ->with(['soporte.cliente:id,nombre,codigo_abonado', 'tecnico.user:id,name'])
            ->when($this->estado, fn($q) => $q->where('estado', $this->estado));

        // El técnico de campo solo ve sus propias visitas. Sin ficha de técnico
        // no ve ninguna: antes, en ese caso, veía las de todos.
        if ($usuario->tieneRol(\App\Models\User::ROL_TECNICO_CAMPO)) {
            $query->delTecnicoUsuario($usuario);
        }

        return [
            'ordenes' => $query->orderByRaw('fecha_programada is null')
                ->orderBy('fecha_programada')
                ->latest('id')
                ->paginate(25),
            'esTecnico' => $usuario->tieneRol(\App\Models\User::ROL_TECNICO_CAMPO),
        ];
    }
}; ?>

<div class="mx-auto flex max-w-[1400px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
    <x-encabezado :titulo="$esTecnico ? 'Mis visitas' : 'Visitas programadas'" subtitulo="Órdenes de trabajo en terreno.">
        <x-slot:acciones>
            <label for="f-estado" class="sr-only">Estado</label>
            <select id="f-estado" wire:model.live="estado" class="control h-9 w-auto py-0 pr-9">
                <option value="">Todos los estados</option>
                @foreach (\App\Models\OrdenTrabajo::ESTADOS as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
        </x-slot:acciones>
    </x-encabezado>

    <x-tabla minimo="720px">
        <thead>
            <tr>
                <th>Orden</th>
                <th>Caso</th>
                <th>Cliente</th>
                @unless ($esTecnico)
                    <th>Técnico</th>
                @endunless
                <th>Programada</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ordenes as $orden)
                <tr wire:key="orden-{{ $orden->id }}" class="cursor-pointer"
                    onclick="window.location='{{ route('ordenes.show', $orden) }}'">
                    <td>
                        <a href="{{ route('ordenes.show', $orden) }}" class="font-mono font-medium text-tinta no-underline hover:text-acento-texto hover:underline">#{{ $orden->id }}</a>
                    </td>
                    <td class="whitespace-nowrap font-mono text-tinta-2">
                        {{ $orden->soporte->numero_soporte ?? '#'.$orden->soporte_id }}
                    </td>
                    <td class="font-medium">{{ $orden->soporte->cliente->nombre ?? '—' }}</td>
                    @unless ($esTecnico)
                        <td class="text-tinta-2">{{ $orden->tecnico->user->name ?? $orden->tecnico->nombre }}</td>
                    @endunless
                    <td class="whitespace-nowrap">
                        @if ($orden->fecha_programada)
                            @if ($orden->fecha_programada->isToday())
                                <span class="rounded-[3px] bg-acento-tinte px-1.5 text-xs font-semibold text-acento-tinte-texto">Hoy</span>
                            @else
                                <span class="font-mono text-xs">{{ $orden->fecha_programada->format('d/m/Y') }}</span>
                            @endif
                            <span class="block text-xs text-tinta-3">{{ \App\Models\OrdenTrabajo::FRANJAS[$orden->franja] ?? '' }}</span>
                        @else
                            <span class="text-xs italic text-tinta-3">Sin programar</span>
                        @endif
                    </td>
                    <td><x-estado :estado="$orden->estado" :etiqueta="$orden->etiquetaEstado()" /></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="!h-24 text-center text-tinta-3">No hay órdenes.</td>
                </tr>
            @endforelse
        </tbody>
    </x-tabla>

    <div>{{ $ordenes->links() }}</div>
</div>
