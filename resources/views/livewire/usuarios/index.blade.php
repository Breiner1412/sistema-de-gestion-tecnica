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
        $this->authorize('viewAny', User::class);
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
        $usuario = User::findOrFail($id);
        $this->authorize('update', $usuario);

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

<div class="mx-auto flex max-w-[1400px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
    <x-mensajes />

    <x-encabezado titulo="Usuarios" subtitulo="Cuentas del equipo y el rol con el que entra cada persona.">
        <x-slot:acciones>
            <x-boton variante="primario" :href="route('usuarios.create')" wire:navigate>
                <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
                Nuevo usuario
            </x-boton>
        </x-slot:acciones>
    </x-encabezado>

    {{-- Resumen del equipo --}}
    <div class="tarjeta grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 lg:divide-x lg:divide-linea">
        @foreach (\App\Models\User::ROLES as $clave => $etiqueta)
            <div class="flex flex-col gap-0.5 p-3">
                <span class="font-mono text-2xl font-semibold leading-none">{{ $porRol[$clave] ?? 0 }}</span>
                <span class="text-xs leading-tight text-tinta-3">{{ $etiqueta }}</span>
            </div>
        @endforeach
    </div>

    <div class="tarjeta grid gap-3 p-3 sm:grid-cols-[minmax(0,3fr)_minmax(0,1fr)]">
        <div>
            <label for="buscar" class="etiqueta text-[13px]">Buscar</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-tinta-3" width="15" height="15" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor" stroke-width="1.5"></circle><path d="m10.5 10.5 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>
                <input id="buscar" type="search" wire:model.live.debounce.400ms="buscar" class="control h-9 pl-8" placeholder="Nombre o correo">
            </div>
        </div>
        <div>
            <label for="f-rol" class="etiqueta text-[13px]">Rol</label>
            <select id="f-rol" wire:model.live="rol" class="control h-9 py-0">
                <option value="">Todos</option>
                @foreach (\App\Models\User::ROLES as $clave => $etiqueta)
                    <option value="{{ $clave }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-tabla minimo="820px">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Rol</th>
                <th class="text-right">Casos</th>
                <th>Estado</th>
                <th class="text-right"><span class="sr-only">Acciones</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($usuarios as $usuario)
                <tr wire:key="usuario-{{ $usuario->id }}">
                    <td class="font-medium">{{ $usuario->name }}</td>
                    <td class="text-tinta-2">{{ $usuario->email }}</td>
                    <td class="text-tinta-2">{{ $usuario->etiquetaRol() }}</td>
                    <td class="text-right font-mono text-tinta-2">{{ $usuario->soportes_asignados_count }}</td>
                    <td><x-estado :estado="$usuario->estado" :etiqueta="ucfirst($usuario->estado)" /></td>
                    <td class="whitespace-nowrap text-right">
                        <x-boton tamano="chico" :href="route('usuarios.edit', $usuario)" wire:navigate>Editar</x-boton>
                        <x-boton tamano="chico" :variante="$usuario->estado === 'activo' ? 'peligro' : 'secundario'" wire:click="alternarEstado({{ $usuario->id }})">
                            {{ $usuario->estado === 'activo' ? 'Desactivar' : 'Activar' }}
                        </x-boton>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="!h-24 text-center text-tinta-3">No hay usuarios que coincidan.</td>
                </tr>
            @endforelse
        </tbody>
    </x-tabla>

    <div>{{ $usuarios->links() }}</div>

    <p class="text-xs text-tinta-3">
        Los usuarios se desactivan, no se borran: los casos históricos siguen apuntando a quien los atendió.
    </p>
</div>
