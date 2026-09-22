<?php

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public Inventario $material;

    public function mount(Inventario $material): void
    {
        abort_unless(auth()->user()->tieneRol(User::ROL_ADMIN, User::ROL_GERENTE), 403);

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

<div class="p-6 max-w-4xl">
    <a href="{{ route('inventario.index') }}" wire:navigate class="text-blue-600 text-sm">&larr; Volver a inventario</a>

    <h1 class="text-2xl font-bold mt-2 mb-1 text-slate-800">{{ $material->nombre }}</h1>
    <p class="text-sm text-slate-500 mb-4">{{ ucfirst($material->tipo ?? 'sin categoría') }}</p>

    <div class="grid gap-4 sm:grid-cols-4 mb-6">
        <div class="bg-white border rounded p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Existencia</p>
            <p @class([
                'text-3xl font-bold mt-1',
                'text-orange-700' => $material->cantidad_total <= $material->cantidad_minima,
                'text-slate-800' => $material->cantidad_total > $material->cantidad_minima,
            ])>{{ number_format($material->cantidad_total) }}</p>
        </div>
        <div class="bg-white border rounded p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Stock mínimo</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{{ number_format($material->cantidad_minima) }}</p>
        </div>
        <div class="bg-white border rounded p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Consumido en visitas</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{{ number_format($resumen['consumo'] ?? 0) }}</p>
        </div>
        <div class="bg-white border rounded p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Valor en bodega</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">
                {{ $material->precio_unitario
                    ? '$'.number_format($material->cantidad_total * $material->precio_unitario, 0, ',', '.')
                    : '—' }}
            </p>
        </div>
    </div>

    <div class="bg-white border rounded p-4">
        <h2 class="font-semibold text-slate-800 mb-3">Kardex</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-1 font-medium">Fecha</th>
                        <th class="py-1 font-medium">Movimiento</th>
                        <th class="py-1 font-medium text-right">Cantidad</th>
                        <th class="py-1 font-medium">Técnico</th>
                        <th class="py-1 font-medium">Visita</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movimientos as $movimiento)
                        <tr class="border-b last:border-0">
                            <td class="py-1.5 text-slate-500 text-xs whitespace-nowrap">
                                {{ $movimiento->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="py-1.5">
                                <span @class([
                                    'px-2 py-0.5 rounded text-xs',
                                    'bg-green-200 text-green-900' => $movimiento->tipo === 'entrada',
                                    'bg-blue-200 text-blue-900' => $movimiento->tipo === 'devolucion',
                                    'bg-orange-200 text-orange-900' => $movimiento->tipo === 'consumo',
                                    'bg-red-200 text-red-900' => $movimiento->tipo === 'salida',
                                ])>{{ ucfirst($movimiento->tipo) }}</span>
                            </td>
                            <td class="py-1.5 text-right font-medium">
                                {{ in_array($movimiento->tipo, ['entrada', 'devolucion']) ? '+' : '−' }}{{ $movimiento->cantidad }}
                            </td>
                            <td class="py-1.5 text-slate-600">{{ $movimiento->tecnico->user->name ?? '—' }}</td>
                            <td class="py-1.5">
                                @if ($movimiento->referencia_orden_id)
                                    <a href="{{ route('ordenes.show', $movimiento->referencia_orden_id) }}" wire:navigate
                                        class="text-blue-600 text-xs">#{{ $movimiento->referencia_orden_id }}</a>
                                @else
                                    <span class="text-slate-400 text-xs">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-slate-500">Sin movimientos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $movimientos->links() }}</div>
    </div>
</div>
