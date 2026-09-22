<?php

use App\Models\OrdenTrabajo;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.campo')] class extends Component {
    #[Url(except: 'hoy')]
    public string $cuando = 'hoy';

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
            'manana' => $query->whereDate('fecha_programada', today()->addDay()),
            'pendientes' => $query->where(fn($q) => $q
                ->whereNull('fecha_programada')
                ->orWhereDate('fecha_programada', '<', today())),
            default => $query->whereDate('fecha_programada', today()),
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
                ->whereDate('hora_fin', today())
                ->count(),
        ];
    }
}; ?>

<div class="px-4 py-4">

    <div class="mb-4 flex items-baseline justify-between">
        <h1 class="text-xl font-bold">
            {{ $cuando === 'manana' ? 'Mañana' : ($cuando === 'pendientes' ? 'Atrasadas' : 'Hoy') }}
        </h1>
        @if ($hechasHoy > 0)
            <p class="text-sm text-slate-500">{{ $hechasHoy }} cerrada{{ $hechasHoy === 1 ? '' : 's' }} hoy</p>
        @endif
    </div>

    {{-- Pestañas grandes: se tocan con el pulgar, no con el índice. --}}
    <div class="mb-4 grid grid-cols-3 gap-2">
        @foreach (['hoy' => 'Hoy', 'manana' => 'Mañana', 'pendientes' => 'Atrasadas'] as $valor => $etiqueta)
            <button wire:click="$set('cuando', '{{ $valor }}')"
                    @class([
                        'rounded-lg py-3 text-sm font-semibold transition',
                        'bg-slate-900 text-white' => $cuando === $valor,
                        'bg-white text-slate-600 border' => $cuando !== $valor,
                    ])>{{ $etiqueta }}</button>
        @endforeach
    </div>

    @forelse ($visitas as $visita)
        @php($cliente = $visita->soporte?->cliente)

        <div class="mb-3 rounded-xl border bg-white shadow-sm">
            <a href="{{ route('campo.visita', $visita) }}" wire:navigate class="block p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold">{{ $cliente?->nombre ?? 'Sin cliente' }}</p>
                        <p class="mt-0.5 truncate text-sm text-slate-500">{{ $cliente?->direccion ?: 'Sin dirección registrada' }}</p>
                    </div>

                    @if ($visita->soporte?->criticidad === 'inmediata')
                        <span class="shrink-0 rounded bg-red-100 px-2 py-1 text-xs font-bold text-red-700">Inmediata</span>
                    @elseif ($visita->franja)
                        <span class="shrink-0 rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">
                            {{ $visita->franja === 'manana' ? 'AM' : 'PM' }}
                        </span>
                    @endif
                </div>

                <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $visita->soporte?->descripcion }}</p>

                <div class="mt-3 flex items-center gap-2 text-xs text-slate-500">
                    <span class="rounded bg-slate-100 px-2 py-1">{{ $visita->etiquetaTipo() }}</span>
                    <span class="rounded bg-slate-100 px-2 py-1">{{ $visita->etiquetaEstado() }}</span>
                    @if ($visita->fecha_programada && $visita->fecha_programada->isPast() && !$visita->fecha_programada->isToday())
                        <span class="rounded bg-amber-100 px-2 py-1 text-amber-800">
                            {{ $visita->fecha_programada->format('d/m') }}
                        </span>
                    @endif
                </div>
            </a>

            {{-- Llamar y navegar salen del teléfono, no del sistema: son enlaces
                 normales para que funcionen aunque la página esté cacheada. --}}
            <div class="grid grid-cols-2 divide-x border-t text-sm font-semibold">
                @if ($cliente?->telefono)
                    <a href="tel:{{ $cliente->telefono }}" class="py-3 text-center text-sky-700">Llamar</a>
                @else
                    <span class="py-3 text-center text-slate-300">Sin teléfono</span>
                @endif

                @if ($cliente?->direccion)
                    <a href="https://maps.google.com/?q={{ urlencode($cliente->direccion.', Pereira, Colombia') }}"
                       target="_blank" rel="noopener" class="py-3 text-center text-sky-700">Cómo llegar</a>
                @else
                    <span class="py-3 text-center text-slate-300">Sin dirección</span>
                @endif
            </div>
        </div>
    @empty
        <div class="rounded-xl border-2 border-dashed bg-white py-14 text-center">
            <p class="font-medium text-slate-600">
                {{ $cuando === 'pendientes' ? 'Nada atrasado.' : 'No hay visitas para este día.' }}
            </p>
            <p class="mt-1 text-sm text-slate-400">Se actualiza cuando la oficina programe una.</p>
        </div>
    @endforelse
</div>
