<?php

use App\Models\Diagnostico;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.campo')] class extends Component {
    public OrdenTrabajo $orden;

    public function mount(OrdenTrabajo $orden): void
    {
        $usuario = auth()->user();

        abort_unless(
            $usuario->tieneRol(...User::ROLES_GESTION) || $usuario->tecnico?->id === $orden->tecnico_id,
            403,
        );

        $this->orden = $orden->load(['soporte.cliente', 'soporte.tipoFalla', 'evidencias']);
    }

    public function with(): array
    {
        return [
            // Los dos catálogos viajan con la página, no se piden después: si el
            // técnico pierde la señal después de abrirla, el formulario tiene
            // que seguir completo.
            'diagnosticos' => Diagnostico::activos()->orderBy('nombre')->get(['id', 'nombre']),
            'catalogo' => Inventario::where('cantidad_total', '>', 0)
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->values(),
        ];
    }
}; ?>

@php($cliente = $orden->soporte?->cliente)

<div class="px-4 py-4">

    <a href="{{ route('campo.ruta') }}" wire:navigate class="text-sm text-sky-700">&larr; Mi ruta</a>

    {{-- Datos del sitio: lo primero que mira el técnico al llegar. --}}
    <div class="mt-2 rounded-xl border bg-white p-4 shadow-sm">
        <h1 class="text-lg font-bold">{{ $cliente?->nombre ?? 'Sin cliente' }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $cliente?->direccion ?: 'Sin dirección registrada' }}</p>

        <div class="mt-3 flex flex-wrap gap-2 text-xs">
            <span class="rounded bg-slate-100 px-2 py-1">{{ $orden->soporte?->numero_soporte }}</span>
            <span class="rounded bg-slate-100 px-2 py-1">{{ $orden->etiquetaTipo() }}</span>
            @if ($orden->soporte?->tipoFalla)
                <span class="rounded bg-slate-100 px-2 py-1">{{ $orden->soporte->tipoFalla->nombre }}</span>
            @endif
            @if ($orden->soporte?->criticidad === 'inmediata')
                <span class="rounded bg-red-100 px-2 py-1 font-semibold text-red-700">Inmediata</span>
            @endif
        </div>

        <p class="mt-3 border-t pt-3 text-sm text-slate-700">{{ $orden->soporte?->descripcion }}</p>

        @if ($cliente?->telefono)
            <a href="tel:{{ $cliente->telefono }}"
               class="mt-3 block rounded-lg bg-sky-50 py-3 text-center font-semibold text-sky-700">
                Llamar a {{ $cliente->telefono }}
            </a>
        @endif
    </div>

    @if (! $orden->estaAbierta())
        <div class="mt-4 rounded-xl border bg-white p-6 text-center shadow-sm">
            <p class="font-semibold">Esta visita ya está {{ strtolower($orden->etiquetaEstado()) }}.</p>
            @if ($orden->hora_fin)
                <p class="mt-1 text-sm text-slate-500">Cerrada el {{ $orden->hora_fin->format('d/m/Y \a \l\a\s H:i') }}.</p>
            @endif
            @if ($orden->cerradaEnDiferido())
                <p class="mt-1 text-xs text-slate-400">Se cerró sin señal y se sincronizó después.</p>
            @endif
        </div>

        @if ($orden->evidencias->isNotEmpty())
            <div class="mt-4 grid grid-cols-3 gap-2">
                @foreach ($orden->evidencias as $evidencia)
                    <img src="{{ $evidencia->url() }}" alt="Evidencia de la visita"
                         class="aspect-square w-full rounded-lg object-cover">
                @endforeach
            </div>
        @endif
    @else
        {{-- El cierre no pasa por Livewire: es Alpine puro contra un endpoint,
             porque tiene que funcionar con el teléfono sin señal. --}}
        <div class="mt-4"
             x-data="cierreDeVisita({
                 urlInicio: '{{ route('campo.iniciar', $orden) }}',
                 urlCierre: '{{ route('campo.cerrar', $orden) }}',
                 urlRuta: '{{ route('campo.ruta') }}',
                 yaIniciada: {{ $orden->hora_inicio ? 'true' : 'false' }},
                 catalogo: {{ Js::from($catalogo) }},
             })">

            {{-- Confirmación --}}
            <template x-if="listo">
                <div class="rounded-xl border bg-white p-8 text-center shadow-sm">
                    <p class="text-lg font-bold" x-text="guardadoSinSenal ? 'Guardado en el teléfono' : 'Visita cerrada'"></p>
                    <p class="mt-2 text-sm text-slate-600"
                       x-text="guardadoSinSenal
                           ? 'Se enviará solo cuando vuelva la señal. Ya puedes seguir con la siguiente.'
                           : 'El caso quedó cerrado en el sistema.'"></p>
                </div>
            </template>

            <div x-show="!listo">
                {{-- Resultado --}}
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="estado = 'completado'"
                            :class="completa ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600 border'"
                            class="rounded-lg py-4 font-semibold transition">Se resolvió</button>
                    <button type="button" @click="estado = 'no_realizada'"
                            :class="!completa ? 'bg-amber-600 text-white' : 'bg-white text-slate-600 border'"
                            class="rounded-lg py-4 font-semibold transition">No se pudo</button>
                </div>

                {{-- Rama: se resolvió --}}
                <div x-show="completa" class="mt-4 space-y-4">
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <label class="block text-sm font-semibold">¿Qué era?</label>
                        <select x-model="diagnosticoId" class="mt-2 w-full rounded-lg border p-3 text-base">
                            <option value="">Selecciona el diagnóstico</option>
                            @foreach ($diagnosticos as $diagnostico)
                                <option value="{{ $diagnostico->id }}">{{ $diagnostico->nombre }}</option>
                            @endforeach
                        </select>

                        <label class="mt-4 block text-sm font-semibold">¿Qué hiciste?</label>
                        <textarea x-model="observaciones" rows="4"
                                  class="mt-2 w-full rounded-lg border p-3 text-base"
                                  placeholder="Cambio de conector en la acometida, se midió potencia y quedó en -18 dBm."></textarea>
                        <p class="mt-1 text-xs text-slate-400"
                           x-text="observaciones.trim().length < 10 ? 'Falta describir el trabajo.' : ''"></p>
                    </div>

                    {{-- Material --}}
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <p class="text-sm font-semibold">Material usado</p>

                        <template x-for="(material, indice) in materiales" :key="indice">
                            <div class="mt-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                                <span class="text-sm" x-text="`${material.cantidad} × ${material.nombre}`"></span>
                                <button type="button" @click="quitarMaterial(indice)"
                                        class="px-2 text-sm font-semibold text-red-600">Quitar</button>
                            </div>
                        </template>

                        <div class="mt-3 flex gap-2">
                            <select x-model="materialElegido" class="min-w-0 flex-1 rounded-lg border p-3 text-sm">
                                <option value="">Agregar material…</option>
                                <template x-for="material in catalogo" :key="material.id">
                                    <option :value="material.id" x-text="material.nombre"></option>
                                </template>
                            </select>
                            <input type="number" x-model="cantidad" min="1" inputmode="numeric"
                                   class="w-20 rounded-lg border p-3 text-center text-sm">
                            <button type="button" @click="agregarMaterial()"
                                    class="rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white">+</button>
                        </div>
                    </div>

                    {{-- Fotos --}}
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <p class="text-sm font-semibold">Fotos <span class="font-normal text-slate-400">(hasta 6)</span></p>

                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <template x-for="(foto, indice) in fotos" :key="indice">
                                <div class="relative">
                                    <img :src="foto.contenido" alt="" class="aspect-square w-full rounded-lg object-cover">
                                    <button type="button" @click="quitarFoto(indice)"
                                            class="absolute -right-1 -top-1 h-7 w-7 rounded-full bg-red-600 text-sm font-bold text-white">×</button>
                                </div>
                            </template>

                            <label x-show="fotos.length < 6"
                                   class="flex aspect-square w-full cursor-pointer items-center justify-center rounded-lg border-2 border-dashed text-3xl text-slate-400">
                                +
                                {{-- capture="environment" abre la cámara trasera directamente. --}}
                                <input type="file" accept="image/*" capture="environment" multiple
                                       class="hidden" @change="agregarFoto($event)">
                            </label>
                        </div>
                    </div>

                    {{-- Firma --}}
                    <div class="rounded-xl border bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold">Firma del cliente</p>
                            <button type="button" @click="borrarFirma()" class="text-sm text-sky-700">Borrar</button>
                        </div>

                        {{-- touch-none: sin esto el dedo arrastra la página en
                             vez de dibujar sobre el lienzo. --}}
                        <canvas x-init="prepararFirma($el)"
                                class="mt-2 h-40 w-full touch-none rounded-lg border-2 border-dashed bg-slate-50"></canvas>
                        <p class="mt-1 text-xs text-slate-400">Opcional. Que firme con el dedo.</p>
                    </div>
                </div>

                {{-- Rama: no se pudo --}}
                <div x-show="!completa" class="mt-4 rounded-xl border bg-white p-4 shadow-sm">
                    <label class="block text-sm font-semibold">¿Por qué no se pudo?</label>
                    <textarea x-model="motivo" rows="3" class="mt-2 w-full rounded-lg border p-3 text-base"
                              placeholder="No había nadie en la casa. Se llamó dos veces y no contestaron."></textarea>
                    <p class="mt-2 text-xs text-slate-500">El caso vuelve a la cola de soporte y el reloj se reanuda.</p>
                </div>

                <p x-show="error" x-text="error" class="mt-3 rounded-lg bg-red-100 p-3 text-sm text-red-800"></p>

                {{-- Botón fijo abajo: al alcance del pulgar, siempre visible. --}}
                <div class="fixed inset-x-0 bottom-0 border-t bg-white/95 p-4 backdrop-blur">
                    <div class="mx-auto max-w-xl">
                        <button type="button" @click="enviar()" :disabled="!puedeEnviar"
                                class="w-full rounded-xl py-4 text-base font-bold text-white transition disabled:bg-slate-300"
                                :class="completa ? 'bg-emerald-600' : 'bg-amber-600'">
                            <span x-show="!enviando" x-text="completa ? 'Cerrar la visita' : 'Registrar que no se pudo'"></span>
                            <span x-show="enviando">Guardando…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
