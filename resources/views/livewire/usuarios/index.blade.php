<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $rol = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->esAdmin(), 403);
    }

    public function updated($propiedad): void
    {
        if ($propiedad !== 'page') {
            $this->resetPage();
        }
    }

    /** Desactivar en vez de borrar: los casos históricos siguen apuntando al usuario. */
    public function alternarEstado(int $id): void
    {
        abort_unless(auth()->user()->esAdmin(), 403);

        $usuario = User::findOrFail($id);

        if ($usuario->id === auth()->id()) {
            session()->flash('error', 'No puedes desactivar tu propia cuenta.');
            return;
        }

        $usuario->update(['estado' => $usuario->estado === 'activo' ? 'inactivo' : 'activo']);

        session()->flash('mensaje', "Usuario {$usuario->name} " .
            ($usuario->estado === 'activo' ? 'activado.' : 'desactivado.'));
    }

    public function with(): array
    {
        return [
            'usuarios' => User::query()
                ->withCount('soportesAsignados')
                ->when($this->buscar, fn($q) => $q->where(fn($s) => $s
                    ->where('name', 'like', "%{$this->buscar}%")
                    ->orWhere('email', 'like', "%{$this->buscar}%")))
                ->when($this->rol, fn($q) => $q->where('rol', $this->rol))
                ->orderBy('rol')
                ->orderBy('name')
                ->paginate(30),
            'porRol' => User::selectRaw('rol, count(*) as total')->groupBy('rol')->pluck('total', 'rol'),
        ];
    }
}; ?>

<div class="p-6">
    @if (session('mensaje'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('mensaje') }}</div>
    @endif
    @if (session('error'))
        <div class="bg-red-100 text-red-800 p-3 rounded mb-4">{{ session('error') }}</div>
    @endif

    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold text-slate-800">Usuarios</h1>
        <a href="{{ route('usuarios.create') }}" wire:navigate class="bg-blue-600 text-white px-4 py-2 rounded">
            + Nuevo usuario
        </a>
    </div>

    {{-- Resumen del equipo --}}
    <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6 mb-4">
        @foreach (\App\Models\User::ROLES as $clave => $etiqueta)
            <div class="bg-white border rounded p-3">
                <p class="text-2xl font-bold text-slate-800">{{ $porRol[$clave] ?? 0 }}</p>
                <p class="text-xs text-slate-500 leading-tight">{{ $etiqueta }}</p>
            </div>
        @endforeach
    </div>

    <div class="bg-white border rounded p-4 mb-4 grid gap-3 md:grid-cols-4">
        <div class="md:col-span-3">
            <label class="block text-xs font-medium text-slate-600 mb-1">Buscar</label>
            <input type="search" wire:model.live.debounce.400ms="buscar" class="w-full border rounded p-2 text-sm"
                placeholder="Nombre o correo">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Rol</label>
            <select wire:model.live="rol" class="w-full border rounded p-2 text-sm">
                <option value="">Todos</option>
                @foreach (\App\Models\User::ROLES as $clave => $etiqueta)
                    <option value="{{ $clave }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-x-auto bg-white border rounded shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-700 text-white">
                <tr>
                    <th class="p-2 text-left font-semibold">Nombre</th>
                    <th class="p-2 text-left font-semibold">Correo</th>
                    <th class="p-2 text-left font-semibold">Rol</th>
                    <th class="p-2 text-right font-semibold">Casos</th>
                    <th class="p-2 text-left font-semibold">Estado</th>
                    <th class="p-2 text-right font-semibold">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($usuarios as $usuario)
                    <tr class="hover:bg-slate-50">
                        <td class="p-2">{{ $usuario->name }}</td>
                        <td class="p-2 text-slate-600">{{ $usuario->email }}</td>
                        <td class="p-2 text-slate-600 text-xs">{{ $usuario->etiquetaRol() }}</td>
                        <td class="p-2 text-right text-slate-600">{{ $usuario->soportes_asignados_count }}</td>
                        <td class="p-2">
                            <span @class([
                                'px-2 py-1 rounded text-xs',
                                'bg-green-200 text-green-900' => $usuario->estado === 'activo',
                                'bg-slate-200 text-slate-700' => $usuario->estado !== 'activo',
                            ])>{{ ucfirst($usuario->estado) }}</span>
                        </td>
                        <td class="p-2 text-right whitespace-nowrap">
                            <a href="{{ route('usuarios.edit', $usuario) }}" wire:navigate class="text-blue-600 text-xs">
                                Editar
                            </a>
                            <button wire:click="alternarEstado({{ $usuario->id }})"
                                class="ml-3 text-xs {{ $usuario->estado === 'activo' ? 'text-red-600' : 'text-green-700' }}">
                                {{ $usuario->estado === 'activo' ? 'Desactivar' : 'Activar' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center text-slate-500">No hay usuarios que coincidan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $usuarios->links() }}</div>

    <p class="mt-3 text-xs text-slate-500">
        Los usuarios se desactivan, no se borran: los casos históricos siguen apuntando a quien los atendió.
    </p>
</div>
