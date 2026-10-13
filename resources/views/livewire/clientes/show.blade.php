<?php

use App\Models\Cliente;
use App\Models\EquipoInstalado;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public Cliente $cliente;

    public string $panel = '';

    // Contrato. Los ids los pone el servidor (editarContrato, nuevoEquipo):
    // #[Locked] impide que el navegador los cambie por los de otro abonado.
    #[Locked]
    public ?int $contrato_id = null;
    public string $numero_contrato = '';
    public string $fecha_inicio = '';
    public string $fecha_fin = '';
    public string $valor_contrato = '';
    public string $contrato_estado = 'activo';

    // Equipo
    #[Locked]
    public ?int $equipo_contrato_id = null;
    public string $equipo_tipo = 'router';
    public string $equipo_modelo = '';
    public string $equipo_serie = '';
    public string $equipo_fecha = '';

    public function mount(Cliente $cliente): void
    {
        $this->authorize('view', $cliente);

        $this->cliente = $cliente;
        $this->recargar();
    }

    private function recargar(): void
    {
        $this->cliente->refresh()->load([
            'contratos.equipos',
            'soportes' => fn($q) => $q->with(['tecnicoSoporte:id,name', 'diagnostico:id,nombre'])
                ->latest('id')->limit(20),
        ]);
    }

    /** Cifras de reincidencia del abonado. */
    #[Computed]
    public function recurrencia(): array
    {
        $casos = \App\Models\Soporte::where('cliente_id', $this->cliente->id);

        $total = (clone $casos)->count();

        if ($total === 0) {
            return ['total' => 0];
        }

        $sinContacto = (clone $casos)->whereIn('estado', [
            \App\Models\Soporte::ESTADO_SIN_CONTACTO,
            \App\Models\Soporte::ESTADO_CERRADO_SIN_CONTACTO,
        ])->count();

        $diagnosticos = \App\Models\Soporte::where('cliente_id', $this->cliente->id)
            ->join('diagnosticos', 'diagnosticos.id', '=', 'soportes.diagnostico_id')
            ->select('diagnosticos.nombre', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('diagnosticos.nombre')
            ->orderByDesc('total')
            ->limit(3)
            ->pluck('total', 'diagnosticos.nombre');

        return [
            'total' => $total,
            'ultimos12' => $this->cliente->reportesRecientes(),
            'sin_contacto' => $sinContacto,
            'es_recurrente' => $this->cliente->esRecurrente(),
            'motivo' => $this->cliente->motivoRecurrencia(),
            'diagnosticos' => $diagnosticos,
            'primero' => (clone $casos)->min('created_at'),
        ];
    }

    #[Computed]
    public function puedeEditar(): bool
    {
        return auth()->user()->can('update', $this->cliente);
    }

    /* ---------------------------------------------------------------
     | Contratos
     * --------------------------------------------------------------- */

    public function nuevoContrato(): void
    {
        $this->reset(['contrato_id', 'numero_contrato', 'fecha_fin', 'valor_contrato']);
        $this->fecha_inicio = today()->toDateString();
        $this->contrato_estado = 'activo';
        $this->panel = 'contrato';
    }

    public function editarContrato(int $id): void
    {
        $contrato = $this->cliente->contratos->firstWhere('id', $id);

        if (!$contrato) {
            return;
        }

        $this->contrato_id = $contrato->id;
        $this->numero_contrato = $contrato->numero_contrato;
        $this->fecha_inicio = $contrato->fecha_inicio?->toDateString() ?? '';
        $this->fecha_fin = $contrato->fecha_fin?->toDateString() ?? '';
        $this->valor_contrato = (string) $contrato->valor_contrato;
        $this->contrato_estado = $contrato->estado;
        $this->panel = 'contrato';
    }

    public function guardarContrato(): void
    {
        $this->authorize('update', $this->cliente);

        $datos = $this->validate([
            'numero_contrato' => [
                'required', 'string', 'max:60',
                Rule::unique('contratos', 'numero_contrato')->ignore($this->contrato_id),
            ],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'valor_contrato' => ['nullable', 'numeric', 'min:0'],
            'contrato_estado' => [Rule::in(['activo', 'suspendido', 'retirado'])],
        ], [], [
            'numero_contrato' => 'número de contrato',
            'contrato_estado' => 'estado',
        ]);

        $atributos = [
            'numero_contrato' => $datos['numero_contrato'],
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_fin' => $datos['fecha_fin'] ?: null,
            'valor_contrato' => $datos['valor_contrato'] !== '' ? $datos['valor_contrato'] : null,
            'estado' => $datos['contrato_estado'],
        ];

        // Siempre a través del cliente: un contrato ajeno da 404, no se pisa
        // ni se muda a este abonado.
        $this->contrato_id
            ? $this->cliente->contratos()->findOrFail($this->contrato_id)->update($atributos)
            : $this->cliente->contratos()->create($atributos);

        $this->panel = '';
        $this->recargar();

        session()->flash('mensaje', 'Contrato guardado.');
    }

    /* ---------------------------------------------------------------
     | Equipos instalados
     * --------------------------------------------------------------- */

    public function nuevoEquipo(int $contratoId): void
    {
        $this->reset(['equipo_modelo', 'equipo_serie']);
        $this->equipo_contrato_id = $this->cliente->contratos()->findOrFail($contratoId)->id;
        $this->equipo_tipo = 'router';
        $this->equipo_fecha = today()->toDateString();
        $this->panel = 'equipo';
    }

    public function guardarEquipo(): void
    {
        $this->authorize('update', $this->cliente);

        $this->validate([
            'equipo_contrato_id' => [
                'required',
                Rule::exists('contratos', 'id')->where('cliente_id', $this->cliente->id),
            ],
            'equipo_tipo' => ['required', 'string', 'max:40'],
            'equipo_modelo' => ['nullable', 'string', 'max:80'],
            'equipo_serie' => ['nullable', 'string', 'max:80'],
            'equipo_fecha' => ['nullable', 'date'],
        ], [], [
            'equipo_tipo' => 'tipo de equipo',
            'equipo_serie' => 'número de serie',
        ]);

        EquipoInstalado::create([
            'contrato_id' => $this->equipo_contrato_id,
            'tipo' => $this->equipo_tipo,
            'modelo' => $this->equipo_modelo ?: null,
            'numero_serie' => $this->equipo_serie ?: null,
            'fecha_instalacion' => $this->equipo_fecha ?: null,
            'estado' => 'activo',
        ]);

        $this->panel = '';
        $this->recargar();

        session()->flash('mensaje', 'Equipo registrado.');
    }

    public function retirarEquipo(int $id): void
    {
        $this->authorize('update', $this->cliente);

        EquipoInstalado::whereKey($id)
            ->whereHas('contrato', fn ($contrato) => $contrato->where('cliente_id', $this->cliente->id))
            ->firstOrFail()
            ->update(['estado' => 'retirado']);

        $this->recargar();
        session()->flash('mensaje', 'Equipo marcado como retirado.');
    }
}; ?>

<div class="mx-auto flex max-w-[1100px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
    <x-mensajes />

    <x-encabezado :titulo="$cliente->nombre">
        <x-slot:antes>
            <a href="{{ route('clientes.index') }}" wire:navigate class="inline-flex items-center gap-1 text-tinta-3 no-underline hover:text-acento-texto">
                <svg width="13" height="13" viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                Clientes
            </a>
        </x-slot:antes>
        <x-slot:acciones>
            <x-estado :estado="$cliente->estado" :etiqueta="ucfirst($cliente->estado)" />
            @if ($this->puedeEditar)
                <x-boton :href="route('clientes.edit', $cliente)" wire:navigate>Editar</x-boton>
                <x-boton variante="primario" :href="route('soportes.create', ['cliente' => $cliente->id])" wire:navigate>
                    <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
                    Nuevo caso
                </x-boton>
            @endif
        </x-slot:acciones>
    </x-encabezado>
    <p class="-mt-2 font-mono text-[13px] text-tinta-3">
        {{ $cliente->codigo_abonado ?? 'Sin código de abonado' }} · CC/NIT {{ $cliente->cedula }}
    </p>

    {{-- Reincidencia --}}
    @if (($this->recurrencia['total'] ?? 0) > 0 && $this->recurrencia['es_recurrente'])
        <section class="tarjeta border-l-[3px] border-l-recurrente p-4">
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <h2 class="flex items-center gap-2 font-semibold text-recurrente">
                    <span class="rounded-[3px] bg-recurrente-fondo px-1.5 text-[11px] leading-[18px]">↻ {{ $this->recurrencia['total'] }}</span>
                    Abonado recurrente
                </h2>
                <span class="inline-flex h-[22px] items-center rounded-full border border-recurrente/40 px-2 text-xs font-medium text-recurrente">
                    {{ $this->recurrencia['motivo'] === 'sin_contacto' ? 'No se logra contactar' : 'Falla recurrente' }}
                </span>
            </div>

            <p class="text-sm">
                <span class="font-mono">{{ $this->recurrencia['total'] }}</span> reportes en total,
                <span class="font-mono">{{ $this->recurrencia['ultimos12'] }}</span> en los últimos 12 meses.
                @if ($this->recurrencia['sin_contacto'] > 0)
                    <span class="font-mono">{{ $this->recurrencia['sin_contacto'] }}</span> terminaron sin poder contactarlo.
                @endif
            </p>

            @if ($this->recurrencia['diagnosticos']->count())
                <p class="mt-2 text-sm">
                    <span class="font-medium">Se le ha diagnosticado:</span>
                    @foreach ($this->recurrencia['diagnosticos'] as $nombre => $veces)
                        {{ $nombre }} (<span class="font-mono">{{ $veces }}</span>){{ !$loop->last ? ',' : '' }}
                    @endforeach
                </p>
            @endif

            <p class="mt-2 text-[13px] text-tinta-3">
                {{ $this->recurrencia['motivo'] === 'sin_contacto'
                    ? 'Insistir por el mismo canal no está funcionando: conviene agendar visita o buscar otro contacto.'
                    : 'El patrón sugiere una causa de fondo en la instalación, no una falla nueva cada vez.' }}
            </p>
        </section>
    @endif

    {{-- Datos --}}
    <section class="tarjeta p-4">
        <h2 class="titulo-seccion mb-3">Datos de contacto</h2>
        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-tinta-3">Teléfono</dt>
                <dd class="font-mono text-[13px]">{{ $cliente->telefono ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-tinta-3">Correo</dt>
                <dd class="break-all">{{ $cliente->correo ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium text-tinta-3">Dirección</dt>
                <dd>{{ $cliente->direccion ?? '—' }}</dd>
            </div>
            @if ($cliente->latitud && $cliente->longitud)
                <div>
                    <dt class="text-xs font-medium text-tinta-3">Ubicación</dt>
                    <dd class="font-mono text-[13px]">{{ $cliente->latitud }}, {{ $cliente->longitud }}</dd>
                </div>
            @endif
        </dl>
    </section>

    {{-- Contratos --}}
    <section class="tarjeta p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2 class="titulo-seccion">Contratos</h2>
            @if ($this->puedeEditar)
                <x-boton variante="fantasma" tamano="chico" wire:click="nuevoContrato">+ Agregar contrato</x-boton>
            @endif
        </div>

        <div class="flex flex-col gap-3">
            @forelse ($cliente->contratos as $contrato)
                <div class="rounded-tarjeta border border-linea p-3" wire:key="contrato-{{ $contrato->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="text-sm">
                            <p class="font-mono font-medium">{{ $contrato->numero_contrato }}</p>
                            <p class="text-[13px] text-tinta-3">
                                Desde <span class="font-mono">{{ $contrato->fecha_inicio?->format('d/m/Y') }}</span>
                                @if ($contrato->fecha_fin) hasta <span class="font-mono">{{ $contrato->fecha_fin->format('d/m/Y') }}</span> @endif
                                @if ($contrato->valor_contrato)
                                    · <span class="font-mono">${{ number_format($contrato->valor_contrato, 0, ',', '.') }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-estado :estado="$contrato->estado" :etiqueta="ucfirst($contrato->estado)" />

                            @if ($this->puedeEditar)
                                <x-boton tamano="chico" wire:click="editarContrato({{ $contrato->id }})">Editar</x-boton>
                                <x-boton tamano="chico" wire:click="nuevoEquipo({{ $contrato->id }})">+ Equipo</x-boton>
                            @endif
                        </div>
                    </div>

                    @if ($contrato->equipos->count())
                        <ul class="mt-3 divide-y divide-linea-suave border-t border-linea-suave text-[13px]">
                            @foreach ($contrato->equipos as $equipo)
                                <li class="flex items-center justify-between gap-3 py-1.5">
                                    <span @class(['text-tinta-3 line-through' => $equipo->estado === 'retirado', 'text-tinta-2' => $equipo->estado !== 'retirado'])>
                                        <span class="font-medium text-tinta">{{ ucfirst($equipo->tipo) }}</span>
                                        @if ($equipo->modelo) · {{ $equipo->modelo }} @endif
                                        @if ($equipo->numero_serie) · <span class="font-mono">s/n {{ $equipo->numero_serie }}</span> @endif
                                        @if ($equipo->fecha_instalacion)
                                            <span class="font-mono text-tinta-3">({{ $equipo->fecha_instalacion->format('d/m/Y') }})</span>
                                        @endif
                                    </span>
                                    @if ($this->puedeEditar && $equipo->estado !== 'retirado')
                                        <x-boton variante="fantasma" tamano="chico" class="!text-peligro" wire:click="retirarEquipo({{ $equipo->id }})"
                                            wire:confirm="¿Marcar este equipo como retirado?">Retirar</x-boton>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @empty
                <p class="text-sm text-tinta-3">Este cliente todavía no tiene contratos registrados.</p>
            @endforelse
        </div>

        {{-- Panel de contrato --}}
        @if ($panel === 'contrato')
            <div class="mt-4 space-y-3 border-t border-linea pt-4">
                <h3 class="font-semibold">{{ $contrato_id ? 'Editar contrato' : 'Nuevo contrato' }}</h3>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label for="numero-contrato" class="etiqueta">Número de contrato</label>
                        <input id="numero-contrato" type="text" wire:model="numero_contrato" class="control h-9">
                        @error('numero_contrato') <p class="error-campo">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="valor-contrato" class="etiqueta">Valor mensual</label>
                        <input id="valor-contrato" type="number" step="1" wire:model="valor_contrato" class="control h-9 font-mono">
                        @error('valor_contrato') <p class="error-campo">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="fecha-inicio" class="etiqueta">Fecha de inicio</label>
                        <input id="fecha-inicio" type="date" wire:model="fecha_inicio" class="control h-9">
                        @error('fecha_inicio') <p class="error-campo">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="fecha-fin" class="etiqueta">Fecha de fin (opcional)</label>
                        <input id="fecha-fin" type="date" wire:model="fecha_fin" class="control h-9">
                        @error('fecha_fin') <p class="error-campo">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="contrato-estado" class="etiqueta">Estado</label>
                        <select id="contrato-estado" wire:model="contrato_estado" class="control h-9 py-0">
                            <option value="activo">Activo</option>
                            <option value="suspendido">Suspendido</option>
                            <option value="retirado">Retirado</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-2">
                    <x-boton variante="primario" wire:click="guardarContrato">Guardar</x-boton>
                    <x-boton variante="fantasma" wire:click="$set('panel', '')">Cancelar</x-boton>
                </div>
            </div>
        @endif

        {{-- Panel de equipo --}}
        @if ($panel === 'equipo')
            <div class="mt-4 space-y-3 border-t border-linea pt-4">
                <h3 class="font-semibold">Nuevo equipo instalado</h3>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="equipo-tipo" class="etiqueta">Tipo</label>
                        <select id="equipo-tipo" wire:model="equipo_tipo" class="control h-9 py-0">
                            <option value="router">Router / ONU</option>
                            <option value="decodificador">Decodificador</option>
                            <option value="antena">Antena</option>
                            <option value="cableado">Cableado</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div>
                        <label for="equipo-modelo" class="etiqueta">Modelo</label>
                        <input id="equipo-modelo" type="text" wire:model="equipo_modelo" class="control h-9">
                    </div>
                    <div>
                        <label for="equipo-serie" class="etiqueta">Número de serie</label>
                        <input id="equipo-serie" type="text" wire:model="equipo_serie" class="control h-9 font-mono">
                    </div>
                    <div>
                        <label for="equipo-fecha" class="etiqueta">Instalación</label>
                        <input id="equipo-fecha" type="date" wire:model="equipo_fecha" class="control h-9">
                    </div>
                </div>

                <div class="flex gap-2">
                    <x-boton variante="primario" wire:click="guardarEquipo">Guardar</x-boton>
                    <x-boton variante="fantasma" wire:click="$set('panel', '')">Cancelar</x-boton>
                </div>
            </div>
        @endif
    </section>

    {{-- Historial de casos --}}
    <section>
        <div class="mb-2 flex items-center justify-between gap-3">
            <h2 class="titulo-seccion">Últimos casos</h2>
            @if (($this->recurrencia['total'] ?? 0) > 20)
                <a href="{{ route('soportes.index', ['q' => $cliente->codigo_abonado ?? $cliente->cedula]) }}"
                    wire:navigate class="text-[13px] font-medium">Ver los {{ $this->recurrencia['total'] }}</a>
            @endif
        </div>

        @if ($cliente->soportes->count())
            <x-tabla minimo="760px">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Diagnóstico</th>
                        <th>Responsable</th>
                        <th class="text-right">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cliente->soportes as $soporte)
                        <tr class="cursor-pointer" onclick="window.location='{{ route('soportes.show', $soporte) }}'">
                            <td>
                                <a href="{{ route('soportes.show', $soporte) }}" class="whitespace-nowrap font-mono font-medium text-tinta no-underline hover:text-acento-texto hover:underline">{{ $soporte->numero_soporte ?? $soporte->id }}</a>
                            </td>
                            <td class="text-tinta-2">{{ $soporte->etiquetaTipo() }}</td>
                            <td><x-estado :estado="$soporte->estado" :etiqueta="$soporte->etiquetaEstado()" /></td>
                            <td class="text-tinta-2">{{ $soporte->diagnostico->nombre ?? '—' }}</td>
                            <td class="text-tinta-2">{{ $soporte->tecnicoSoporte->name ?? '—' }}</td>
                            <td class="text-right font-mono text-xs text-tinta-3">{{ $soporte->created_at->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tabla>
        @else
            <p class="tarjeta p-4 text-sm text-tinta-3">Sin casos registrados.</p>
        @endif
    </section>
</div>
