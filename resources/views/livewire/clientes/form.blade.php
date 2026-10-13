<?php

use App\Models\Cliente;
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
        $cliente?->exists
            ? $this->authorize('update', $cliente)
            : $this->authorize('create', Cliente::class);

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
        $this->cliente
            ? $this->authorize('update', $this->cliente)
            : $this->authorize('create', Cliente::class);

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

<div class="mx-auto flex max-w-[760px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
    <x-encabezado :titulo="$cliente ? 'Editar cliente' : 'Nuevo cliente'">
        <x-slot:antes>
            <a href="{{ route('clientes.index') }}" wire:navigate class="inline-flex items-center gap-1 text-tinta-3 no-underline hover:text-acento-texto">
                <svg width="13" height="13" viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                Clientes
            </a>
        </x-slot:antes>
    </x-encabezado>

    <form wire:submit="guardar" class="flex flex-col gap-4">

        <section class="tarjeta grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
            <h2 class="titulo-seccion sm:col-span-2">Identificación y contacto</h2>

            <div>
                <label for="codigo-abonado" class="etiqueta">Código de abonado</label>
                <input id="codigo-abonado" type="text" wire:model="codigo_abonado" class="control h-10 font-mono" placeholder="TCF004906">
                <p class="mt-1 text-xs text-tinta-3">Es el identificador que usa la operación a diario.</p>
                @error('codigo_abonado') <p class="error-campo">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="cedula" class="etiqueta">Cédula o NIT</label>
                <input id="cedula" type="text" wire:model="cedula" class="control h-10 font-mono">
                @error('cedula') <p class="error-campo">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="nombre" class="etiqueta">Nombre o razón social</label>
                <input id="nombre" type="text" wire:model="nombre" class="control h-10">
                @error('nombre') <p class="error-campo">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="telefono" class="etiqueta">Teléfono</label>
                <input id="telefono" type="text" wire:model="telefono" class="control h-10 font-mono" inputmode="tel">
                @error('telefono') <p class="error-campo">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="correo" class="etiqueta">Correo</label>
                <input id="correo" type="email" wire:model="correo" class="control h-10">
                @error('correo') <p class="error-campo">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="direccion" class="etiqueta">Dirección</label>
                <input id="direccion" type="text" wire:model="direccion" class="control h-10">
                @error('direccion') <p class="error-campo">{{ $message }}</p> @enderror
            </div>
        </section>

        <section class="tarjeta grid gap-4 p-4 sm:grid-cols-3 sm:p-5">
            <h2 class="titulo-seccion sm:col-span-3">Ubicación y estado</h2>

            <div>
                <label for="latitud" class="etiqueta">Latitud</label>
                <input id="latitud" type="text" wire:model="latitud" class="control h-10 font-mono" placeholder="4.8133">
                @error('latitud') <p class="error-campo">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="longitud" class="etiqueta">Longitud</label>
                <input id="longitud" type="text" wire:model="longitud" class="control h-10 font-mono" placeholder="-75.6961">
                @error('longitud') <p class="error-campo">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="estado" class="etiqueta">Estado</label>
                <select id="estado" wire:model="estado" class="control h-10 py-0">
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
            </div>
            <p class="text-xs text-tinta-3 sm:col-span-3">
                Las coordenadas son opcionales; sirven para ubicar al abonado cuando se programa una visita.
            </p>
        </section>

        <div class="flex gap-2">
            <x-boton variante="primario" type="submit">
                {{ $cliente ? 'Guardar cambios' : 'Crear cliente' }}
            </x-boton>
            <x-boton variante="fantasma" :href="$cliente ? route('clientes.show', $cliente) : route('clientes.index')" wire:navigate>Cancelar</x-boton>
        </div>
    </form>
</div>
