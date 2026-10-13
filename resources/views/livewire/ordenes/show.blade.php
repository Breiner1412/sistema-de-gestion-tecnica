<?php

use App\Models\Diagnostico;
use App\Models\Inventario;
use App\Models\MaterialOrden;
use App\Models\OrdenTrabajo;
use App\Services\CierreDeVisita;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        // Antes no se comprobaba nada aquí: cualquier técnico de campo abría la
        // visita de otro, con la dirección y el teléfono del cliente.
        $this->authorize('view', $orden);

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
            'evidencias',
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
        return auth()->user()->can('update', $this->orden);
    }

    /**
     * Una visita cerrada ya movió el caso y el inventario: reabrirla o tocar
     * su material desde aquí descuadraba los dos sin pasar por ninguna regla.
     */
    private function exigirAbierta(): void
    {
        if (! $this->orden->refresh()->estaAbierta()) {
            throw ValidationException::withMessages([
                'estado' => 'La visita ya está cerrada y no se puede modificar.',
            ]);
        }
    }

    public function agregarMaterial(): void
    {
        $this->authorize('update', $this->orden);
        $this->exigirAbierta();

        $this->validate([
            'material_id' => 'required|exists:inventario,id',
            'cantidad_usada' => 'required|integer|min:1',
        ]);

        // Si no hay stock, el observer lanza ValidationException en creating()
        // y no se inserta nada. La transacción une el descuento y su
        // movimiento de kardex: o quedan los dos, o ninguno.
        DB::transaction(fn () => MaterialOrden::create([
            'orden_id' => $this->orden->id,
            'material_id' => $this->material_id,
            'cantidad_usada' => $this->cantidad_usada,
        ]));

        $this->reset('material_id');
        $this->cantidad_usada = 1;
        unset($this->materiales);
        $this->recargar();

        session()->flash('mensaje', 'Material registrado y descontado del inventario.');
    }

    public function quitarMaterial(int $id): void
    {
        $this->authorize('update', $this->orden);
        $this->exigirAbierta();

        DB::transaction(fn () => MaterialOrden::where('orden_id', $this->orden->id)->whereKey($id)->first()?->delete());

        unset($this->materiales);
        $this->recargar();

        session()->flash('mensaje', 'Material devuelto al inventario.');
    }

    public function actualizar(): void
    {
        $this->authorize('update', $this->orden);
        $this->exigirAbierta();

        $reglas = [
            'estado' => ['required', Rule::in(array_keys(OrdenTrabajo::ESTADOS))],
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

        // Cerrar una visita mueve el caso, el reloj y el inventario. Eso vive en
        // un solo sitio: el mismo servicio que usa el cierre desde el celular.
        if (in_array($this->estado, OrdenTrabajo::ESTADOS_FINALES, true)) {
            $resultado = CierreDeVisita::para($this->orden)->registrar([
                'uuid' => (string) Str::uuid(),
                'estado' => $this->estado,
                'diagnostico_id' => $this->diagnostico_id ?: null,
                'observaciones' => $this->observaciones,
                'motivo' => $this->motivo_no_realizada ?: null,
                'usuario_id' => auth()->id(),
            ]);

            foreach ($resultado['avisos'] as $aviso) {
                session()->flash('error', $aviso);
            }
        } else {
            $cambios = [
                'estado' => $this->estado,
                'observaciones' => $this->observaciones,
            ];

            if ($this->estado === OrdenTrabajo::ESTADO_EN_PROGRESO && !$this->orden->hora_inicio) {
                $cambios['hora_inicio'] = now();
            }

            $this->orden->update($cambios);
        }

        $this->recargar();

        session()->flash('mensaje', 'Visita actualizada.');
    }
}; ?>

<div class="mx-auto flex max-w-[1100px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
    <x-mensajes />

    <x-encabezado :titulo="'Visita #'.$orden->id" :subtitulo="($orden->soporte->cliente->nombre ?? '—').' · '.(\App\Models\OrdenTrabajo::TIPOS[$orden->tipo] ?? $orden->tipo)">
        <x-slot:antes>
            <a href="{{ route('soportes.show', $orden->soporte) }}" wire:navigate class="inline-flex items-center gap-1 text-tinta-3 no-underline hover:text-acento-texto">
                <svg width="13" height="13" viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                Caso <span class="font-mono">{{ $orden->soporte->numero_soporte ?? '#'.$orden->soporte->id }}</span>
            </a>
        </x-slot:antes>
        <x-slot:acciones>
            <x-estado :estado="$orden->estado" :etiqueta="$orden->etiquetaEstado()" class="h-7 px-3 text-[13px]" />
        </x-slot:acciones>
    </x-encabezado>

    @unless ($this->puedeEditar)
        <p class="flex items-center gap-2 rounded-tarjeta border border-linea bg-elevada px-4 py-3 text-sm text-tinta-2">
            <svg width="15" height="15" viewBox="0 0 16 16" aria-hidden="true"><rect x="3.5" y="7" width="9" height="6.5" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"></rect><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2" fill="none" stroke="currentColor" stroke-width="1.5"></path></svg>
            Esta visita está asignada a otro técnico, solo puedes consultarla.
        </p>
    @endunless

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div class="flex min-w-0 flex-col gap-4">
            <section class="tarjeta p-4">
                <h2 class="titulo-seccion mb-3">Datos de la visita</h2>
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-tinta-3">Cliente</dt>
                        <dd class="font-medium">{{ $orden->soporte->cliente->nombre }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-tinta-3">Teléfono</dt>
                        <dd class="font-mono text-[13px]">{{ $orden->soporte->cliente->telefono ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-tinta-3">Dirección</dt>
                        <dd>{{ $orden->soporte->cliente->direccion ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-tinta-3">Técnico</dt>
                        <dd>{{ $orden->tecnico->user->name ?? $orden->tecnico->nombre }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-tinta-3">Tipo</dt>
                        <dd>{{ \App\Models\OrdenTrabajo::TIPOS[$orden->tipo] ?? $orden->tipo }}</dd>
                    </div>
                    @if ($orden->fecha_programada)
                        <div>
                            <dt class="text-xs font-medium text-tinta-3">Programada</dt>
                            <dd><span class="font-mono text-[13px]">{{ $orden->fecha_programada->format('d/m/Y') }}</span> {{ \App\Models\OrdenTrabajo::FRANJAS[$orden->franja] ?? '' }}</dd>
                        </div>
                    @endif
                    @if ($orden->hora_inicio)
                        <div>
                            <dt class="text-xs font-medium text-tinta-3">Inicio</dt>
                            <dd class="font-mono text-[13px]">{{ $orden->hora_inicio->format('d/m/Y H:i') }}</dd>
                        </div>
                    @endif
                    @if ($orden->hora_fin)
                        <div>
                            <dt class="text-xs font-medium text-tinta-3">Fin</dt>
                            <dd class="font-mono text-[13px]">{{ $orden->hora_fin->format('d/m/Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($orden->cumplioProgramacion() === false)
                    <p class="mt-3 inline-flex items-center gap-1.5 rounded bg-sla-atencion-fondo px-2 py-1 text-[13px] font-medium text-sla-atencion">
                        <x-sla-glifo estado="atencion" :tamano="11" />
                        La visita no se ejecutó el día programado.
                    </p>
                @endif

                <div class="mt-4 border-t border-linea-suave pt-3">
                    <p class="text-xs font-medium text-tinta-3">Problema reportado</p>
                    <p class="mt-0.5 whitespace-pre-line text-sm">{{ $orden->soporte->descripcion }}</p>
                </div>
            </section>

            {{-- Lo que el técnico dejó desde el celular. Vive aquí porque la oficina es
                 quien lo mira: si la evidencia no se ve desde escritorio, tomarla no sirve. --}}
            @if ($orden->evidencias->isNotEmpty() || $orden->firma_path || $orden->latitud_fin)
                <section class="tarjeta p-4">
                    <h2 class="titulo-seccion mb-3">Evidencia de la visita</h2>

                    @if ($orden->cerradaEnDiferido())
                        <p class="mb-3 rounded-control border border-dashed border-linea bg-elevada px-3 py-2 text-xs text-tinta-2">
                            Se cerró en terreno el
                            <span class="font-mono">{{ $orden->cerrada_en_terreno_at->format('d/m/Y \a \l\a\s H:i') }}</span>
                            y se sincronizó cuando volvió la señal.
                        </p>
                    @endif

                    @if ($orden->evidencias->isNotEmpty())
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach ($orden->evidencias as $evidencia)
                                <a href="{{ $evidencia->url() }}" target="_blank" rel="noopener" class="block no-underline">
                                    <img src="{{ $evidencia->url() }}" alt="Foto de la visita"
                                         class="aspect-square w-full rounded-control border border-linea object-cover">
                                    @if ($evidencia->descripcion)
                                        <p class="mt-1 truncate text-xs text-tinta-3">{{ $evidencia->descripcion }}</p>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @if ($orden->firma_path)
                            <div>
                                <p class="mb-1 text-xs font-medium text-tinta-3">Firma del cliente</p>
                                {{-- Fondo blanco también en oscuro: la firma es tinta oscura. --}}
                                <img src="{{ $orden->urlFirma() }}" alt="Firma del cliente"
                                     class="h-24 rounded-control border border-linea bg-white p-2">
                            </div>
                        @endif

                        @if ($orden->latitud_fin && $orden->longitud_fin)
                            <div>
                                <p class="mb-1 text-xs font-medium text-tinta-3">Dónde se cerró</p>
                                <a class="font-mono text-[13px]" target="_blank" rel="noopener"
                                   href="https://maps.google.com/?q={{ $orden->latitud_fin }},{{ $orden->longitud_fin }}">
                                    {{ $orden->latitud_fin }}, {{ $orden->longitud_fin }}
                                </a>
                            </div>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        <div class="flex flex-col gap-4">
            {{-- Materiales --}}
            <section class="tarjeta p-4">
                <h2 class="titulo-seccion mb-2">Materiales usados</h2>

                @if ($orden->materialesUsados->count())
                    <ul class="mb-3 divide-y divide-linea-suave text-sm">
                        @foreach ($orden->materialesUsados as $uso)
                            <li class="flex items-center justify-between gap-3 py-2">
                                <span>{{ $uso->material->nombre ?? '—' }} <span class="font-mono text-tinta-3">× {{ $uso->cantidad_usada }}</span></span>
                                @if ($this->puedeEditar && $orden->estaAbierta())
                                    <x-boton variante="fantasma" tamano="chico" class="!text-peligro" wire:click="quitarMaterial({{ $uso->id }})"
                                        wire:confirm="¿Devolver este material al inventario?">Quitar</x-boton>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mb-3 text-sm text-tinta-3">Sin materiales registrados.</p>
                @endif

                @if ($this->puedeEditar && $orden->estaAbierta())
                    <div class="flex flex-wrap items-end gap-2 border-t border-linea-suave pt-3">
                        <div class="min-w-0 flex-1 basis-40">
                            <label for="material" class="etiqueta text-[13px]">Material</label>
                            <select id="material" wire:model="material_id" class="control h-9 py-0">
                                <option value="">— Selecciona —</option>
                                @foreach ($this->materiales as $m)
                                    <option value="{{ $m->id }}">{{ $m->nombre }} (stock: {{ $m->cantidad_total }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-20">
                            <label for="cantidad" class="etiqueta text-[13px]">Cantidad</label>
                            <input id="cantidad" type="number" min="1" wire:model="cantidad_usada" class="control h-9 font-mono">
                        </div>
                        <x-boton wire:click="agregarMaterial">Agregar</x-boton>
                    </div>
                    @error('material_id') <p class="error-campo">{{ $message }}</p> @enderror
                    @error('cantidad_usada') <p class="error-campo">{{ $message }}</p> @enderror
                @endif
            </section>

            {{-- Estado --}}
            @if ($this->puedeEditar && $orden->estaAbierta())
                <form wire:submit="actualizar" class="tarjeta space-y-4 p-4">
                    <h2 class="titulo-seccion">Registrar resultado</h2>
                    <div>
                        <label for="estado-visita" class="etiqueta">Estado de la visita</label>
                        <select id="estado-visita" wire:model.live="estado" class="control h-9 py-0">
                            @foreach (\App\Models\OrdenTrabajo::ESTADOS as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($estado === 'completado')
                        <div>
                            <label for="diagnostico" class="etiqueta">Diagnóstico</label>
                            <select id="diagnostico" wire:model="diagnostico_id" class="control h-9 py-0">
                                <option value="">— Selecciona —</option>
                                @foreach ($this->diagnosticos as $d)
                                    <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                                @endforeach
                            </select>
                            @error('diagnostico_id') <p class="error-campo">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-tinta-3">Al completar la visita el caso queda solucionado.</p>
                        </div>
                    @endif

                    @if ($estado === 'no_realizada')
                        <div>
                            <label for="motivo-no-realizada" class="etiqueta">¿Por qué no se pudo hacer?</label>
                            <textarea id="motivo-no-realizada" wire:model="motivo_no_realizada" rows="2" class="control"
                                placeholder="Nadie en la vivienda, dirección errada, clima..."></textarea>
                            @error('motivo_no_realizada') <p class="error-campo">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-tinta-3">El caso vuelve al nivel 2 y el reloj se reanuda.</p>
                        </div>
                    @endif

                    <div>
                        <label for="observaciones" class="etiqueta">Observaciones</label>
                        <textarea id="observaciones" wire:model="observaciones" rows="4" class="control"
                            placeholder="Detalles del trabajo realizado..."></textarea>
                        @error('observaciones') <p class="error-campo">{{ $message }}</p> @enderror
                    </div>

                    <x-boton variante="primario" type="submit" class="w-full">Guardar cambios</x-boton>
                </form>
            @endif
        </div>
    </div>
</div>
