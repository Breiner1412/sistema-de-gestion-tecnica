{{-- Visitas de campo e historial de estados del caso. --}}

{{-- Visitas --}}
@if ($soporte->ordenesTrabajo->count())
    <section class="tarjeta p-4">
        <h2 class="titulo-seccion mb-2">Visitas</h2>
        <ul class="divide-y divide-linea-suave text-sm">
            @foreach ($soporte->ordenesTrabajo as $orden)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                    <div class="min-w-0">
                        <p class="font-medium">
                            <span class="font-mono text-tinta-3">#{{ $orden->id }}</span>
                            {{ $orden->tecnico->user->name ?? $orden->tecnico->nombre }}
                        </p>
                        @if ($orden->fecha_programada)
                            <p class="text-[13px] text-tinta-3">
                                <span class="font-mono">{{ $orden->fecha_programada->format('d/m/Y') }}</span>
                                {{ \App\Models\OrdenTrabajo::FRANJAS[$orden->franja] ?? '' }}
                            </p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <x-estado :estado="$orden->estado" :etiqueta="$orden->etiquetaEstado()" />
                        <x-boton tamano="chico" :href="route('ordenes.show', $orden)" wire:navigate>Abrir</x-boton>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@endif

{{-- Historial --}}
@if ($soporte->historialEstados->count())
    <section class="tarjeta p-4">
        <h2 class="titulo-seccion mb-3">Historial</h2>
        <ol class="relative ml-1 space-y-4 border-l border-linea pl-5 text-sm">
            @foreach ($soporte->historialEstados as $h)
                <li class="relative">
                    <span class="absolute -left-[25px] top-1.5 size-[9px] rounded-full border-2 border-superficie bg-tinta-3" aria-hidden="true"></span>
                    <p>
                        <span class="font-semibold">{{ \App\Models\Soporte::ESTADOS[$h->estado_nuevo] ?? $h->estado_nuevo }}</span>
                        @if ($h->estado_anterior && $h->estado_anterior !== $h->estado_nuevo)
                            <span class="text-tinta-3">desde {{ \App\Models\Soporte::ESTADOS[$h->estado_anterior] ?? $h->estado_anterior }}</span>
                        @endif
                    </p>
                    <p class="text-xs text-tinta-3">
                        {{ $h->usuario->name ?? 'Sistema' }} · <span class="font-mono">{{ $h->created_at->format('d/m/Y H:i') }}</span>
                    </p>
                    @if ($h->motivo)
                        <p class="mt-1 text-[13px] text-tinta-2">{{ $h->motivo }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
@endif
