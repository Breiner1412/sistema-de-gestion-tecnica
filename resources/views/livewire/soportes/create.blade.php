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
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    // Cliente
    public string $busquedaCliente = '';
    // Lo pone seleccionarCliente(); el navegador no lo cambia directamente.
    #[Locked]
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
        $this->authorize('create', Soporte::class);

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
            // El contrato tiene que ser del cliente elegido, no de cualquiera.
            'contrato_id' => ['nullable', Rule::exists('contratos', 'id')->where('cliente_id', $this->cliente_id)],
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
        $this->authorize('create', Soporte::class);

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

<div class="mx-auto flex max-w-[760px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
    <x-encabezado titulo="Nuevo caso" subtitulo="Solo lo que el primer nivel no pudo resolver en el momento.">
        <x-slot:antes>
            <a href="{{ route('soportes.index') }}" wire:navigate class="inline-flex items-center gap-1 text-tinta-3 no-underline hover:text-acento-texto">
                <svg width="13" height="13" viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                Bandeja de casos
            </a>
        </x-slot:antes>
    </x-encabezado>

    <form wire:submit="guardar" class="flex flex-col gap-4">

        {{-- Cliente --}}
        <section class="tarjeta p-4 sm:p-5">
            <h2 class="titulo-seccion mb-3"><span class="font-mono">1</span> · Cliente</h2>

            @if ($this->cliente)
                <div class="flex items-center justify-between gap-3 rounded-control border border-acento/40 bg-acento-tinte px-3 py-2">
                    <div class="min-w-0 text-sm">
                        <p class="truncate font-semibold text-acento-tinte-texto">{{ $this->cliente->nombre }}</p>
                        <p class="font-mono text-xs text-tinta-2">
                            {{ $this->cliente->codigo_abonado ?? '—' }} · CC {{ $this->cliente->cedula }}
                        </p>
                    </div>
                    <x-boton tamano="chico" wire:click="quitarCliente">Cambiar</x-boton>
                </div>
            @else
                <label for="busqueda-cliente" class="sr-only">Buscar cliente</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-tinta-3" width="15" height="15" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor" stroke-width="1.5"></circle><path d="m10.5 10.5 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>
                    <input id="busqueda-cliente" type="search" wire:model.live.debounce.400ms="busquedaCliente"
                        class="control h-10 pl-9" placeholder="Nombre, cédula o código de abonado (mín. 3 caracteres)">
                </div>

                @if ($this->resultadosCliente->isNotEmpty())
                    <ul class="mt-2 max-h-60 divide-y divide-linea-suave overflow-y-auto rounded-control border border-linea">
                        @foreach ($this->resultadosCliente as $c)
                            <li>
                                <button type="button" wire:click="seleccionarCliente({{ $c->id }})"
                                    class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-elevada">
                                    <span class="font-medium">{{ $c->nombre }}</span>
                                    <span class="font-mono text-xs text-tinta-3">{{ $c->codigo_abonado ?? $c->cedula }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @elseif (strlen($busquedaCliente) >= 3)
                    <p class="mt-2 text-sm text-tinta-3">Sin resultados.</p>
                @endif
            @endif

            @error('cliente_id') <p class="error-campo">{{ $message }}</p> @enderror

            @if ($this->cliente && $this->contratos->count())
                <div class="mt-3">
                    <label for="contrato" class="etiqueta">Contrato (opcional)</label>
                    <select id="contrato" wire:model="contrato_id" class="control h-10 py-0">
                        <option value="">— Sin contrato específico —</option>
                        @foreach ($this->contratos as $contrato)
                            <option value="{{ $contrato->id }}">
                                {{ $contrato->numero_contrato }} ({{ $contrato->estado }})
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
        </section>

        {{-- Tipo de solicitud y canal --}}
        <section class="tarjeta grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
            <h2 class="titulo-seccion sm:col-span-2"><span class="font-mono">2</span> · Solicitud</h2>
            <div>
                <label for="tipo-solicitud" class="etiqueta">Tipo de solicitud</label>
                <select id="tipo-solicitud" wire:model.live="tipo_solicitud" class="control h-10 py-0">
                    @foreach (\App\Models\Soporte::TIPOS_SOLICITUD as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="canal" class="etiqueta">¿Por dónde entró?</label>
                <select id="canal" wire:model="canal_ingreso" class="control h-10 py-0">
                    @foreach (\App\Models\Soporte::CANALES as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Campos según tipo --}}
            @if (in_array($tipo_solicitud, ['soporte_remoto', 'sin_internet']))
                <div>
                    <label for="servicio" class="etiqueta">Servicio afectado</label>
                    <select id="servicio" wire:model.live="servicio_afectado" class="control h-10 py-0">
                        @foreach (\App\Models\Soporte::SERVICIOS as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="falla" class="etiqueta">Falla reportada</label>
                    <select id="falla" wire:model="tipo_falla_id" class="control h-10 py-0">
                        <option value="">— Sin clasificar —</option>
                        @foreach ($this->fallas as $falla)
                            <option value="{{ $falla->id }}">{{ $falla->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($tipo_solicitud === 'cambio_plan')
                <div class="grid gap-4 border-t border-linea-suave pt-4 sm:col-span-2 sm:grid-cols-3">
                    <div>
                        <label for="plan-anterior" class="etiqueta">Plan anterior</label>
                        <select id="plan-anterior" wire:model="plan_anterior_id" class="control h-10 py-0">
                            <option value="">— Sin registrar —</option>
                            @foreach ($this->planes as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="plan-nuevo" class="etiqueta">Plan nuevo</label>
                        <select id="plan-nuevo" wire:model="plan_nuevo_id" class="control h-10 py-0">
                            <option value="">— Selecciona —</option>
                            @foreach ($this->planes as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->nombre }}</option>
                            @endforeach
                        </select>
                        @error('plan_nuevo_id') <p class="error-campo">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="tiempo-ejecucion" class="etiqueta">Tiempo de ejecución</label>
                        <select id="tiempo-ejecucion" wire:model="tiempo_ejecucion" class="control h-10 py-0">
                            @foreach (\App\Models\SoporteCambioPlan::TIEMPOS_EJECUCION as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif

            @if ($tipo_solicitud === 'cambio_titular')
                <div class="space-y-4 border-t border-linea-suave pt-4 sm:col-span-2">
                    <p class="text-[13px] text-tinta-3">El titular actual es el cliente seleccionado arriba.</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label for="titular-cedula" class="etiqueta">Cédula nuevo titular</label>
                            <input id="titular-cedula" type="text" wire:model="titular_nuevo_cedula" class="control h-10 font-mono">
                            @error('titular_nuevo_cedula') <p class="error-campo">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="titular-nombre" class="etiqueta">Nombre nuevo titular</label>
                            <input id="titular-nombre" type="text" wire:model="titular_nuevo_nombre" class="control h-10">
                            @error('titular_nuevo_nombre') <p class="error-campo">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="titular-telefono" class="etiqueta">Teléfono</label>
                            <input id="titular-telefono" type="text" wire:model="titular_nuevo_telefono" class="control h-10 font-mono" inputmode="tel">
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="incluye_cambio_plan" class="size-4 rounded border-linea bg-superficie text-acento focus:ring-acento-texto">
                            Incluye cambio de plan
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="incluye_traslado" class="size-4 rounded border-linea bg-superficie text-acento focus:ring-acento-texto">
                            Incluye traslado
                        </label>
                    </div>
                </div>
            @endif
        </section>

        {{-- Descripción --}}
        <section class="tarjeta space-y-3 p-4 sm:p-5">
            <h2 class="titulo-seccion"><span class="font-mono">3</span> · Detalle</h2>
            <div>
                <label for="descripcion" class="etiqueta">
                    {{ in_array($tipo_solicitud, ['cambio_plan', 'cambio_titular']) ? 'Motivo / observaciones' : 'Descripción del problema' }}
                </label>
                <textarea id="descripcion" wire:model="descripcion" rows="4" class="control"
                    placeholder="Describe lo que reporta el cliente..."></textarea>
                @error('descripcion') <p class="error-campo">{{ $message }}</p> @enderror
            </div>

            <p class="flex items-start gap-2 text-xs text-tinta-3">
                <x-inmediata class="mt-px shrink-0" />
                <span>La criticidad se calcula sola: si el servicio está caído (sin internet o sin TV)
                el caso entra como <strong class="text-tinta">inmediato</strong>; el resto va a cinco días hábiles.</span>
            </p>
        </section>

        <div class="flex gap-2">
            <x-boton variante="primario" type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="guardar">Guardar caso</span>
                <span wire:loading wire:target="guardar">Guardando…</span>
            </x-boton>
            <x-boton variante="fantasma" :href="route('soportes.index')" wire:navigate>Cancelar</x-boton>
        </div>
    </form>
</div>
