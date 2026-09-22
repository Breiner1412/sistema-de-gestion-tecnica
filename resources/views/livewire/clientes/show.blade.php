<?php

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\EquipoInstalado;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public Cliente $cliente;

    public string $panel = '';

    // Contrato
    public ?int $contrato_id = null;
    public string $numero_contrato = '';
    public string $fecha_inicio = '';
    public string $fecha_fin = '';
    public string $valor_contrato = '';
    public string $contrato_estado = 'activo';

    // Equipo
    public ?int $equipo_contrato_id = null;
    public string $equipo_tipo = 'router';
    public string $equipo_modelo = '';
    public string $equipo_serie = '';
    public string $equipo_fecha = '';

    public function mount(Cliente $cliente): void
    {
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
        return auth()->user()->tieneRol(User::ROL_ADMIN, User::ROL_GERENTE, User::ROL_CALL_CENTER);
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
        abort_unless($this->puedeEditar, 403);

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
            'cliente_id' => $this->cliente->id,
            'numero_contrato' => $datos['numero_contrato'],
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_fin' => $datos['fecha_fin'] ?: null,
            'valor_contrato' => $datos['valor_contrato'] !== '' ? $datos['valor_contrato'] : null,
            'estado' => $datos['contrato_estado'],
        ];

        $this->contrato_id
            ? Contrato::whereKey($this->contrato_id)->update($atributos)
            : Contrato::create($atributos);

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
        $this->equipo_contrato_id = $contratoId;
        $this->equipo_tipo = 'router';
        $this->equipo_fecha = today()->toDateString();
        $this->panel = 'equipo';
    }

    public function guardarEquipo(): void
    {
        abort_unless($this->puedeEditar, 403);

        $this->validate([
            'equipo_contrato_id' => ['required', 'exists:contratos,id'],
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
        abort_unless($this->puedeEditar, 403);

        EquipoInstalado::whereKey($id)->update(['estado' => 'retirado']);

        $this->recargar();
        session()->flash('mensaje', 'Equipo marcado como retirado.');
    }
}; ?>

<div class="p-6 max-w-4xl">

    @if (session('mensaje'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('mensaje') }}</div>
    @endif

    <a href="{{ route('clientes.index') }}" wire:navigate class="text-blue-600 text-sm">&larr; Volver a clientes</a>

    <div class="flex flex-wrap justify-between items-start gap-3 mt-2 mb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ $cliente->nombre }}</h1>
            <p class="text-sm text-slate-500">
                {{ $cliente->codigo_abonado ?? 'Sin código de abonado' }} · CC/NIT {{ $cliente->cedula }}
            </p>
        </div>
        <div class="flex gap-2">
            @if ($this->puedeEditar)
                <a href="{{ route('clientes.edit', $cliente) }}" wire:navigate class="px-4 py-2 rounded border">
                    Editar
                </a>
                <a href="{{ route('soportes.create', ['cliente' => $cliente->id]) }}" wire:navigate class="bg-blue-600 text-white px-4 py-2 rounded">
                    Nuevo caso
                </a>
            @endif
        </div>
    </div>

    {{-- Reincidencia --}}
    @if (($this->recurrencia['total'] ?? 0) > 0 && $this->recurrencia['es_recurrente'])
        <div class="bg-amber-50 border border-amber-300 rounded p-4 mb-4">
            <div class="flex flex-wrap items-center gap-2 mb-2">
                <h2 class="font-semibold text-amber-900">Abonado recurrente</h2>
                <span class="px-2 py-0.5 rounded bg-amber-200 text-amber-900 text-xs">
                    {{ $this->recurrencia['motivo'] === 'sin_contacto' ? 'No se logra contactar' : 'Falla recurrente' }}
                </span>
            </div>

            <p class="text-sm text-amber-900">
                {{ $this->recurrencia['total'] }} reportes en total,
                {{ $this->recurrencia['ultimos12'] }} en los últimos 12 meses.
                @if ($this->recurrencia['sin_contacto'] > 0)
                    {{ $this->recurrencia['sin_contacto'] }} terminaron sin poder contactarlo.
                @endif
            </p>

            @if ($this->recurrencia['diagnosticos']->count())
                <p class="text-sm text-amber-900 mt-2">
                    <span class="font-medium">Se le ha diagnosticado:</span>
                    @foreach ($this->recurrencia['diagnosticos'] as $nombre => $veces)
                        {{ $nombre }} ({{ $veces }}){{ !$loop->last ? ',' : '' }}
                    @endforeach
                </p>
            @endif

            <p class="text-xs text-amber-800 mt-2">
                {{ $this->recurrencia['motivo'] === 'sin_contacto'
                    ? 'Insistir por el mismo canal no está funcionando: conviene agendar visita o buscar otro contacto.'
                    : 'El patrón sugiere una causa de fondo en la instalación, no una falla nueva cada vez.' }}
            </p>
        </div>
    @endif

    {{-- Datos --}}
    <div class="bg-slate-50 border rounded p-4 mb-4 grid gap-x-6 gap-y-1 text-sm text-slate-800 md:grid-cols-2">
        <p><span class="font-semibold">Teléfono:</span> {{ $cliente->telefono ?? '—' }}</p>
        <p><span class="font-semibold">Correo:</span> {{ $cliente->correo ?? '—' }}</p>
        <p class="md:col-span-2"><span class="font-semibold">Dirección:</span> {{ $cliente->direccion ?? '—' }}</p>
        <p><span class="font-semibold">Estado:</span> {{ ucfirst($cliente->estado) }}</p>
        @if ($cliente->latitud && $cliente->longitud)
            <p><span class="font-semibold">Ubicación:</span> {{ $cliente->latitud }}, {{ $cliente->longitud }}</p>
        @endif
    </div>

    {{-- Contratos --}}
    <div class="bg-white border rounded p-4 mb-4">
        <div class="flex justify-between items-center mb-3">
            <h2 class="font-semibold text-slate-800">Contratos</h2>
            @if ($this->puedeEditar)
                <button wire:click="nuevoContrato" class="text-sm text-blue-600">+ Agregar contrato</button>
            @endif
        </div>

        @forelse ($cliente->contratos as $contrato)
            <div class="border rounded p-3 mb-3 last:mb-0">
                <div class="flex flex-wrap justify-between items-start gap-2">
                    <div class="text-sm">
                        <p class="font-medium text-slate-800">{{ $contrato->numero_contrato }}</p>
                        <p class="text-slate-600">
                            Desde {{ $contrato->fecha_inicio?->format('d/m/Y') }}
                            @if ($contrato->fecha_fin) hasta {{ $contrato->fecha_fin->format('d/m/Y') }} @endif
                            @if ($contrato->valor_contrato)
                                · ${{ number_format($contrato->valor_contrato, 0, ',', '.') }}
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span @class([
                            'px-2 py-1 rounded text-xs',
                            'bg-green-200 text-green-900' => $contrato->estado === 'activo',
                            'bg-yellow-200 text-yellow-900' => $contrato->estado === 'suspendido',
                            'bg-slate-200 text-slate-700' => $contrato->estado === 'retirado',
                        ])>{{ ucfirst($contrato->estado) }}</span>

                        @if ($this->puedeEditar)
                            <button wire:click="editarContrato({{ $contrato->id }})" class="text-xs text-blue-600">Editar</button>
                            <button wire:click="nuevoEquipo({{ $contrato->id }})" class="text-xs text-blue-600">+ Equipo</button>
                        @endif
                    </div>
                </div>

                @if ($contrato->equipos->count())
                    <ul class="mt-2 pt-2 border-t text-xs divide-y">
                        @foreach ($contrato->equipos as $equipo)
                            <li class="py-1.5 flex justify-between items-center">
                                <span @class(['text-slate-400 line-through' => $equipo->estado === 'retirado'])>
                                    {{ ucfirst($equipo->tipo) }}
                                    @if ($equipo->modelo) · {{ $equipo->modelo }} @endif
                                    @if ($equipo->numero_serie) · s/n {{ $equipo->numero_serie }} @endif
                                    @if ($equipo->fecha_instalacion)
                                        <span class="text-slate-500">({{ $equipo->fecha_instalacion->format('d/m/Y') }})</span>
                                    @endif
                                </span>
                                @if ($this->puedeEditar && $equipo->estado !== 'retirado')
                                    <button wire:click="retirarEquipo({{ $equipo->id }})"
                                        wire:confirm="¿Marcar este equipo como retirado?"
                                        class="text-red-600">Retirar</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @empty
            <p class="text-sm text-slate-500">Este cliente todavía no tiene contratos registrados.</p>
        @endforelse

        {{-- Panel de contrato --}}
        @if ($panel === 'contrato')
            <div class="border-t pt-3 mt-3 space-y-3">
                <h3 class="font-medium text-slate-800">{{ $contrato_id ? 'Editar contrato' : 'Nuevo contrato' }}</h3>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Número de contrato</label>
                        <input type="text" wire:model="numero_contrato" class="w-full border rounded p-2 text-sm">
                        @error('numero_contrato') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Valor mensual</label>
                        <input type="number" step="1" wire:model="valor_contrato" class="w-full border rounded p-2 text-sm">
                        @error('valor_contrato') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Fecha de inicio</label>
                        <input type="date" wire:model="fecha_inicio" class="w-full border rounded p-2 text-sm">
                        @error('fecha_inicio') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Fecha de fin (opcional)</label>
                        <input type="date" wire:model="fecha_fin" class="w-full border rounded p-2 text-sm">
                        @error('fecha_fin') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Estado</label>
                        <select wire:model="contrato_estado" class="w-full border rounded p-2 text-sm">
                            <option value="activo">Activo</option>
                            <option value="suspendido">Suspendido</option>
                            <option value="retirado">Retirado</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button wire:click="guardarContrato" class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Guardar</button>
                    <button wire:click="$set('panel', '')" class="px-4 py-2 rounded border text-sm">Cancelar</button>
                </div>
            </div>
        @endif

        {{-- Panel de equipo --}}
        @if ($panel === 'equipo')
            <div class="border-t pt-3 mt-3 space-y-3">
                <h3 class="font-medium text-slate-800">Nuevo equipo instalado</h3>

                <div class="grid gap-3 md:grid-cols-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Tipo</label>
                        <select wire:model="equipo_tipo" class="w-full border rounded p-2 text-sm">
                            <option value="router">Router / ONU</option>
                            <option value="decodificador">Decodificador</option>
                            <option value="antena">Antena</option>
                            <option value="cableado">Cableado</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Modelo</label>
                        <input type="text" wire:model="equipo_modelo" class="w-full border rounded p-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Número de serie</label>
                        <input type="text" wire:model="equipo_serie" class="w-full border rounded p-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Instalación</label>
                        <input type="date" wire:model="equipo_fecha" class="w-full border rounded p-2 text-sm">
                    </div>
                </div>

                <div class="flex gap-2">
                    <button wire:click="guardarEquipo" class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Guardar</button>
                    <button wire:click="$set('panel', '')" class="px-4 py-2 rounded border text-sm">Cancelar</button>
                </div>
            </div>
        @endif
    </div>

    {{-- Historial de casos --}}
    <div class="bg-white border rounded p-4">
        <div class="flex justify-between items-center mb-3">
            <h2 class="font-semibold text-slate-800">Últimos casos</h2>
            @if (($this->recurrencia['total'] ?? 0) > 20)
                <a href="{{ route('soportes.index', ['q' => $cliente->codigo_abonado ?? $cliente->cedula]) }}"
                    wire:navigate class="text-sm text-blue-600">Ver los {{ $this->recurrencia['total'] }}</a>
            @endif
        </div>

        @if ($cliente->soportes->count())
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b">
                        <th class="py-1 font-medium">N°</th>
                        <th class="py-1 font-medium">Tipo</th>
                        <th class="py-1 font-medium">Estado</th>
                        <th class="py-1 font-medium">Diagnóstico</th>
                        <th class="py-1 font-medium">Responsable</th>
                        <th class="py-1 font-medium">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cliente->soportes as $soporte)
                        <tr class="border-b last:border-0 hover:bg-slate-50 cursor-pointer"
                            onclick="window.location='{{ route('soportes.show', $soporte) }}'">
                            <td class="py-1.5 font-mono text-xs">{{ $soporte->numero_soporte ?? $soporte->id }}</td>
                            <td class="py-1.5">{{ $soporte->etiquetaTipo() }}</td>
                            <td class="py-1.5">{{ $soporte->etiquetaEstado() }}</td>
                            <td class="py-1.5 text-slate-600 text-xs">{{ $soporte->diagnostico->nombre ?? '—' }}</td>
                            <td class="py-1.5 text-slate-600">{{ $soporte->tecnicoSoporte->name ?? '—' }}</td>
                            <td class="py-1.5 text-slate-500 text-xs">{{ $soporte->created_at->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-sm text-slate-500">Sin casos registrados.</p>
        @endif
    </div>
</div>
