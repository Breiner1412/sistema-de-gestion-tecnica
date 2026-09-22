<?php

use App\Models\Cliente;
use App\Models\Plan;
use App\Models\Soporte;
use App\Models\SoporteCambioPlan;
use App\Models\SoporteCambioTitular;
use App\Models\TipoFalla;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    // Cliente
    public string $busquedaCliente = '';
    public ?int $cliente_id = null;
    public $contrato_id = '';

    // Comunes
    public string $tipo_solicitud = Soporte::TIPO_SOPORTE_REMOTO;
    public string $canal_ingreso = Soporte::CANAL_CALL_CENTER;
    public string $descripcion = '';

    // Soporte técnico
    public string $servicio_afectado = 'internet';
    public $tipo_falla_id = '';

    // Cambio de plan
    public $plan_anterior_id = '';
    public $plan_nuevo_id = '';
    public string $tiempo_ejecucion = 'inmediatamente';

    // Cambio de titular
    public string $titular_nuevo_cedula = '';
    public string $titular_nuevo_nombre = '';
    public string $titular_nuevo_telefono = '';
    public bool $incluye_cambio_plan = false;
    public bool $incluye_traslado = false;

    /** Permite llegar desde la ficha del cliente con el abonado ya elegido. */
    public function mount(): void
    {
        if ($id = request()->integer('cliente')) {
            if (Cliente::whereKey($id)->exists()) {
                $this->cliente_id = $id;
            }
        }
    }

    /** Búsqueda acotada: antes se cargaba el maestro completo de clientes. */
    #[Computed]
    public function resultadosCliente()
    {
        if (strlen($this->busquedaCliente) < 3 || $this->cliente_id) {
            return collect();
        }

        return Cliente::buscar($this->busquedaCliente)
            ->where('estado', 'activo')
            ->orderBy('nombre')
            ->limit(15)
            ->get(['id', 'nombre', 'cedula', 'codigo_abonado']);
    }

    #[Computed]
    public function cliente(): ?Cliente
    {
        return $this->cliente_id ? Cliente::find($this->cliente_id) : null;
    }

    #[Computed]
    public function contratos()
    {
        return $this->cliente?->contratos()->orderByDesc('fecha_inicio')->get() ?? collect();
    }

    #[Computed]
    public function fallas()
    {
        return TipoFalla::activos()->paraServicio($this->servicio_afectado)->get();
    }

    #[Computed]
    public function planes()
    {
        return Plan::activos()->get();
    }

    public function seleccionarCliente(int $id): void
    {
        $this->cliente_id = $id;
        $this->busquedaCliente = '';
        $this->contrato_id = '';
        unset($this->cliente, $this->contratos);
    }

    public function quitarCliente(): void
    {
        $this->reset(['cliente_id', 'contrato_id', 'busquedaCliente']);
        unset($this->cliente, $this->contratos);
    }

    public function updatedServicioAfectado(): void
    {
        $this->tipo_falla_id = '';
        unset($this->fallas);
    }

    protected function reglas(): array
    {
        $base = [
            'cliente_id' => ['required', 'exists:clientes,id'],
            'contrato_id' => ['nullable', 'exists:contratos,id'],
            'tipo_solicitud' => [Rule::in(array_keys(Soporte::TIPOS_SOLICITUD))],
            'canal_ingreso' => [Rule::in(array_keys(Soporte::CANALES))],
            'descripcion' => ['required', 'min:10'],
        ];

        return match ($this->tipo_solicitud) {
            Soporte::TIPO_CAMBIO_PLAN => $base + [
                'plan_nuevo_id' => ['required', 'exists:planes,id'],
                'plan_anterior_id' => ['nullable', 'exists:planes,id'],
                'tiempo_ejecucion' => [Rule::in(array_keys(SoporteCambioPlan::TIEMPOS_EJECUCION))],
            ],
            Soporte::TIPO_CAMBIO_TITULAR => $base + [
                'titular_nuevo_cedula' => ['required', 'string', 'max:30'],
                'titular_nuevo_nombre' => ['required', 'string', 'max:255'],
                'titular_nuevo_telefono' => ['nullable', 'string', 'max:30'],
            ],
            default => $base + [
                'servicio_afectado' => [Rule::in(array_keys(Soporte::SERVICIOS))],
                'tipo_falla_id' => ['nullable', 'exists:tipos_falla,id'],
            ],
        };
    }

    public function guardar()
    {
        $this->validate($this->reglas(), [], [
            'cliente_id' => 'cliente',
            'plan_nuevo_id' => 'plan nuevo',
            'tipo_falla_id' => 'falla reportada',
            'titular_nuevo_cedula' => 'cédula del nuevo titular',
            'titular_nuevo_nombre' => 'nombre del nuevo titular',
        ]);

        $esCambio = in_array($this->tipo_solicitud, [Soporte::TIPO_CAMBIO_PLAN, Soporte::TIPO_CAMBIO_TITULAR], true);

        $soporte = DB::transaction(function () use ($esCambio) {
            $soporte = Soporte::create([
                'cliente_id' => $this->cliente_id,
                'contrato_id' => $this->contrato_id ?: null,
                'tipo_solicitud' => $this->tipo_solicitud,
                'canal_ingreso' => $this->canal_ingreso,
                'servicio_afectado' => $esCambio ? null : $this->servicio_afectado,
                'tipo_falla_id' => $esCambio ? null : ($this->tipo_falla_id ?: null),
                'usuario_registra_id' => auth()->id(),
                'descripcion' => $this->descripcion,
                'estado' => Soporte::ESTADO_PENDIENTE,
                // La criticidad la calcula el modelo a partir de la falla.
            ]);

            if ($this->tipo_solicitud === Soporte::TIPO_CAMBIO_PLAN) {
                $soporte->cambioPlan()->create([
                    'plan_anterior_id' => $this->plan_anterior_id ?: null,
                    'plan_nuevo_id' => $this->plan_nuevo_id,
                    'tiempo_ejecucion' => $this->tiempo_ejecucion,
                    'motivo' => $this->descripcion,
                ]);
            }

            if ($this->tipo_solicitud === Soporte::TIPO_CAMBIO_TITULAR) {
                $soporte->cambioTitular()->create([
                    'titular_anterior_id' => $this->cliente_id,
                    'titular_nuevo_cedula' => $this->titular_nuevo_cedula,
                    'titular_nuevo_nombre' => $this->titular_nuevo_nombre,
                    'titular_nuevo_telefono' => $this->titular_nuevo_telefono ?: null,
                    'incluye_cambio_plan' => $this->incluye_cambio_plan,
                    'incluye_traslado' => $this->incluye_traslado,
                ]);
            }

            $soporte->historialEstados()->create([
                'estado_anterior' => null,
                'estado_nuevo' => Soporte::ESTADO_PENDIENTE,
                'usuario_id' => auth()->id(),
            ]);

            return $soporte;
        });

        session()->flash('mensaje', "Soporte {$soporte->numero_soporte} registrado correctamente.");

        return $this->redirect(route('soportes.show', $soporte), navigate: true);
    }
}; ?>

<div class="p-6 max-w-3xl">
    <h1 class="text-2xl font-bold mb-4 text-slate-800">Nuevo soporte</h1>

    <form wire:submit="guardar" class="space-y-5">

        {{-- Cliente --}}
        <div class="bg-white border rounded p-4">
            <label class="block font-medium mb-1 text-slate-800">Cliente</label>

            @if ($this->cliente)
                <div class="flex items-center justify-between border rounded p-2 bg-slate-50">
                    <div class="text-sm">
                        <p class="font-medium">{{ $this->cliente->nombre }}</p>
                        <p class="text-slate-500 text-xs">
                            {{ $this->cliente->codigo_abonado ?? '—' }} · CC {{ $this->cliente->cedula }}
                        </p>
                    </div>
                    <button type="button" wire:click="quitarCliente" class="text-sm text-red-600">Cambiar</button>
                </div>
            @else
                <input type="search" wire:model.live.debounce.400ms="busquedaCliente"
                    class="w-full border rounded p-2" placeholder="Nombre, cédula o código de abonado (mín. 3 caracteres)">

                @if ($this->resultadosCliente->isNotEmpty())
                    <ul class="border rounded mt-2 divide-y max-h-60 overflow-y-auto">
                        @foreach ($this->resultadosCliente as $c)
                            <li>
                                <button type="button" wire:click="seleccionarCliente({{ $c->id }})"
                                    class="w-full text-left p-2 hover:bg-slate-50 text-sm">
                                    {{ $c->nombre }}
                                    <span class="text-slate-500">— {{ $c->codigo_abonado ?? $c->cedula }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @elseif (strlen($busquedaCliente) >= 3)
                    <p class="text-sm text-slate-500 mt-2">Sin resultados.</p>
                @endif
            @endif

            @error('cliente_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror

            @if ($this->cliente && $this->contratos->count())
                <div class="mt-3">
                    <label class="block font-medium mb-1 text-slate-800">Contrato (opcional)</label>
                    <select wire:model="contrato_id" class="w-full border rounded p-2">
                        <option value="">— Sin contrato específico —</option>
                        @foreach ($this->contratos as $contrato)
                            <option value="{{ $contrato->id }}">
                                {{ $contrato->numero_contrato }} ({{ $contrato->estado }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        {{-- Tipo de solicitud y canal --}}
        <div class="bg-white border rounded p-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="block font-medium mb-1 text-slate-800">Tipo de solicitud</label>
                <select wire:model.live="tipo_solicitud" class="w-full border rounded p-2">
                    @foreach (\App\Models\Soporte::TIPOS_SOLICITUD as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-medium mb-1 text-slate-800">¿Por dónde entró?</label>
                <select wire:model="canal_ingreso" class="w-full border rounded p-2">
                    @foreach (\App\Models\Soporte::CANALES as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Campos según tipo --}}
        @if (in_array($tipo_solicitud, ['soporte_remoto', 'sin_internet']))
            <div class="bg-white border rounded p-4 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block font-medium mb-1 text-slate-800">Servicio afectado</label>
                    <select wire:model.live="servicio_afectado" class="w-full border rounded p-2">
                        @foreach (\App\Models\Soporte::SERVICIOS as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-medium mb-1 text-slate-800">Falla reportada</label>
                    <select wire:model="tipo_falla_id" class="w-full border rounded p-2">
                        <option value="">— Sin clasificar —</option>
                        @foreach ($this->fallas as $falla)
                            <option value="{{ $falla->id }}">{{ $falla->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        @if ($tipo_solicitud === 'cambio_plan')
            <div class="bg-white border rounded p-4 grid gap-4 md:grid-cols-3">
                <div>
                    <label class="block font-medium mb-1 text-slate-800">Plan anterior</label>
                    <select wire:model="plan_anterior_id" class="w-full border rounded p-2">
                        <option value="">— Sin registrar —</option>
                        @foreach ($this->planes as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-medium mb-1 text-slate-800">Plan nuevo</label>
                    <select wire:model="plan_nuevo_id" class="w-full border rounded p-2">
                        <option value="">— Selecciona —</option>
                        @foreach ($this->planes as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->nombre }}</option>
                        @endforeach
                    </select>
                    @error('plan_nuevo_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block font-medium mb-1 text-slate-800">Tiempo de ejecución</label>
                    <select wire:model="tiempo_ejecucion" class="w-full border rounded p-2">
                        @foreach (\App\Models\SoporteCambioPlan::TIEMPOS_EJECUCION as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        @if ($tipo_solicitud === 'cambio_titular')
            <div class="bg-white border rounded p-4 space-y-4">
                <p class="text-sm text-slate-500">El titular actual es el cliente seleccionado arriba.</p>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="block font-medium mb-1 text-slate-800">Cédula nuevo titular</label>
                        <input type="text" wire:model="titular_nuevo_cedula" class="w-full border rounded p-2">
                        @error('titular_nuevo_cedula') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-medium mb-1 text-slate-800">Nombre nuevo titular</label>
                        <input type="text" wire:model="titular_nuevo_nombre" class="w-full border rounded p-2">
                        @error('titular_nuevo_nombre') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block font-medium mb-1 text-slate-800">Teléfono</label>
                        <input type="text" wire:model="titular_nuevo_telefono" class="w-full border rounded p-2">
                    </div>
                </div>
                <div class="flex gap-6 text-sm">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="incluye_cambio_plan" class="rounded border-slate-300">
                        Incluye cambio de plan
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="incluye_traslado" class="rounded border-slate-300">
                        Incluye traslado
                    </label>
                </div>
            </div>
        @endif

        {{-- Descripción --}}
        <div class="bg-white border rounded p-4 space-y-4">
            <div>
                <label class="block font-medium mb-1 text-slate-800">
                    {{ in_array($tipo_solicitud, ['cambio_plan', 'cambio_titular']) ? 'Motivo / observaciones' : 'Descripción del problema' }}
                </label>
                <textarea wire:model="descripcion" rows="4" class="w-full border rounded p-2"
                    placeholder="Describe lo que reporta el cliente..."></textarea>
                @error('descripcion') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <p class="text-xs text-slate-500">
                La criticidad se calcula sola: si el servicio está caído (sin internet o sin TV)
                el caso entra como <strong>inmediato</strong>; el resto va a cinco días hábiles.
            </p>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="guardar">Guardar soporte</span>
                <span wire:loading wire:target="guardar">Guardando...</span>
            </button>
            <a href="{{ route('soportes.index') }}" wire:navigate class="px-4 py-2 rounded border">Cancelar</a>
        </div>
    </form>
</div>
