<?php

use App\Models\Cliente;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(except: 'activo')]
    public string $estado = 'activo';

    public function updated($propiedad): void
    {
        if ($propiedad !== 'page') {
            $this->resetPage();
        }
    }

    #[Computed]
    public function puedeEditar(): bool
    {
        return auth()->user()->tieneRol(User::ROL_ADMIN, User::ROL_GERENTE, User::ROL_CALL_CENTER);
    }

    public function with(): array
    {
        return [
            'clientes' => Cliente::query()
                ->withCount(['contratos', 'soportes'])
                ->buscar($this->buscar)
                ->when($this->estado, fn($q) => $q->where('estado', $this->estado))
                ->orderBy('nombre')
                ->paginate(25),
        ];
    }
}; ?>

<div class="p-6">
    @if (session('mensaje'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('mensaje') }}</div>
    @endif

    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold text-slate-800">Clientes</h1>
        <div class="flex gap-2">
            <a href="{{ route('clientes.recurrentes') }}" wire:navigate class="px-4 py-2 rounded border text-sm">
                Ver recurrentes
            </a>
        @if ($this->puedeEditar)
            <a href="{{ route('clientes.create') }}" wire:navigate class="bg-blue-600 text-white px-4 py-2 rounded">
                + Nuevo cliente
            </a>
        @endif
        </div>
    </div>

    <div class="bg-white border rounded p-4 mb-4 grid gap-3 md:grid-cols-4">
        <div class="md:col-span-3">
            <label class="block text-xs font-medium text-slate-600 mb-1">Buscar</label>
            <input type="search" wire:model.live.debounce.400ms="buscar" class="w-full border rounded p-2 text-sm"
                placeholder="Nombre, cédula o código de abonado">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Estado</label>
            <select wire:model.live="estado" class="w-full border rounded p-2 text-sm">
                <option value="activo">Activos</option>
                <option value="inactivo">Inactivos</option>
                <option value="">Todos</option>
            </select>
        </div>
    </div>

    <div class="overflow-x-auto bg-white border rounded shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-700 text-white">
                <tr>
                    <th class="p-2 text-left font-semibold">Abonado</th>
                    <th class="p-2 text-left font-semibold">Nombre</th>
                    <th class="p-2 text-left font-semibold">Cédula / NIT</th>
                    <th class="p-2 text-left font-semibold">Teléfono</th>
                    <th class="p-2 text-right font-semibold">Contratos</th>
                    <th class="p-2 text-right font-semibold">Casos</th>
                    <th class="p-2 text-left font-semibold">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($clientes as $cliente)
                    <tr class="hover:bg-slate-50 cursor-pointer"
                        onclick="window.location='{{ route('clientes.show', $cliente) }}'">
                        <td class="p-2 font-mono text-xs text-slate-600">{{ $cliente->codigo_abonado ?? '—' }}</td>
                        <td class="p-2">{{ $cliente->nombre }}</td>
                        <td class="p-2 text-slate-600">{{ $cliente->cedula }}</td>
                        <td class="p-2 text-slate-600">{{ $cliente->telefono ?? '—' }}</td>
                        <td class="p-2 text-right text-slate-600">{{ $cliente->contratos_count }}</td>
                        <td class="p-2 text-right text-slate-600">{{ $cliente->soportes_count }}</td>
                        <td class="p-2">
                            <span @class([
                                'px-2 py-1 rounded text-xs',
                                'bg-green-200 text-green-900' => $cliente->estado === 'activo',
                                'bg-slate-200 text-slate-700' => $cliente->estado !== 'activo',
                            ])>{{ ucfirst($cliente->estado) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center text-slate-500">
                            No hay clientes que coincidan con la búsqueda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $clientes->links() }}</div>
</div>
