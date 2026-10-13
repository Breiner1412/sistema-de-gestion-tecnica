<?php

use App\Models\Cliente;
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

    public function mount(): void
    {
        $this->authorize('viewAny', Cliente::class);
    }

    #[Computed]
    public function puedeEditar(): bool
    {
        return auth()->user()->can('create', Cliente::class);
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

<div class="mx-auto flex max-w-[1400px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
    <x-mensajes />

    <x-encabezado titulo="Clientes" subtitulo="Abonados, sus contratos y los casos que han reportado.">
        <x-slot:acciones>
            <x-boton :href="route('clientes.recurrentes')" wire:navigate>
                <span class="font-semibold text-recurrente">↻</span>
                Ver recurrentes
            </x-boton>
            @if ($this->puedeEditar)
                <x-boton variante="primario" :href="route('clientes.create')" wire:navigate>
                    <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
                    Nuevo cliente
                </x-boton>
            @endif
        </x-slot:acciones>
    </x-encabezado>

    <div class="tarjeta grid gap-3 p-3 sm:grid-cols-[minmax(0,3fr)_minmax(0,1fr)]">
        <div>
            <label for="buscar" class="etiqueta text-[13px]">Buscar</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-tinta-3" width="15" height="15" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor" stroke-width="1.5"></circle><path d="m10.5 10.5 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>
                <input id="buscar" type="search" wire:model.live.debounce.400ms="buscar" class="control h-9 pl-8"
                    placeholder="Nombre, cédula o código de abonado">
            </div>
        </div>
        <div>
            <label for="f-estado" class="etiqueta text-[13px]">Estado</label>
            <select id="f-estado" wire:model.live="estado" class="control h-9 py-0">
                <option value="activo">Activos</option>
                <option value="inactivo">Inactivos</option>
                <option value="">Todos</option>
            </select>
        </div>
    </div>

    <x-tabla minimo="760px">
        <thead>
            <tr>
                <th>Abonado</th>
                <th>Nombre</th>
                <th>Cédula / NIT</th>
                <th>Teléfono</th>
                <th class="text-right">Contratos</th>
                <th class="text-right">Casos</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clientes as $cliente)
                <tr wire:key="cliente-{{ $cliente->id }}" class="cursor-pointer"
                    onclick="window.location='{{ route('clientes.show', $cliente) }}'">
                    <td class="font-mono text-tinta-2">{{ $cliente->codigo_abonado ?? '—' }}</td>
                    <td>
                        <a href="{{ route('clientes.show', $cliente) }}" class="font-medium text-tinta no-underline hover:text-acento-texto hover:underline">{{ $cliente->nombre }}</a>
                    </td>
                    <td class="font-mono text-tinta-2">{{ $cliente->cedula }}</td>
                    <td class="font-mono text-tinta-2">{{ $cliente->telefono ?? '—' }}</td>
                    <td class="text-right font-mono text-tinta-2">{{ $cliente->contratos_count }}</td>
                    <td class="text-right font-mono text-tinta-2">{{ $cliente->soportes_count }}</td>
                    <td><x-estado :estado="$cliente->estado" :etiqueta="ucfirst($cliente->estado)" /></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="!h-24 text-center text-tinta-3">No hay clientes que coincidan con la búsqueda.</td>
                </tr>
            @endforelse
        </tbody>
    </x-tabla>

    <div>{{ $clientes->links() }}</div>
</div>
