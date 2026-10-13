{{-- Visitas de campo e historial de estados del caso. --}}

{{-- Visitas --}}
@if ($soporte->ordenesTrabajo->count())
    <div class="bg-white border rounded p-4 mb-4">
        <h2 class="font-semibold text-slate-800 mb-2">Visitas</h2>
        <ul class="text-sm divide-y">
            @foreach ($soporte->ordenesTrabajo as $orden)
                <li class="py-2 flex justify-between items-center">
                    <span>
                        #{{ $orden->id }} · {{ $orden->tecnico->user->name ?? $orden->tecnico->nombre }}
                        @if ($orden->fecha_programada)
                            · {{ $orden->fecha_programada->format('d/m/Y') }}
                            {{ \App\Models\OrdenTrabajo::FRANJAS[$orden->franja] ?? '' }}
                        @endif
                        <span class="text-slate-500">({{ $orden->etiquetaEstado() }})</span>
                    </span>
                    <a href="{{ route('ordenes.show', $orden) }}" wire:navigate class="text-blue-600">Abrir</a>
                </li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Historial --}}
@if ($soporte->historialEstados->count())
    <div class="bg-white border rounded p-4">
        <h2 class="font-semibold text-slate-800 mb-2">Historial</h2>
        <ul class="space-y-2 text-sm">
            @foreach ($soporte->historialEstados as $h)
                <li class="border-l-2 border-slate-300 pl-3">
                    <p class="text-slate-700">
                        <span class="font-medium">{{ \App\Models\Soporte::ESTADOS[$h->estado_nuevo] ?? $h->estado_nuevo }}</span>
                        @if ($h->estado_anterior && $h->estado_anterior !== $h->estado_nuevo)
                            <span class="text-slate-400">(desde {{ \App\Models\Soporte::ESTADOS[$h->estado_anterior] ?? $h->estado_anterior }})</span>
                        @endif
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ $h->usuario->name ?? 'Sistema' }} · {{ $h->created_at->format('d/m/Y H:i') }}
                    </p>
                    @if ($h->motivo)
                        <p class="text-xs text-slate-600 mt-1">{{ $h->motivo }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
