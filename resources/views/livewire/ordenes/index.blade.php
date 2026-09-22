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

        // El técnico de campo solo ve sus propias visitas.
        if ($usuario->tieneRol(\App\Models\User::ROL_TECNICO_CAMPO) && $usuario->tecnico) {
            $query->where('tecnico_id', $usuario->tecnico->id);
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

<div class="p-6">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold text-slate-800">
            {{ $esTecnico ? 'Mis visitas' : 'Visitas programadas' }}
        </h1>

        <select wire:model.live="estado" class="border rounded p-2 text-sm">
            <option value="">Todos los estados</option>
            @foreach (\App\Models\OrdenTrabajo::ESTADOS as $valor => $etiqueta)
                <option value="{{ $valor }}">{{ $etiqueta }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto bg-white border rounded shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-700 text-white">
                <tr>
                    <th class="p-2 text-left font-semibold">Orden</th>
                    <th class="p-2 text-left font-semibold">Soporte</th>
                    <th class="p-2 text-left font-semibold">Cliente</th>
                    @unless ($esTecnico)
                        <th class="p-2 text-left font-semibold">Técnico</th>
                    @endunless
                    <th class="p-2 text-left font-semibold">Programada</th>
                    <th class="p-2 text-left font-semibold">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($ordenes as $orden)
                    <tr class="hover:bg-slate-50 cursor-pointer"
                        onclick="window.location='{{ route('ordenes.show', $orden) }}'">
                        <td class="p-2 font-mono text-xs">#{{ $orden->id }}</td>
                        <td class="p-2 font-mono text-xs text-slate-600">
                            {{ $orden->soporte->numero_soporte ?? '#'.$orden->soporte_id }}
                        </td>
                        <td class="p-2">{{ $orden->soporte->cliente->nombre ?? '—' }}</td>
                        @unless ($esTecnico)
                            <td class="p-2 text-slate-600">{{ $orden->tecnico->user->name ?? $orden->tecnico->nombre }}</td>
                        @endunless
                        <td class="p-2 text-xs whitespace-nowrap">
                            @if ($orden->fecha_programada)
                                <span @class(['font-semibold text-indigo-700' => $orden->fecha_programada->isToday()])>
                                    {{ $orden->fecha_programada->isToday() ? 'Hoy' : $orden->fecha_programada->format('d/m/Y') }}
                                </span>
                                <span class="block text-slate-500">
                                    {{ \App\Models\OrdenTrabajo::FRANJAS[$orden->franja] ?? '' }}
                                </span>
                            @else
                                <span class="text-slate-400">Sin programar</span>
                            @endif
                        </td>
                        <td class="p-2">
                            <span @class([
                                'px-2 py-1 rounded text-xs whitespace-nowrap',
                                'bg-yellow-200 text-yellow-900' => $orden->estado === 'pendiente',
                                'bg-indigo-200 text-indigo-900' => $orden->estado === 'programada',
                                'bg-blue-200 text-blue-900' => $orden->estado === 'en_progreso',
                                'bg-green-200 text-green-900' => $orden->estado === 'completado',
                                'bg-red-200 text-red-900' => $orden->estado === 'no_realizada',
                            ])>{{ $orden->etiquetaEstado() }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center text-slate-500">No hay órdenes.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $ordenes->links() }}</div>
</div>
