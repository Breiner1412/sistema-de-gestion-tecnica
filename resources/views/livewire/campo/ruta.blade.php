<?php

use App\Models\OrdenTrabajo;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.campo')] class extends Component {
    #[Url(except: 'hoy')]
    public string $cuando = 'hoy';

    public function mount(): void
    {
        $this->authorize('viewAny', OrdenTrabajo::class);
    }

    /**
     * Las visitas del técnico que abrió la pantalla.
     *
     * Un coordinador que entre aquí ve la ruta completa del día: es la misma
     * pantalla, pero sin filtrar por técnico. No hay una segunda vista para eso.
     */
    private function consulta()
    {
        $usuario = auth()->user();

        $query = OrdenTrabajo::query()
            ->with(['soporte.cliente:id,nombre,telefono,direccion', 'soporte:id,cliente_id,numero_soporte,descripcion,criticidad'])
            ->abiertas();

        if ($usuario->tieneRol(User::ROL_TECNICO_CAMPO)) {
            $query->delTecnicoUsuario($usuario);
        }

        return match ($this->cuando) {
            'manana' => $query->delDiaEn('fecha_programada', today()->addDay()),
            'pendientes' => $query->where(fn($q) => $q
                ->whereNull('fecha_programada')
                ->orWhere('fecha_programada', '<', today()->startOfDay())),
            default => $query->delDiaEn('fecha_programada', today()),
        };
    }

    public function with(): array
    {
        $visitas = $this->consulta()
            // La mañana antes que la tarde, y dentro de cada franja las más
            // viejas primero: el caso que lleva más días esperando va de primero.
            ->orderByRaw("case when franja = 'manana' then 0 when franja = 'tarde' then 1 else 2 end")
            ->orderBy('id')
            ->get();

        return [
            'visitas' => $visitas,
            'hechasHoy' => OrdenTrabajo::query()
                ->when(
                    auth()->user()->tieneRol(User::ROL_TECNICO_CAMPO),
                    fn($q) => $q->delTecnicoUsuario(auth()->user()),
                )
                ->whereIn('estado', OrdenTrabajo::ESTADOS_FINALES)
                ->delDiaEn('hora_fin', today())
                ->count(),
        ];
    }
}; ?>

<div class="px-4 py-4">

    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-[26px] font-semibold leading-tight">
                {{ $cuando === 'manana' ? 'Mañana' : ($cuando === 'pendientes' ? 'Atrasadas' : 'Hoy') }}
            </h1>
            <p class="text-sm text-tinta-3">
                <span class="font-mono text-tinta">{{ $visitas->count() }}</span> {{ $visitas->count() === 1 ? 'visita' : 'visitas' }}
                @if ($hechasHoy > 0)
                    · <span class="font-mono text-tinta">{{ $hechasHoy }}</span> cerrada{{ $hechasHoy === 1 ? '' : 's' }} hoy
                @endif
            </p>
        </div>

        {{-- Modo noche: preferencia aparte de la del escritorio. Por defecto
             claro, que es lo que se lee al sol. --}}
        <button type="button" x-data="selectorTema(@js(route('preferencias.tema-campo')))"
                x-on:click="elegir(tema === 'oscuro' ? 'claro' : 'oscuro')"
                :aria-pressed="tema === 'oscuro'" aria-label="Modo noche"
                class="flex h-12 shrink-0 items-center gap-2 rounded-[12px] border border-linea bg-superficie px-3.5 text-sm font-semibold text-tinta">
            <svg x-show="tema !== 'oscuro'" width="18" height="18" viewBox="0 0 16 16" aria-hidden="true"><path d="M13 9.5A5.5 5.5 0 0 1 6.5 3a5.5 5.5 0 1 0 6.5 6.5Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path></svg>
            <svg x-show="tema === 'oscuro'" x-cloak width="18" height="18" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="3" fill="none" stroke="currentColor" stroke-width="1.5"></circle><path d="M8 1.5v1.5M8 13v1.5M1.5 8H3M13 8h1.5M3.4 3.4l1 1M11.6 11.6l1 1M3.4 12.6l1-1M11.6 4.4l1-1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>
            <span x-text="tema === 'oscuro' ? 'Día' : 'Noche'">Noche</span>
        </button>
    </div>

    {{-- Pestañas grandes: se tocan con el pulgar, no con el índice. --}}
    <div class="mb-4 grid grid-cols-3 gap-1 rounded-[14px] border border-linea bg-superficie p-1" role="tablist" aria-label="Día de la ruta">
        @foreach (['hoy' => 'Hoy', 'manana' => 'Mañana', 'pendientes' => 'Atrasadas'] as $valor => $etiqueta)
            <button type="button" role="tab" wire:click="$set('cuando', '{{ $valor }}')" aria-selected="{{ $cuando === $valor ? 'true' : 'false' }}"
                    @class([
                        'h-12 rounded-[10px] text-[15px] font-semibold transition-colors',
                        'bg-menu text-white dark:bg-elevada dark:text-tinta' => $cuando === $valor,
                        'text-tinta-2' => $cuando !== $valor,
                    ])>{{ $etiqueta }}</button>
        @endforeach
    </div>

    <ol class="flex flex-col gap-3">
        @forelse ($visitas as $visita)
            @php($cliente = $visita->soporte?->cliente)
            @php($atrasada = $visita->fecha_programada && $visita->fecha_programada->isPast() && ! $visita->fecha_programada->isToday())

            <li class="overflow-hidden rounded-[14px] border border-linea bg-superficie" wire:key="visita-{{ $visita->id }}">
                <a href="{{ route('campo.visita', $visita) }}" wire:navigate class="block p-4 text-tinta no-underline active:bg-elevada">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-[17px] font-semibold">{{ $cliente?->nombre ?? 'Sin cliente' }}</p>
                            <p class="mt-0.5 flex items-start gap-1.5 text-[15px] text-tinta-2">
                                <svg class="mt-[3px] shrink-0 text-tinta-3" width="14" height="14" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 17.5s5.5-5 5.5-9.5a5.5 5.5 0 0 0-11 0c0 4.5 5.5 9.5 5.5 9.5Z" fill="none" stroke="currentColor" stroke-width="1.8"></path><circle cx="10" cy="8" r="2" fill="none" stroke="currentColor" stroke-width="1.8"></circle></svg>
                                <span class="line-clamp-2">{{ $cliente?->direccion ?: 'Sin dirección registrada' }}</span>
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-1">
                            @if ($visita->franja)
                                <span class="rounded-[4px] bg-elevada px-2 py-0.5 font-mono text-xs font-semibold text-tinta-2">
                                    {{ $visita->franja === 'manana' ? 'AM' : 'PM' }}
                                </span>
                            @endif
                            @if ($visita->soporte?->criticidad === 'inmediata')
                                <x-inmediata />
                            @endif
                        </div>
                    </div>

                    @if ($visita->soporte?->descripcion)
                        <p class="mt-2 line-clamp-2 text-sm text-tinta-3">{{ $visita->soporte->descripcion }}</p>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="inline-flex h-[22px] items-center rounded-full border border-linea px-2 text-xs font-medium text-tinta-2">{{ $visita->etiquetaTipo() }}</span>
                        <x-estado :estado="$visita->estado" :etiqueta="$visita->etiquetaEstado()" />
                        @if ($atrasada)
                            <span class="inline-flex h-[22px] items-center gap-1 rounded bg-sla-atencion-fondo px-2 text-xs font-semibold text-sla-atencion">
                                <x-sla-glifo estado="atencion" :tamano="10" />
                                Desde <span class="font-mono">{{ $visita->fecha_programada->format('d/m') }}</span>
                            </span>
                        @endif
                    </div>
                </a>

                {{-- Llamar y navegar salen del teléfono, no del sistema: son enlaces
                     normales para que funcionen aunque la página esté cacheada. --}}
                <div class="grid grid-cols-2 divide-x divide-linea border-t border-linea text-[15px] font-semibold">
                    @if ($cliente?->telefono)
                        <a href="tel:{{ $cliente->telefono }}" class="flex h-14 items-center justify-center gap-2 text-acento-texto no-underline active:bg-elevada">
                            <svg width="18" height="18" viewBox="0 0 20 20" aria-hidden="true"><path d="M5.5 2.5h2.2l1.2 3.6-1.7 1.2a9 9 0 0 0 5.5 5.5l1.2-1.7 3.6 1.2v2.2a1.6 1.6 0 0 1-1.7 1.6A14 14 0 0 1 3.9 4.2a1.6 1.6 0 0 1 1.6-1.7Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"></path></svg>
                            Llamar
                        </a>
                    @else
                        <span class="flex h-14 items-center justify-center text-tinta-3">Sin teléfono</span>
                    @endif

                    @if ($cliente?->direccion)
                        <a href="https://maps.google.com/?q={{ urlencode($cliente->direccion.', Pereira, Colombia') }}"
                           target="_blank" rel="noopener" class="flex h-14 items-center justify-center gap-2 text-acento-texto no-underline active:bg-elevada">
                            <svg width="18" height="18" viewBox="0 0 20 20" aria-hidden="true"><path d="m3 9 14-6-6 14-1.6-6.4L3 9Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"></path></svg>
                            Cómo llegar
                        </a>
                    @else
                        <span class="flex h-14 items-center justify-center text-tinta-3">Sin dirección</span>
                    @endif
                </div>
            </li>
        @empty
            <li class="rounded-[14px] border-2 border-dashed border-linea bg-superficie px-4 py-14 text-center">
                <p class="text-[17px] font-medium text-tinta-2">
                    {{ $cuando === 'pendientes' ? 'Nada atrasado.' : 'No hay visitas para este día.' }}
                </p>
                <p class="mt-1 text-sm text-tinta-3">Se actualiza cuando la oficina programe una.</p>
            </li>
        @endforelse
    </ol>
</div>
