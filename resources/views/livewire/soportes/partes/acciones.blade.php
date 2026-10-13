{{-- Responsable de nivel 2 y acciones del caso. Cada botón se muestra según la
     política (SoportePolicy); la acción vuelve a preguntarla en el servidor. --}}

{{-- Responsable --}}
@unless ($soporte->estaCerrado())
    <div class="bg-white border rounded p-4 mb-4">
        <label class="block font-medium mb-1 text-slate-800">Técnico de soporte (nivel 2)</label>

        @if ($this->puedeReasignar)
            <div class="flex gap-2">
                <select wire:model="tecnico_soporte_id" class="w-full border rounded p-2">
                    <option value="">— Sin asignar —</option>
                    @foreach ($this->tecnicosSoporte as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
                <button wire:click="asignar" class="bg-blue-600 text-white px-4 py-2 rounded whitespace-nowrap">
                    Asignar
                </button>
            </div>
            @error('tecnico_soporte_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        @else
            <p class="text-slate-700">{{ $soporte->tecnicoSoporte->name ?? 'Sin asignar' }}</p>
            @if ($this->ordenAbierta)
                <p class="text-xs text-slate-500 mt-1">Hay una visita en curso. Solo un administrador puede reasignar.</p>
            @endif
        @endif
    </div>
@endunless

{{-- Acciones --}}
@unless ($soporte->estaCerrado())
    <div class="bg-white border rounded p-4 mb-4 space-y-3">
        <h2 class="font-semibold text-slate-800">Acciones</h2>

        <div class="flex flex-wrap gap-2">
            @if ($soporte->estado === 'escalado_n3')
                @if ($this->esResponsableN3)
                    <button wire:click="$set('panel', 'redes')" class="bg-slate-800 text-white px-4 py-2 rounded">
                        Responder y devolver al nivel 2
                    </button>
                @else
                    <p class="text-sm text-slate-500">
                        El caso está en manos de {{ $soporte->escaladoA->name ?? 'ingeniería de redes' }}.
                    </p>
                @endif
            @endif

            @if ($this->puedeTrabajar && $soporte->puedeTransicionarA('solucionado') && !$this->ordenAbierta)
                <button wire:click="$set('panel', 'cerrar')" class="bg-green-600 text-white px-4 py-2 rounded">
                    Resolver
                </button>
            @endif

            @if ($this->ordenAbierta)
                <a href="{{ route('ordenes.show', $this->ordenAbierta) }}" wire:navigate
                    class="bg-slate-700 text-white px-4 py-2 rounded">
                    Ver visita #{{ $this->ordenAbierta->id }}
                    @if ($this->ordenAbierta->fecha_programada)
                        ({{ $this->ordenAbierta->fecha_programada->format('d/m') }})
                    @endif
                </a>
            @elseif ($this->puedeTrabajar && $soporte->puedeTransicionarA('enviado_tecnico'))
                <button wire:click="$set('panel', 'visita')" class="bg-indigo-600 text-white px-4 py-2 rounded">
                    Programar visita
                </button>
            @endif

            @if ($this->puedeTrabajar && $soporte->puedeTransicionarA('escalado_n3'))
                <button wire:click="$set('panel', 'escalar')" class="bg-slate-800 text-white px-4 py-2 rounded">
                    Escalar a redes
                </button>
            @endif

            @if ($this->puedeTrabajar && $soporte->puedeTransicionarA('sin_contacto'))
                <button wire:click="sinContacto"
                    wire:confirm="¿Registrar un intento de contacto sin respuesta?"
                    class="bg-orange-500 text-white px-4 py-2 rounded">
                    No contesta
                </button>
            @endif

            @if ($this->puedeTrabajar && $soporte->puedeTransicionarA('seguimiento'))
                <button wire:click="seguimiento" class="bg-cyan-600 text-white px-4 py-2 rounded">
                    Seguimiento
                </button>
            @endif

            @if ($this->puedeTrabajar && $soporte->estado === 'sin_contacto')
                <button wire:click="retomar" class="bg-blue-600 text-white px-4 py-2 rounded">
                    Retomar
                </button>
            @endif

            @if ($this->puedeElevar && !$soporte->esInmediato())
                <button wire:click="$set('panel', 'criticidad')" class="border border-red-300 text-red-700 px-4 py-2 rounded">
                    Elevar a inmediato
                </button>
            @endif

            @if ($this->puedeCancelar && $soporte->puedeTransicionarA('cancelado'))
                <button wire:click="$set('panel', 'cancelar')" class="bg-red-600 text-white px-4 py-2 rounded">
                    Cancelar
                </button>
            @endif
        </div>

        @if ($panel === 'cerrar')
            <div class="border-t pt-3 mt-2 space-y-3">
                <div>
                    <label class="block font-medium mb-1 text-slate-800">Diagnóstico</label>
                    <select wire:model="diagnostico_id" class="w-full border rounded p-2">
                        <option value="">— Selecciona —</option>
                        @foreach ($this->diagnosticos as $d)
                            <option value="{{ $d->id }}">{{ $d->nombre }}</option>
                        @endforeach
                    </select>
                    @error('diagnostico_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-medium mb-1 text-slate-800">¿Qué se hizo?</label>
                    <textarea wire:model="observaciones_cierre" rows="3" class="w-full border rounded p-2"></textarea>
                    @error('observaciones_cierre') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div class="flex gap-2">
                    <button wire:click="resolver" class="bg-green-600 text-white px-4 py-2 rounded">Confirmar cierre</button>
                    <button wire:click="$set('panel', '')" class="px-4 py-2 rounded border">Volver</button>
                </div>
            </div>
        @endif

        @if ($panel === 'visita')
            <div class="border-t pt-3 mt-2 space-y-3">
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <label class="block font-medium mb-1 text-slate-800">Técnico de campo</label>
                        <select wire:model="tecnico_campo_id" class="w-full border rounded p-2">
                            <option value="">— Selecciona —</option>
                            @foreach ($this->tecnicosCampo as $t)
                                <option value="{{ $t->id }}">{{ $t->user->name ?? $t->nombre }}</option>
                            @endforeach
                        </select>
                        @error('tecnico_campo_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-medium mb-1 text-slate-800">Fecha</label>
                        <input type="date" wire:model="fecha_programada" class="w-full border rounded p-2">
                        @error('fecha_programada') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-medium mb-1 text-slate-800">Franja</label>
                        <select wire:model="franja" class="w-full border rounded p-2">
                            @foreach (\App\Models\OrdenTrabajo::FRANJAS as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="text-xs text-slate-500">
                    Al programar la visita el reloj del nivel 2 se detiene y el caso no se puede cerrar
                    hasta que el técnico la ejecute.
                </p>
                <div class="flex gap-2">
                    <button wire:click="programarVisita" class="bg-indigo-600 text-white px-4 py-2 rounded">Programar</button>
                    <button wire:click="$set('panel', '')" class="px-4 py-2 rounded border">Volver</button>
                </div>
            </div>
        @endif

        @if ($panel === 'escalar')
            <div class="border-t pt-3 mt-2 space-y-3">
                <div>
                    <label class="block font-medium mb-1 text-slate-800">Ingeniero de redes</label>
                    <select wire:model="ingeniero_redes_id" class="w-full border rounded p-2">
                        <option value="">— Selecciona —</option>
                        @foreach ($this->ingenierosRedes as $ing)
                            <option value="{{ $ing->id }}">{{ $ing->name }}</option>
                        @endforeach
                    </select>
                    @error('ingeniero_redes_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-medium mb-1 text-slate-800">¿Por qué supera al nivel 2?</label>
                    <textarea wire:model="motivo_escalamiento" rows="2" class="w-full border rounded p-2"></textarea>
                    @error('motivo_escalamiento') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div class="flex gap-2">
                    <button wire:click="escalar" class="bg-slate-800 text-white px-4 py-2 rounded">Escalar</button>
                    <button wire:click="$set('panel', '')" class="px-4 py-2 rounded border">Volver</button>
                </div>
            </div>
        @endif

        @if ($panel === 'redes')
            <div class="border-t pt-3 mt-2 space-y-3">
                <label class="block font-medium mb-1 text-slate-800">Respuesta de ingeniería de redes</label>
                <textarea wire:model="respuesta_n3" rows="3" class="w-full border rounded p-2"
                    placeholder="Qué se encontró y qué debe hacer el nivel 2..."></textarea>
                @error('respuesta_n3') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                <div class="flex gap-2">
                    <button wire:click="devolverDeRedes" class="bg-slate-800 text-white px-4 py-2 rounded">
                        Devolver al nivel 2
                    </button>
                    <button wire:click="$set('panel', '')" class="px-4 py-2 rounded border">Volver</button>
                </div>
            </div>
        @endif

        @if ($panel === 'criticidad')
            <div class="border-t pt-3 mt-2 space-y-3">
                <label class="block font-medium mb-1 text-slate-800">¿Por qué debe atenderse de inmediato?</label>
                <textarea wire:model="motivo_criticidad" rows="2" class="w-full border rounded p-2"></textarea>
                @error('motivo_criticidad') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                <div class="flex gap-2">
                    <button wire:click="elevarCriticidad" class="bg-red-600 text-white px-4 py-2 rounded">Elevar</button>
                    <button wire:click="$set('panel', '')" class="px-4 py-2 rounded border">Volver</button>
                </div>
            </div>
        @endif

        @if ($panel === 'cancelar')
            <div class="border-t pt-3 mt-2 space-y-3">
                <label class="block font-medium mb-1 text-slate-800">Motivo de cancelación</label>
                <textarea wire:model="motivo_cancelacion" rows="2" class="w-full border rounded p-2"></textarea>
                @error('motivo_cancelacion') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                <div class="flex gap-2">
                    <button wire:click="cancelar" class="bg-red-600 text-white px-4 py-2 rounded">Confirmar</button>
                    <button wire:click="$set('panel', '')" class="px-4 py-2 rounded border">Volver</button>
                </div>
            </div>
        @endif
    </div>
@endunless
