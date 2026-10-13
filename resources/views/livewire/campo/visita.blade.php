<?php

use App\Models\Diagnostico;
use App\Models\Inventario;
use App\Models\OrdenTrabajo;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.campo')] class extends Component {
    public OrdenTrabajo $orden;

    public function mount(OrdenTrabajo $orden): void
    {
        $this->authorize('update', $orden);

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


<div class="px-4 py-4">
    @php($cliente = $orden->soporte?->cliente)

    <a href="{{ route('campo.ruta') }}" wire:navigate class="-ml-2 inline-flex h-11 items-center gap-1.5 rounded-control px-2 text-[15px] font-semibold text-acento-texto no-underline">
        <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
        Mi ruta
    </a>

    {{-- Datos del sitio: lo primero que mira el técnico al llegar. --}}
    <div class="mt-1 rounded-[14px] border border-linea bg-superficie p-4">
        <div class="flex items-start justify-between gap-3">
            <h1 class="text-[22px] font-semibold leading-tight">{{ $cliente?->nombre ?? 'Sin cliente' }}</h1>
            @if ($orden->soporte?->criticidad === 'inmediata')
                <x-inmediata class="mt-1 shrink-0" />
            @endif
        </div>
        <p class="mt-1 flex items-start gap-1.5 text-[15px] text-tinta-2">
            <svg class="mt-[3px] shrink-0 text-tinta-3" width="14" height="14" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 17.5s5.5-5 5.5-9.5a5.5 5.5 0 0 0-11 0c0 4.5 5.5 9.5 5.5 9.5Z" fill="none" stroke="currentColor" stroke-width="1.8"></path><circle cx="10" cy="8" r="2" fill="none" stroke="currentColor" stroke-width="1.8"></circle></svg>
            {{ $cliente?->direccion ?: 'Sin dirección registrada' }}
        </p>

        <div class="mt-3 flex flex-wrap gap-2 text-xs">
            <span class="inline-flex h-[22px] items-center rounded-[4px] bg-elevada px-2 font-mono text-tinta-2">{{ $orden->soporte?->numero_soporte }}</span>
            <span class="inline-flex h-[22px] items-center rounded-full border border-linea px-2 font-medium text-tinta-2">{{ $orden->etiquetaTipo() }}</span>
            @if ($orden->soporte?->tipoFalla)
                <span class="inline-flex h-[22px] items-center rounded-full border border-linea px-2 font-medium text-tinta-2">{{ $orden->soporte->tipoFalla->nombre }}</span>
            @endif
        </div>

        @if ($orden->soporte?->descripcion)
            <p class="mt-3 border-t border-linea-suave pt-3 text-[15px] text-tinta">{{ $orden->soporte->descripcion }}</p>
        @endif

        @if ($cliente?->telefono)
            <a href="tel:{{ $cliente->telefono }}"
               class="mt-3 flex h-14 items-center justify-center gap-2 rounded-[12px] bg-acento-tinte text-[17px] font-semibold text-acento-tinte-texto no-underline">
                <svg width="18" height="18" viewBox="0 0 20 20" aria-hidden="true"><path d="M5.5 2.5h2.2l1.2 3.6-1.7 1.2a9 9 0 0 0 5.5 5.5l1.2-1.7 3.6 1.2v2.2a1.6 1.6 0 0 1-1.7 1.6A14 14 0 0 1 3.9 4.2a1.6 1.6 0 0 1 1.6-1.7Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"></path></svg>
                Llamar a <span class="font-mono">{{ $cliente->telefono }}</span>
            </a>
        @endif
    </div>

    @if (! $orden->estaAbierta())
        <div class="mt-4 rounded-[14px] border border-linea bg-superficie p-6 text-center">
            <x-estado :estado="$orden->estado" :etiqueta="$orden->etiquetaEstado()" />
            <p class="mt-3 text-[17px] font-semibold">Esta visita ya está {{ strtolower($orden->etiquetaEstado()) }}.</p>
            @if ($orden->hora_fin)
                <p class="mt-1 text-sm text-tinta-3">Cerrada el <span class="font-mono">{{ $orden->hora_fin->format('d/m/Y \a \l\a\s H:i') }}</span>.</p>
            @endif
            @if ($orden->cerradaEnDiferido())
                <p class="mt-1 text-xs text-tinta-3">Se cerró sin señal y se sincronizó después.</p>
            @endif
        </div>

        @if ($orden->evidencias->isNotEmpty())
            <div class="mt-4 grid grid-cols-3 gap-2">
                @foreach ($orden->evidencias as $evidencia)
                    <img src="{{ $evidencia->url() }}" alt="Evidencia de la visita"
                         class="aspect-square w-full rounded-[10px] border border-linea object-cover">
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
                 catalogo: {{ \Illuminate\Support\Js::from($catalogo) }},
             })">

            {{-- Confirmación --}}
            <template x-if="listo">
                <div class="rounded-[14px] border border-linea bg-superficie p-8 text-center">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-full"
                          :class="sesionCaducada || guardadoSinSenal ? 'bg-acento-tinte text-acento-tinte-texto' : 'bg-sla-ok-fondo text-sla-ok'">
                        <svg width="22" height="22" viewBox="0 0 10 10" aria-hidden="true"><path d="M1.5 5.2 4 7.6 8.6 2.6" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </span>
                    <p class="mt-3 text-xl font-semibold"
                       x-text="sesionCaducada || guardadoSinSenal ? 'Guardado en el teléfono' : 'Visita cerrada'"></p>
                    <p class="mt-2 text-[15px] text-tinta-2"
                       x-text="sesionCaducada
                           ? 'Tu sesión se cerró. Entra otra vez y el cierre se enviará solo.'
                           : (guardadoSinSenal
                               ? 'Se enviará solo cuando vuelva la señal. Ya puedes seguir con la siguiente.'
                               : 'El caso quedó cerrado en el sistema.')"></p>
                    <template x-if="sesionCaducada">
                        <x-boton variante="primario" tamano="campo" :href="route('login')" class="mt-5">Entrar otra vez</x-boton>
                    </template>
                </div>
            </template>

            <div x-show="!listo">
                {{-- Resultado: el elegido lleva relleno, marca y borde grueso. --}}
                <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Resultado de la visita">
                    <button type="button" role="radio" @click="estado = 'completado'" :aria-checked="completa"
                            :class="completa ? 'border-acento bg-acento text-white' : 'border-linea bg-superficie text-tinta-2'"
                            class="flex h-[60px] items-center justify-center gap-2 rounded-[12px] border-2 text-[17px] font-semibold transition-colors">
                        <svg width="18" height="18" viewBox="0 0 10 10" aria-hidden="true"><path d="M1.5 5.2 4 7.6 8.6 2.6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                        Se resolvió
                    </button>
                    <button type="button" role="radio" @click="estado = 'no_realizada'" :aria-checked="!completa"
                            :class="!completa ? 'border-tinta bg-tinta text-fondo' : 'border-linea bg-superficie text-tinta-2'"
                            class="flex h-[60px] items-center justify-center gap-2 rounded-[12px] border-2 text-[17px] font-semibold transition-colors">
                        <svg width="16" height="16" viewBox="0 0 10 10" aria-hidden="true"><path d="M2 2l6 6M8 2 2 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>
                        No se pudo
                    </button>
                </div>

                {{-- Rama: se resolvió --}}
                <div x-show="completa" class="mt-4 space-y-3">
                    <div class="rounded-[14px] border border-linea bg-superficie p-4">
                        <label for="diagnostico" class="etiqueta text-[15px] font-semibold">¿Qué era?</label>
                        <select id="diagnostico" x-model="diagnosticoId" class="control-campo">
                            <option value="">Selecciona el diagnóstico</option>
                            @foreach ($diagnosticos as $diagnostico)
                                <option value="{{ $diagnostico->id }}">{{ $diagnostico->nombre }}</option>
                            @endforeach
                        </select>

                        <label for="observaciones" class="etiqueta mt-4 text-[15px] font-semibold">¿Qué hiciste?</label>
                        <textarea id="observaciones" x-model="observaciones" rows="4" class="control-campo"
                                  placeholder="Cambio de conector en la acometida, se midió potencia y quedó en -18 dBm."></textarea>
                        <p class="mt-1 min-h-4 text-[13px] text-tinta-3"
                           x-text="observaciones.trim().length < 10 ? 'Falta describir el trabajo.' : ''"></p>
                    </div>

                    {{-- Material --}}
                    <div class="rounded-[14px] border border-linea bg-superficie p-4">
                        <p class="text-[15px] font-semibold">Material usado</p>

                        <template x-for="(material, indice) in materiales" :key="indice">
                            <div class="mt-2 flex min-h-12 items-center justify-between rounded-[10px] bg-elevada pl-3">
                                <span class="text-[15px]" x-text="`${material.cantidad} × ${material.nombre}`"></span>
                                <button type="button" @click="quitarMaterial(indice)"
                                        class="h-12 px-4 text-[15px] font-semibold text-peligro">Quitar</button>
                            </div>
                        </template>

                        <div class="mt-3 flex gap-2">
                            <select x-model="materialElegido" class="control-campo min-w-0 flex-1" aria-label="Material">
                                <option value="">Agregar material…</option>
                                <template x-for="material in catalogo" :key="material.id">
                                    <option :value="material.id" x-text="material.nombre"></option>
                                </template>
                            </select>
                            <input type="number" x-model="cantidad" min="1" inputmode="numeric" aria-label="Cantidad"
                                   class="control-campo !w-20 text-center font-mono">
                            <button type="button" @click="agregarMaterial()" aria-label="Agregar material"
                                    class="flex w-[52px] shrink-0 items-center justify-center rounded-[10px] bg-acento text-white">
                                <svg width="18" height="18" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Fotos --}}
                    <div class="rounded-[14px] border border-linea bg-superficie p-4">
                        <p class="text-[15px] font-semibold">Fotos <span class="font-normal text-tinta-3">(hasta 6)</span></p>

                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <template x-for="(foto, indice) in fotos" :key="indice">
                                <div class="relative">
                                    <img :src="foto.contenido" alt="" class="aspect-square w-full rounded-[10px] object-cover">
                                    <button type="button" @click="quitarFoto(indice)" aria-label="Quitar foto"
                                            class="absolute -right-1.5 -top-1.5 flex size-8 items-center justify-center rounded-full border-2 border-superficie bg-sla-vencido text-white">
                                        <svg width="12" height="12" viewBox="0 0 10 10" aria-hidden="true"><path d="M2 2l6 6M8 2 2 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
                                    </button>
                                </div>
                            </template>

                            <label x-show="fotos.length < 6"
                                   class="flex aspect-square w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-[10px] border-2 border-dashed border-linea text-tinta-3">
                                <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h3l1.5-2h7L17 8h3v11H4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"></path><circle cx="12" cy="13" r="3.2" fill="none" stroke="currentColor" stroke-width="1.6"></circle></svg>
                                <span class="text-xs font-semibold">Tomar foto</span>
                                {{-- capture="environment" abre la cámara trasera directamente. --}}
                                <input type="file" accept="image/*" capture="environment" multiple
                                       class="hidden" @change="agregarFoto($event)">
                            </label>
                        </div>
                    </div>

                    {{-- Firma --}}
                    <div class="rounded-[14px] border border-linea bg-superficie p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-[15px] font-semibold">Firma del cliente</p>
                            <button type="button" @click="borrarFirma()" class="-mr-2 h-11 px-2 text-[15px] font-semibold text-acento-texto">Borrar</button>
                        </div>

                        {{-- touch-none: sin esto el dedo arrastra la página en
                             vez de dibujar sobre el lienzo. El lienzo es blanco
                             también de noche: el trazo es tinta oscura. --}}
                        <canvas x-init="prepararFirma($el)"
                                class="mt-1 h-40 w-full touch-none rounded-[10px] border-2 border-dashed border-linea bg-white dark:bg-[#D9DEE5]"></canvas>
                        <p class="mt-1 text-[13px] text-tinta-3">Opcional. Que firme con el dedo.</p>
                    </div>
                </div>

                {{-- Rama: no se pudo --}}
                <div x-show="!completa" class="mt-4 rounded-[14px] border border-linea bg-superficie p-4">
                    <label for="motivo" class="etiqueta text-[15px] font-semibold">¿Por qué no se pudo?</label>
                    <textarea id="motivo" x-model="motivo" rows="3" class="control-campo"
                              placeholder="No había nadie en la casa. Se llamó dos veces y no contestaron."></textarea>
                    <p class="mt-2 text-[13px] text-tinta-3">El caso vuelve a la cola de soporte y el reloj se reanuda.</p>
                </div>

                <p x-show="error" x-text="error" role="alert" class="aviso-error mt-3"></p>

                {{-- Botón fijo abajo: al alcance del pulgar, siempre visible. --}}
                <div class="fixed inset-x-0 bottom-0 border-t border-linea bg-superficie/95 p-4 pb-[max(1rem,env(safe-area-inset-bottom))] backdrop-blur">
                    <div class="mx-auto max-w-xl">
                        <button type="button" @click="enviar()" :disabled="!puedeEnviar"
                                class="flex h-[60px] w-full items-center justify-center rounded-[12px] bg-acento text-[17px] font-semibold text-white transition-colors hover:bg-acento-hover disabled:bg-linea disabled:text-tinta-3">
                            <span x-show="!enviando" x-text="completa ? 'Cerrar la visita' : 'Registrar que no se pudo'"></span>
                            <span x-show="enviando">Guardando…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
