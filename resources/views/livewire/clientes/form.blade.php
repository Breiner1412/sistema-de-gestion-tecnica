<?php

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public ?Cliente $cliente = null;

    public string $codigo_abonado = '';
    public string $cedula = '';
    public string $nombre = '';
    public string $telefono = '';
    public string $correo = '';
    public string $direccion = '';
    public string $latitud = '';
    public string $longitud = '';
    public string $estado = 'activo';

    public function mount(?Cliente $cliente = null): void
    {
        abort_unless(
            auth()->user()->tieneRol(User::ROL_ADMIN, User::ROL_GERENTE, User::ROL_CALL_CENTER),
            403
        );

        if ($cliente?->exists) {
            $this->cliente = $cliente;

            $this->fill([
                'codigo_abonado' => (string) $cliente->codigo_abonado,
                'cedula' => (string) $cliente->cedula,
                'nombre' => (string) $cliente->nombre,
                'telefono' => (string) $cliente->telefono,
                'correo' => (string) $cliente->correo,
                'direccion' => (string) $cliente->direccion,
                'latitud' => (string) $cliente->latitud,
                'longitud' => (string) $cliente->longitud,
                'estado' => $cliente->estado,
            ]);
        }
    }

    public function guardar()
    {
        $datos = $this->validate([
            'codigo_abonado' => ['nullable', 'string', 'max:30', Rule::unique('clientes')->ignore($this->cliente)],
            'cedula' => ['required', 'string', 'max:30', Rule::unique('clientes')->ignore($this->cliente)],
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'estado' => [Rule::in(['activo', 'inactivo'])],
        ], [], [
            'codigo_abonado' => 'código de abonado',
            'cedula' => 'cédula o NIT',
        ]);

        // Los campos vacíos entran como null para no romper los índices únicos.
        foreach (['codigo_abonado', 'telefono', 'correo', 'direccion', 'latitud', 'longitud'] as $campo) {
            $datos[$campo] = $datos[$campo] !== '' ? $datos[$campo] : null;
        }

        $cliente = $this->cliente
            ? tap($this->cliente)->update($datos)
            : Cliente::create($datos);

        session()->flash('mensaje', $this->cliente ? 'Cliente actualizado.' : 'Cliente creado.');

        return $this->redirect(route('clientes.show', $cliente), navigate: true);
    }
}; ?>

<div class="p-6 max-w-3xl">
    <a href="{{ route('clientes.index') }}" wire:navigate class="text-blue-600 text-sm">&larr; Volver a clientes</a>

    <h1 class="text-2xl font-bold mt-2 mb-4 text-slate-800">
        {{ $cliente ? 'Editar cliente' : 'Nuevo cliente' }}
    </h1>

    <form wire:submit="guardar" class="space-y-5">

        <div class="bg-white border rounded p-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="block font-medium mb-1 text-slate-800">Código de abonado</label>
                <input type="text" wire:model="codigo_abonado" class="w-full border rounded p-2"
                    placeholder="TCF004906">
                <p class="text-xs text-slate-500 mt-1">Es el identificador que usa la operación a diario.</p>
                @error('codigo_abonado') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block font-medium mb-1 text-slate-800">Cédula o NIT</label>
                <input type="text" wire:model="cedula" class="w-full border rounded p-2">
                @error('cedula') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block font-medium mb-1 text-slate-800">Nombre o razón social</label>
                <input type="text" wire:model="nombre" class="w-full border rounded p-2">
                @error('nombre') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block font-medium mb-1 text-slate-800">Teléfono</label>
                <input type="text" wire:model="telefono" class="w-full border rounded p-2">
                @error('telefono') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block font-medium mb-1 text-slate-800">Correo</label>
                <input type="email" wire:model="correo" class="w-full border rounded p-2">
                @error('correo') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block font-medium mb-1 text-slate-800">Dirección</label>
                <input type="text" wire:model="direccion" class="w-full border rounded p-2">
                @error('direccion') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="bg-white border rounded p-4 grid gap-4 md:grid-cols-3">
            <div>
                <label class="block font-medium mb-1 text-slate-800">Latitud</label>
                <input type="text" wire:model="latitud" class="w-full border rounded p-2" placeholder="4.8133">
                @error('latitud') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block font-medium mb-1 text-slate-800">Longitud</label>
                <input type="text" wire:model="longitud" class="w-full border rounded p-2" placeholder="-75.6961">
                @error('longitud') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block font-medium mb-1 text-slate-800">Estado</label>
                <select wire:model="estado" class="w-full border rounded p-2">
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
            </div>
            <p class="md:col-span-3 text-xs text-slate-500">
                Las coordenadas son opcionales; sirven para ubicar al abonado cuando se programa una visita.
            </p>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">
                {{ $cliente ? 'Guardar cambios' : 'Crear cliente' }}
            </button>
            <a href="{{ $cliente ? route('clientes.show', $cliente) : route('clientes.index') }}" wire:navigate
                class="px-4 py-2 rounded border">Cancelar</a>
        </div>
    </form>
</div>
