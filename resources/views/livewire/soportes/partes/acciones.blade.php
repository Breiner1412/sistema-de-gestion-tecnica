{{-- Responsable de nivel 2 y acciones del caso. Cada botón se muestra según la
     política (SoportePolicy); la acción vuelve a preguntarla en el servidor. --}}

{{-- Responsable --}}
@unless ($soporte->estaCerrado())
    <section class="tarjeta p-4">
        <label for="tecnico-soporte" class="titulo-seccion mb-2 block">Técnico de soporte (nivel 2)</label>

        @if ($this->puedeReasignar)
            <div class="flex gap-2">
                <select id="tecnico-soporte" wire:model="tecnico_soporte_id" class="control h-9 min-w-0 flex-1 py-0">
                    <option value="">— Sin asignar —</option>
                    @foreach ($this->tecnicosSoporte as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
                <x-boton wire:click="asignar">Asignar</x-boton>
            </div>
            @error('tecnico_soporte_id') <p class="error-campo">{{ $message }}</p> @enderror
        @else
            <p @class(['text-sm', 'italic text-tinta-3' => ! $soporte->tecnicoSoporte])>{{ $soporte->tecnicoSoporte->name ?? 'Sin asignar' }}</p>
            @if ($this->ordenAbierta)
                <p class="mt-1 text-xs text-tinta-3">Hay una visita en curso. Solo un administrador puede reasignar.</p>
            @endif
        @endif
    </section>
@endunless

{{-- Acciones --}}
@unless ($soporte->estaCerrado())
    <section class="tarjeta p-4">
        <h2 class="titulo-seccion mb-3">Acciones</h2>

        <div class="flex flex-col gap-2">
            @if ($soporte->estado === 'escalado_n3')
                @if ($this->esResponsableN3)
                    <x-boton variante="primario" wire:click="$set('panel', 'redes')" class="w-full">
                        Responder y devolver al nivel 2
                    </x-boton>
                @else
                    <p class="text-sm text-tinta-3">
                        El caso está en manos de {{ $soporte->escaladoA->name ?? 'ingeniería de redes' }}.
                    </p>
                @endif
            @endif

            @if ($this->puedeTrabajar && $soporte->puedeTransicionarA('solucionado') && !$this->ordenAbierta)
                <x-boton variante="primario" wire:click="$set('panel', 'cerrar')" class="w-full">
                    <svg width="14" height="14" viewBox="0 0 10 10" aria-hidden="true"><path d="M1.5 5.2 4 7.6 8.6 2.6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    Resolver
                </x-boton>
            @endif

            @if ($this->ordenAbierta)
                <x-boton :href="route('ordenes.show', $this->ordenAbierta)" wire:navigate class="w-full">
                    Ver visita #{{ $this->ordenAbierta->id }}
                    @if ($this->ordenAbierta->fecha_programada)
                        <span class="font-mono font-normal text-tinta-3">{{ $this->ordenAbierta->fecha_programada->format('d/m') }}</span>
                    @endif
                </x-boton>
            @endif

            <div class="grid grid-cols-2 gap-2">
                @if (! $this->ordenAbierta && $this->puedeTrabajar && $soporte->puedeTransicionarA('enviado_tecnico'))
                    <x-boton wire:click="$set('panel', 'visita')">Programar visita</x-boton>
                @endif

                @if ($this->puedeTrabajar && $soporte->puedeTransicionarA('escalado_n3'))
                    <x-boton wire:click="$set('panel', 'escalar')">Escalar a redes</x-boton>
                @endif

                @if ($this->puedeTrabajar && $soporte->puedeTransicionarA('sin_contacto'))
                    <x-boton wire:click="sinContacto" wire:confirm="¿Registrar un intento de contacto sin respuesta?">
                        No contesta
                    </x-boton>
                @endif

                @if ($this->puedeTrabajar && $soporte->puedeTransicionarA('seguimiento'))
                    <x-boton wire:click="seguimiento">Seguimiento</x-boton>
                @endif

                @if ($this->puedeTrabajar && $soporte->estado === 'sin_contacto')
                    <x-boton wire:click="retomar">Retomar</x-boton>
                @endif

                @if ($this->puedeElevar && !$soporte->esInmediato())
                    <x-boton wire:click="$set('panel', 'criticidad')">Elevar a inmediato</x-boton>
                @endif

                @if ($this->puedeCancelar && $soporte->puedeTransicionarA('cancelado'))
                    <x-boton variante="peligro" wire:click="$set('panel', 'cancelar')">Cancelar</x-boton>
                @endif
            </div>
        </div>

        @if ($panel === 'cerrar')
            <div class="mt-4 space-y-3 border-t border-linea pt-4">
                <div>
                    <label for="diagnostico" class="etiqueta">Diagnóstico</label>
                    <select id="diagnostico" wire:model="diagnostico_id" class="control h-9 py-0">
                        <option value="">— Selecciona —</option>
                        @foreach ($this->diagnosticos as $d)
                            <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                        @endforeach
                    </select>
                    @error('diagnostico_id') <p class="error-campo">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="observaciones" class="etiqueta">¿Qué se hizo?</label>
                    <textarea id="observaciones" wire:model="observaciones_cierre" rows="3" class="control"></textarea>
                    @error('observaciones_cierre') <p class="error-campo">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-2">
                    <x-boton variante="primario" wire:click="resolver">Confirmar cierre</x-boton>
                    <x-boton variante="fantasma" wire:click="$set('panel', '')">Volver</x-boton>
                </div>
            </div>
        @endif

        @if ($panel === 'visita')
            <div class="mt-4 space-y-3 border-t border-linea pt-4">
                <div>
                    <label for="tecnico-campo" class="etiqueta">Técnico de campo</label>
                    <select id="tecnico-campo" wire:model="tecnico_campo_id" class="control h-9 py-0">
                        <option value="">— Selecciona —</option>
                        @foreach ($this->tecnicosCampo as $t)
                            <option value="{{ $t->id }}">{{ $t->user->name ?? $t->nombre }}</option>
                        @endforeach
                    </select>
                    @error('tecnico_campo_id') <p class="error-campo">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="fecha-programada" class="etiqueta">Fecha</label>
                        <input id="fecha-programada" type="date" wire:model="fecha_programada" class="control h-9">
                        @error('fecha_programada') <p class="error-campo">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="franja" class="etiqueta">Franja</label>
                        <select id="franja" wire:model="franja" class="control h-9 py-0">
                            @foreach (\App\Models\OrdenTrabajo::FRANJAS as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="text-xs text-tinta-3">
                    Al programar la visita el reloj del nivel 2 se detiene y el caso no se puede cerrar
                    hasta que el técnico la ejecute.
                </p>
                <div class="flex gap-2">
                    <x-boton variante="primario" wire:click="programarVisita">Programar</x-boton>
                    <x-boton variante="fantasma" wire:click="$set('panel', '')">Volver</x-boton>
                </div>
            </div>
        @endif

        @if ($panel === 'escalar')
            <div class="mt-4 space-y-3 border-t border-linea pt-4">
                <div>
                    <label for="ingeniero" class="etiqueta">Ingeniero de redes</label>
                    <select id="ingeniero" wire:model="ingeniero_redes_id" class="control h-9 py-0">
                        <option value="">— Selecciona —</option>
                        @foreach ($this->ingenierosRedes as $ing)
                            <option value="{{ $ing->id }}">{{ $ing->name }}</option>
                        @endforeach
                    </select>
                    @error('ingeniero_redes_id') <p class="error-campo">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="motivo-escalamiento" class="etiqueta">¿Por qué supera al nivel 2?</label>
                    <textarea id="motivo-escalamiento" wire:model="motivo_escalamiento" rows="2" class="control"></textarea>
                    @error('motivo_escalamiento') <p class="error-campo">{{ $message }}</p> @enderror
                </div>
                <div class="flex gap-2">
                    <x-boton variante="primario" wire:click="escalar">Escalar</x-boton>
                    <x-boton variante="fantasma" wire:click="$set('panel', '')">Volver</x-boton>
                </div>
            </div>
        @endif

        @if ($panel === 'redes')
            <div class="mt-4 space-y-3 border-t border-linea pt-4">
                <label for="respuesta-n3" class="etiqueta">Respuesta de ingeniería de redes</label>
                <textarea id="respuesta-n3" wire:model="respuesta_n3" rows="3" class="control"
                    placeholder="Qué se encontró y qué debe hacer el nivel 2..."></textarea>
                @error('respuesta_n3') <p class="error-campo">{{ $message }}</p> @enderror
                <div class="flex gap-2">
                    <x-boton variante="primario" wire:click="devolverDeRedes">Devolver al nivel 2</x-boton>
                    <x-boton variante="fantasma" wire:click="$set('panel', '')">Volver</x-boton>
                </div>
            </div>
        @endif

        @if ($panel === 'criticidad')
            <div class="mt-4 space-y-3 border-t border-linea pt-4">
                <label for="motivo-criticidad" class="etiqueta">¿Por qué debe atenderse de inmediato?</label>
                <textarea id="motivo-criticidad" wire:model="motivo_criticidad" rows="2" class="control"></textarea>
                @error('motivo_criticidad') <p class="error-campo">{{ $message }}</p> @enderror
                <div class="flex gap-2">
                    <x-boton variante="primario" wire:click="elevarCriticidad">Elevar</x-boton>
                    <x-boton variante="fantasma" wire:click="$set('panel', '')">Volver</x-boton>
                </div>
            </div>
        @endif

        @if ($panel === 'cancelar')
            <div class="mt-4 space-y-3 border-t border-linea pt-4">
                <label for="motivo-cancelacion" class="etiqueta">Motivo de cancelación</label>
                <textarea id="motivo-cancelacion" wire:model="motivo_cancelacion" rows="2" class="control"></textarea>
                @error('motivo_cancelacion') <p class="error-campo">{{ $message }}</p> @enderror
                <div class="flex gap-2">
                    <x-boton variante="peligro" wire:click="cancelar">Confirmar cancelación</x-boton>
                    <x-boton variante="fantasma" wire:click="$set('panel', '')">Volver</x-boton>
                </div>
            </div>
        @endif
    </section>
@endunless
