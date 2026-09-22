<?php

use App\Exceptions\TransicionInvalidaException;
use App\Models\Diagnostico;
use App\Models\Inventario;
use App\Models\MaterialOrden;
use App\Models\OrdenTrabajo;
use App\Models\Soporte;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public OrdenTrabajo $orden;

    public string $estado = '';
    public string $observaciones = '';
    public $diagnostico_id = '';
    public string $motivo_no_realizada = '';

    public $material_id = '';
    public int $cantidad_usada = 1;

    public function mount(OrdenTrabajo $orden): void
    {
        $this->orden = $orden;
        $this->estado = $orden->estado;
        $this->observaciones = $orden->observaciones ?? '';
        $this->diagnostico_id = $orden->soporte->diagnostico_id ?? '';
        $this->recargar();
    }

    private function recargar(): void
    {
        $this->orden->refresh()->load([
            'soporte.cliente',
            'soporte.diagnostico',
            'tecnico.user',
            'materialesUsados.material',
        ]);
    }

    #[Computed]
    public function diagnosticos()
    {
        return Diagnostico::activos()->get();
    }

    #[Computed]
    public function materiales()
    {
        return Inventario::where('cantidad_total', '>', 0)->orderBy('nombre')->get();
    }

    /** El técnico solo toca su propia visita; los roles de gestión, cualquiera. */
    #[Computed]
    public function puedeEditar(): bool
    {
        $usuario = auth()->user();

        if ($usuario->tieneRol(...User::ROLES_GESTION)) {
            return true;
        }

        return $usuario->tecnico?->id === $this->orden->tecnico_id;
    }

    public function agregarMaterial(): void
    {
        abort_unless($this->puedeEditar, 403);

        $this->validate([
            'material_id' => 'required|exists:inventario,id',
            'cantidad_usada' => 'required|integer|min:1',
        ]);

        // Si no hay stock, el observer lanza ValidationException en creating()
        // y no se inserta nada.
        MaterialOrden::create([
            'orden_id' => $this->orden->id,
            'material_id' => $this->material_id,
            'cantidad_usada' => $this->cantidad_usada,
        ]);

        $this->reset('material_id');
        $this->cantidad_usada = 1;
        unset($this->materiales);
        $this->recargar();

        session()->flash('mensaje', 'Material registrado y descontado del inventario.');
    }

    public function quitarMaterial(int $id): void
    {
        abort_unless($this->puedeEditar, 403);

        MaterialOrden::where('orden_id', $this->orden->id)->whereKey($id)->first()?->delete();

        unset($this->materiales);
        $this->recargar();

        session()->flash('mensaje', 'Material devuelto al inventario.');
    }

    public function actualizar(): void
    {
        abort_unless($this->puedeEditar, 403);

        $reglas = [
            'estado' => [Rule::in(array_keys(OrdenTrabajo::ESTADOS))],
            'observaciones' => ['nullable', 'string'],
        ];

        if ($this->estado === OrdenTrabajo::ESTADO_COMPLETADO) {
            $reglas['diagnostico_id'] = ['required', 'exists:diagnosticos,id'];
            $reglas['observaciones'] = ['required', 'min:10'];
        }

        if ($this->estado === OrdenTrabajo::ESTADO_NO_REALIZADA) {
            $reglas['motivo_no_realizada'] = ['required', 'min:5'];
        }

        $this->validate($reglas, [
            'diagnostico_id.required' => 'Selecciona el diagnóstico antes de completar la visita.',
            'observaciones.required' => 'Describe el trabajo realizado antes de completar la visita.',
            'motivo_no_realizada.required' => 'Explica por qué no se pudo hacer la visita.',
        ]);

        $cambios = [
            'estado' => $this->estado,
            'observaciones' => $this->observaciones,
        ];

        if ($this->estado === OrdenTrabajo::ESTADO_EN_PROGRESO && !$this->orden->hora_inicio) {
            $cambios['hora_inicio'] = now();
        }

        if (in_array($this->estado, [OrdenTrabajo::ESTADO_COMPLETADO, OrdenTrabajo::ESTADO_NO_REALIZADA], true)
            && !$this->orden->hora_fin) {
            $cambios['hora_fin'] = now();
        }

        $this->orden->update($cambios);
        $this->orden->refresh();

        $soporte = $this->orden->soporte;

        try {
            // La visita se hizo: el caso se cierra por el único camino válido,
            // que además reanuda métricas y deja historial.
            if ($this->estado === OrdenTrabajo::ESTADO_COMPLETADO && !$soporte->estaCerrado()) {
                $soporte->cerrar((int) $this->diagnostico_id, $this->observaciones);
            }

            // No se pudo hacer: el caso vuelve al nivel 2 y el reloj se reanuda.
            if ($this->estado === OrdenTrabajo::ESTADO_NO_REALIZADA && !$soporte->estaCerrado()) {
                $soporte->cambiarEstado(
                    Soporte::ESTADO_EN_PROCESO,
                    "Visita no realizada: {$this->motivo_no_realizada}"
                );
            }
        } catch (TransicionInvalidaException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->recargar();

        session()->flash('mensaje', 'Visita actualizada.');
    }
}; ?>

<div class="p-6 max-w-3xl">

    @if (session('mensaje'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('mensaje') }}</div>
    @endif
    @if (session('error'))
        <div class="bg-red-100 text-red-800 p-3 rounded mb-4">{{ session('error') }}</div>
    @endif

    <a href="{{ route('soportes.show', $orden->soporte) }}" wire:navigate class="text-blue-600 text-sm">
        &larr; Volver al caso
    </a>

    <div class="flex flex-wrap items-center gap-3 mt-2 mb-4">
        <h1 class="text-2xl font-bold text-slate-800">Visita #{{ $orden->id }}</h1>
        <span @class([
            'px-3 py-1 rounded text-sm font-semibold',
            'bg-yellow-200 text-yellow-900' => $orden->estado === 'pendiente',
            'bg-indigo-200 text-indigo-900' => $orden->estado === 'programada',
            'bg-blue-200 text-blue-900' => $orden->estado === 'en_progreso',
            'bg-green-200 text-green-900' => $orden->estado === 'completado',
            'bg-red-200 text-red-900' => $orden->estado === 'no_realizada',
        ])>{{ $orden->etiquetaEstado() }}</span>
    </div>

    <div class="bg-slate-50 border rounded p-4 mb-4 grid gap-x-6 gap-y-1 text-sm md:grid-cols-2">
        <p><span class="font-semibold">Caso:</span> {{ $orden->soporte->numero_soporte ?? '#'.$orden->soporte->id }}</p>
        <p><span class="font-semibold">Cliente:</span> {{ $orden->soporte->cliente->nombre }}</p>
        <p><span class="font-semibold">Dirección:</span> {{ $orden->soporte->cliente->direccion ?? '—' }}</p>
        <p><span class="font-semibold">Teléfono:</span> {{ $orden->soporte->cliente->telefono ?? '—' }}</p>
        <p><span class="font-semibold">Técnico:</span> {{ $orden->tecnico->user->name ?? $orden->tecnico->nombre }}</p>
        <p><span class="font-semibold">Tipo:</span> {{ \App\Models\OrdenTrabajo::TIPOS[$orden->tipo] ?? $orden->tipo }}</p>

        @if ($orden->fecha_programada)
            <p>
                <span class="font-semibold">Programada:</span>
                {{ $orden->fecha_programada->format('d/m/Y') }}
                {{ \App\Models\OrdenTrabajo::FRANJAS[$orden->franja] ?? '' }}
            </p>
        @endif
        @if ($orden->hora_inicio)
            <p><span class="font-semibold">Inicio:</span> {{ $orden->hora_inicio->format('d/m/Y H:i') }}</p>
        @endif
        @if ($orden->hora_fin)
            <p><span class="font-semibold">Fin:</span> {{ $orden->hora_fin->format('d/m/Y H:i') }}</p>
        @endif
        @if ($orden->cumplioProgramacion() === false)
            <p class="md:col-span-2 text-orange-800">
                <span class="font-semibold">Atención:</span> la visita no se ejecutó el día programado.
            </p>
        @endif

        <p class="md:col-span-2 pt-2">
            <span class="font-semibold">Problema reportado:</span> {{ $orden->soporte->descripcion }}
        </p>
    </div>

    @unless ($this->puedeEditar)
        <p class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-3 rounded mb-4 text-sm">
            Esta visita está asignada a otro técnico, solo puedes consultarla.
        </p>
    @endunless

    {{-- Materiales --}}
    <div class="bg-white border rounded p-4 mb-4">
        <h2 class="font-semibold text-slate-800 mb-2">Materiales usados</h2>

        @if ($orden->materialesUsados->count())
            <ul class="text-sm divide-y mb-3">
                @foreach ($orden->materialesUsados as $uso)
                    <li class="py-2 flex justify-between items-center">
                        <span>{{ $uso->material->nombre ?? '—' }} × {{ $uso->cantidad_usada }}</span>
                        @if ($this->puedeEditar && $orden->estaAbierta())
                            <button wire:click="quitarMaterial({{ $uso->id }})"
                                wire:confirm="¿Devolver este material al inventario?"
                                class="text-red-600 text-xs">Quitar</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-slate-500 mb-3">Sin materiales registrados.</p>
        @endif

        @if ($this->puedeEditar && $orden->estaAbierta())
            <div class="flex flex-wrap gap-2 items-end">
                <div class="flex-1 min-w-48">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Material</label>
                    <select wire:model="material_id" class="w-full border rounded p-2 text-sm">
                        <option value="">— Selecciona —</option>
                        @foreach ($this->materiales as $m)
                            <option value="{{ $m->id }}">{{ $m->nombre }} (stock: {{ $m->cantidad_total }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-24">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Cantidad</label>
                    <input type="number" min="1" wire:model="cantidad_usada" class="w-full border rounded p-2 text-sm">
                </div>
                <button wire:click="agregarMaterial" class="bg-slate-700 text-white px-4 py-2 rounded text-sm">
                    Agregar
                </button>
            </div>
            @error('material_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            @error('cantidad_usada') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        @endif
    </div>

    {{-- Estado --}}
    @if ($this->puedeEditar)
        <form wire:submit="actualizar" class="space-y-4 bg-white border rounded p-4">
            <div>
                <label class="block font-medium mb-1 text-slate-800">Estado de la visita</label>
                <select wire:model.live="estado" class="w-full border rounded p-2">
                    @foreach (\App\Models\OrdenTrabajo::ESTADOS as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>

            @if ($estado === 'completado')
                <div>
                    <label class="block font-medium mb-1 text-slate-800">Diagnóstico</label>
                    <select wire:model="diagnostico_id" class="w-full border rounded p-2">
                        <option value="">— Selecciona —</option>
                        @foreach ($this->diagnosticos as $d)
                            <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                        @endforeach
                    </select>
                    @error('diagnostico_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <p class="text-xs text-slate-500 mt-1">Al completar la visita el caso queda solucionado.</p>
                </div>
            @endif

            @if ($estado === 'no_realizada')
                <div>
                    <label class="block font-medium mb-1 text-slate-800">¿Por qué no se pudo hacer?</label>
                    <textarea wire:model="motivo_no_realizada" rows="2" class="w-full border rounded p-2"
                        placeholder="Nadie en la vivienda, dirección errada, clima..."></textarea>
                    @error('motivo_no_realizada') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    <p class="text-xs text-slate-500 mt-1">El caso vuelve al nivel 2 y el reloj se reanuda.</p>
                </div>
            @endif

            <div>
                <label class="block font-medium mb-1 text-slate-800">Observaciones</label>
                <textarea wire:model="observaciones" rows="4" class="w-full border rounded p-2"
                    placeholder="Detalles del trabajo realizado..."></textarea>
                @error('observaciones') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Guardar cambios</button>
        </form>
    @endif
</div>
