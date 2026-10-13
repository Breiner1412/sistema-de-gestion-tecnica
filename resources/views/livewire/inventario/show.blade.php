<?php

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public Inventario $material;

    public function mount(Inventario $material): void
    {
        $this->authorize('view', $material);

        $this->material = $material;
    }

    public function with(): array
    {
        return [
            'movimientos' => MovimientoInventario::where('material_id', $this->material->id)
                ->with('tecnico.user:id,name')
                ->latest('id')
                ->paginate(30),
            'resumen' => MovimientoInventario::where('material_id', $this->material->id)
                ->selectRaw('tipo, sum(cantidad) as total')
                ->groupBy('tipo')
                ->pluck('total', 'tipo'),
        ];
    }
}; ?>

<div class="mx-auto flex max-w-[1100px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
    <x-encabezado :titulo="$material->nombre" :subtitulo="ucfirst($material->tipo ?? 'sin categoría')">
        <x-slot:antes>
            <a href="{{ route('inventario.index') }}" wire:navigate class="inline-flex items-center gap-1 text-tinta-3 no-underline hover:text-acento-texto">
                <svg width="13" height="13" viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                Inventario
            </a>
        </x-slot:antes>
    </x-encabezado>

    @php $bajo = $material->cantidad_total <= $material->cantidad_minima; @endphp
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div @class(['tarjeta p-4', 'border-sla-atencion/50' => $bajo])>
            <p class="text-[13px] font-medium text-tinta-3">Existencia</p>
            <p class="mt-1 font-mono text-[32px] font-semibold leading-none">{{ number_format($material->cantidad_total) }}</p>
            @if ($bajo)
                <p class="mt-2 inline-flex items-center gap-1 rounded bg-sla-atencion-fondo px-1.5 text-xs font-semibold text-sla-atencion">
                    <x-sla-glifo estado="atencion" :tamano="10" /> Stock bajo
                </p>
            @endif
        </div>
        <div class="tarjeta p-4">
            <p class="text-[13px] font-medium text-tinta-3">Stock mínimo</p>
            <p class="mt-1 font-mono text-[32px] font-semibold leading-none">{{ number_format($material->cantidad_minima) }}</p>
        </div>
        <div class="tarjeta p-4">
            <p class="text-[13px] font-medium text-tinta-3">Consumido en visitas</p>
            <p class="mt-1 font-mono text-[32px] font-semibold leading-none">{{ number_format($resumen['consumo'] ?? 0) }}</p>
        </div>
        <div class="tarjeta p-4">
            <p class="text-[13px] font-medium text-tinta-3">Valor en bodega</p>
            <p class="mt-1 font-mono text-2xl font-semibold">
                {{ $material->precio_unitario
                    ? '$'.number_format($material->cantidad_total * $material->precio_unitario, 0, ',', '.')
                    : '—' }}
            </p>
        </div>
    </div>

    <section>
        <h2 class="titulo-seccion mb-2">Kardex</h2>

        @php
            $movimientosEstilo = [
                'entrada' => ['+', 'text-sla-ok', 'M8 13V3m0 0L4 7m4-4 4 4'],
                'devolucion' => ['+', 'text-sla-ok', 'M13 8H3m0 0 4-4M3 8l4 4'],
                'consumo' => ['−', 'text-tinta', 'M8 3v10m0 0-4-4m4 4 4-4'],
                'salida' => ['−', 'text-peligro', 'M8 3v10m0 0-4-4m4 4 4-4'],
            ];
        @endphp

        <x-tabla minimo="620px">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Movimiento</th>
                    <th class="text-right">Cantidad</th>
                    <th>Técnico</th>
                    <th>Visita</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movimientos as $movimiento)
                    @php [$signo, $color, $flecha] = $movimientosEstilo[$movimiento->tipo] ?? ['', 'text-tinta', 'M3 8h10']; @endphp
                    <tr>
                        <td class="whitespace-nowrap font-mono text-xs text-tinta-3">{{ $movimiento->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="{{ $color }}" width="13" height="13" viewBox="0 0 16 16" aria-hidden="true"><path d="{{ $flecha }}" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                {{ ucfirst($movimiento->tipo) }}
                            </span>
                        </td>
                        <td class="text-right font-mono font-semibold {{ $color }}">{{ $signo }}{{ $movimiento->cantidad }}</td>
                        <td class="text-tinta-2">{{ $movimiento->tecnico->user->name ?? '—' }}</td>
                        <td>
                            @if ($movimiento->referencia_orden_id)
                                <a href="{{ route('ordenes.show', $movimiento->referencia_orden_id) }}" wire:navigate class="font-mono text-[13px]">#{{ $movimiento->referencia_orden_id }}</a>
                            @else
                                <span class="text-tinta-3">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="!h-24 text-center text-tinta-3">Sin movimientos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-tabla>

        <div class="mt-4">{{ $movimientos->links() }}</div>
    </section>
</div>
