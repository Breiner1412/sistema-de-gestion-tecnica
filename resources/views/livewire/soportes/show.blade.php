<?php

use App\Exceptions\TransicionInvalidaException;
use App\Models\Diagnostico;
use App\Models\OrdenTrabajo;
use App\Models\Soporte;
use App\Models\Tecnico;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public Soporte $soporte;

    public $tecnico_soporte_id = '';
    public $diagnostico_id = '';
    public string $observaciones_cierre = '';
    public string $motivo_cancelacion = '';

    // Escalamiento a redes
    public $ingeniero_redes_id = '';
    public string $motivo_escalamiento = '';
    public string $respuesta_n3 = '';

    // Visita
    public $tecnico_campo_id = '';
    public string $fecha_programada = '';
    public string $franja = 'manana';

    public string $motivo_criticidad = '';

    public string $panel = '';

    public function mount(Soporte $soporte): void
    {
        $this->soporte = $soporte;
        $this->tecnico_soporte_id = $soporte->tecnico_soporte_id ?? '';
        $this->diagnostico_id = $soporte->diagnostico_id ?? '';
        $this->fecha_programada = now()->addDay()->toDateString();
        $this->recargar();
    }

    private function recargar(): void
    {
        $this->soporte->refresh()->load([
            'cliente', 'contrato', 'tipoFalla', 'diagnostico',
            'usuarioRegistra', 'tecnicoSoporte', 'escaladoA',
            'historialEstados.usuario',
            'ordenesTrabajo.tecnico.user',
            'cambioPlan.planAnterior', 'cambioPlan.planNuevo',
            'cambioTitular',
        ]);
    }

    /* ---------------------------------------------------------------
     | Datos para la vista
     * --------------------------------------------------------------- */

    #[Computed]
    public function tecnicosSoporte()
    {
        return User::tecnicosSoporte()->get(['id', 'name']);
    }

    #[Computed]
    public function ingenierosRedes()
    {
        return User::ingenierosRedes()->get(['id', 'name']);
    }

    #[Computed]
    public function tecnicosCampo()
    {
        return Tecnico::with('user:id,name')->where('estado', 'activo')->orderBy('nombre')->get();
    }

    #[Computed]
    public function diagnosticos()
    {
        return Diagnostico::activos()->get();
    }

    #[Computed]
    public function ordenAbierta(): ?OrdenTrabajo
    {
        return $this->soporte->ordenesTrabajo->first(fn($o) => $o->estaAbierta());
    }

    #[Computed]
    public function puedeReasignar(): bool
    {
        return auth()->user()->esAdmin() || !$this->ordenAbierta;
    }

    /** Cuántas veces había reportado el abonado antes de este caso. */
    #[Computed]
    public function reportesPrevios(): int
    {
        return $this->soporte->reportesPreviosDelAbonado();
    }

    #[Computed]
    public function totalDelAbonado(): int
    {
        return Soporte::where('cliente_id', $this->soporte->cliente_id)->count();
    }

    #[Computed]
    public function esResponsableN3(): bool
    {
        return auth()->id() === $this->soporte->escalado_a_id || auth()->user()->esAdmin();
    }

    private function ok(string $mensaje): void
    {
        $this->panel = '';
        $this->recargar();
        session()->flash('mensaje', $mensaje);
    }

    /* ---------------------------------------------------------------
     | Acciones
     * --------------------------------------------------------------- */

    public function asignar(): void
    {
        if (!$this->puedeReasignar) {
            session()->flash('error', 'El caso ya tiene una visita en curso. Solo un administrador puede reasignarlo.');
            return;
        }

        $this->validate(['tecnico_soporte_id' => 'required|exists:users,id']);

        $this->soporte->asignarTecnicoSoporte((int) $this->tecnico_soporte_id);
        $this->ok('Responsable asignado.');
    }

    public function resolver(): void
    {
        if ($this->ordenAbierta) {
            session()->flash('error', 'Hay una visita pendiente. El caso no se puede cerrar hasta que el técnico la ejecute.');
            return;
        }

        $this->validate([
            'diagnostico_id' => 'required|exists:diagnosticos,id',
            'observaciones_cierre' => 'required|min:10',
        ], [
            'diagnostico_id.required' => 'Selecciona el diagnóstico para poder cerrar el caso.',
            'observaciones_cierre.required' => 'Describe qué se hizo para resolverlo.',
        ]);

        $this->intentar(fn() => $this->soporte->cerrar(
            (int) $this->diagnostico_id,
            $this->observaciones_cierre,
        ), 'Caso cerrado como solucionado.');
    }

    public function sinContacto(): void
    {
        $this->intentar(fn() => $this->soporte->marcarSinContacto(), 'Intento de contacto registrado.');
    }

    public function seguimiento(): void
    {
        $this->intentar(fn() => $this->soporte->cambiarEstado(Soporte::ESTADO_SEGUIMIENTO), 'Caso puesto en seguimiento.');
    }

    public function retomar(): void
    {
        $this->intentar(fn() => $this->soporte->cambiarEstado(Soporte::ESTADO_EN_PROCESO), 'Caso retomado por el nivel 2.');
    }

    public function escalar(): void
    {
        $this->validate([
            'ingeniero_redes_id' => 'required|exists:users,id',
            'motivo_escalamiento' => 'required|min:10',
        ], [
            'ingeniero_redes_id.required' => 'Indica a qué ingeniero de redes se le entrega el caso.',
            'motivo_escalamiento.required' => 'Explica por qué el caso supera al nivel 2.',
        ]);

        $this->intentar(fn() => $this->soporte->escalarANivel3(
            (int) $this->ingeniero_redes_id,
            $this->motivo_escalamiento,
        ), 'Caso escalado a ingeniería de redes.');
    }

    public function devolverDeRedes(): void
    {
        $this->validate(['respuesta_n3' => 'required|min:10']);

        $this->intentar(fn() => $this->soporte->devolverDeNivel3($this->respuesta_n3), 'Caso devuelto al nivel 2.');
    }

    public function programarVisita(): void
    {
        if ($orden = $this->ordenAbierta) {
            $this->redirect(route('ordenes.show', $orden), navigate: true);
            return;
        }

        $this->validate([
            'tecnico_campo_id' => 'required|exists:tecnicos,id',
            'fecha_programada' => 'required|date|after_or_equal:today',
            'franja' => 'required|in:manana,tarde',
        ], [
            'tecnico_campo_id.required' => 'Selecciona el técnico que va a hacer la visita.',
        ]);

        $orden = OrdenTrabajo::create([
            'soporte_id' => $this->soporte->id,
            'tecnico_id' => $this->tecnico_campo_id,
            'tipo' => 'revision',
            'estado' => OrdenTrabajo::ESTADO_PROGRAMADA,
            'fecha_programada' => $this->fecha_programada,
            'franja' => $this->franja,
        ]);

        $this->intentar(fn() => $this->soporte->cambiarEstado(
            Soporte::ESTADO_ENVIADO_TECNICO,
            'Visita programada para el '.$orden->fecha_programada->format('d/m/Y').'.',
        ), 'Visita programada.');
    }

    public function cancelar(): void
    {
        $this->validate(['motivo_cancelacion' => 'required|min:5'], [
            'motivo_cancelacion.required' => 'Debes escribir un motivo para cancelar el caso.',
        ]);

        $this->intentar(
            fn() => $this->soporte->cambiarEstado(Soporte::ESTADO_CANCELADO, $this->motivo_cancelacion),
            'Caso cancelado.'
        );
    }

    public function elevarCriticidad(): void
    {
        $this->validate(['motivo_criticidad' => 'required|min:10']);

        $this->soporte->elevarCriticidad($this->motivo_criticidad);
        $this->ok('Criticidad elevada a inmediata.');
    }

    /** Envuelve las transiciones para que un salto inválido no reviente la pantalla. */
    private function intentar(callable $accion, string $mensaje): void
    {
        try {
            $accion();
            $this->ok($mensaje);
        } catch (TransicionInvalidaException $e) {
            $this->recargar();
            session()->flash('error', $e->getMessage());
        }
    }
}; ?>

<div class="p-6 max-w-3xl">

    @if (session('mensaje'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('mensaje') }}</div>
    @endif
    @if (session('error'))
        <div class="bg-red-100 text-red-800 p-3 rounded mb-4">{{ session('error') }}</div>
    @endif

    <a href="{{ route('soportes.index') }}" wire:navigate class="text-blue-600 text-sm">&larr; Volver a la bandeja</a>

    <div class="flex flex-wrap items-center gap-3 mt-2 mb-4">
        <h1 class="text-2xl font-bold text-slate-800">{{ $soporte->numero_soporte ?? 'Caso #'.$soporte->id }}</h1>

        <span @class([
            'px-3 py-1 rounded text-sm font-semibold',
            'bg-yellow-200 text-yellow-900' => $soporte->estado === 'pendiente',
            'bg-blue-200 text-blue-900' => $soporte->estado === 'en_proceso',
            'bg-cyan-200 text-cyan-900' => $soporte->estado === 'seguimiento',
            'bg-purple-200 text-purple-900' => $soporte->estado === 'enviado_tecnico',
            'bg-slate-800 text-white' => $soporte->estado === 'escalado_n3',
            'bg-orange-200 text-orange-900' => $soporte->estado === 'sin_contacto',
            'bg-green-200 text-green-900' => $soporte->estado === 'solucionado',
            'bg-red-200 text-red-900' => $soporte->estado === 'cancelado',
            'bg-slate-200 text-slate-700' => $soporte->estado === 'cerrado_sin_contacto',
        ])>{{ $soporte->etiquetaEstado() }}</span>

        @if ($soporte->esInmediato())
            <span class="px-2 py-1 rounded bg-red-600 text-white text-xs font-semibold">Inmediato</span>
        @endif

        @if ($soporte->escalado_nivel_3)
            <span class="px-2 py-1 rounded bg-slate-800 text-white text-xs">Pasó por nivel 3</span>
        @endif
    </div>

    {{-- Reloj --}}
    @unless ($soporte->estaCerrado())
        @php $semaforo = $soporte->semaforoSla(); @endphp
        <div @class([
            'border rounded p-3 mb-4 flex items-center justify-between text-sm',
            'bg-green-50 border-green-200' => $semaforo === 'ok',
            'bg-yellow-50 border-yellow-200' => $semaforo === 'atencion',
            'bg-orange-50 border-orange-200' => $semaforo === 'riesgo',
            'bg-red-50 border-red-200' => $semaforo === 'vencido',
            'bg-slate-50 border-slate-200' => $semaforo === 'pausado',
        ])>
            <div>
                <span class="font-semibold text-slate-800">
                    @if ($semaforo === 'pausado')
                        Reloj en pausa
                    @elseif ($semaforo === 'vencido')
                        SLA incumplido
                    @else
                        Quedan {{ $soporte->restanteLegible() }}
                    @endif
                </span>
                <span class="text-slate-600">
                    @if ($semaforo === 'pausado')
                        — el caso espera a un tercero, el tiempo no corre contra el nivel 2.
                    @else
                        — vence el {{ $soporte->sla_vence_at?->format('d/m/Y H:i') ?? '—' }}
                    @endif
                </span>
            </div>
            <span class="text-xs text-slate-500">{{ $soporte->porcentajeSla() }}% consumido</span>
        </div>
    @endunless

    {{-- Reincidencia --}}
    @if ($this->reportesPrevios > 0)
        @php $dias = config('sla.recurrencia.ventana_dias', 60); @endphp
        <div class="bg-amber-50 border border-amber-300 rounded p-3 mb-4 text-sm">
            <p class="text-amber-900">
                <span class="font-semibold">Abonado reincidente.</span>
                Ya había reportado <strong>{{ $this->reportesPrevios }}</strong>
                {{ $this->reportesPrevios === 1 ? 'vez' : 'veces' }} en los {{ $dias }} días previos a este caso,
                y lleva {{ $this->totalDelAbonado }} en total.
            </p>
            <p class="text-amber-800 text-xs mt-1">
                Antes de repetir el mismo procedimiento, vale la pena mirar qué se le diagnosticó las veces anteriores.
            </p>
            <div class="mt-2 flex gap-3">
                <a href="{{ route('clientes.show', $soporte->cliente_id) }}" wire:navigate
                    class="text-amber-900 underline text-xs">Ver ficha del abonado</a>
                <a href="{{ route('soportes.index', ['q' => $soporte->cliente->codigo_abonado ?? $soporte->cliente->cedula]) }}"
                    wire:navigate class="text-amber-900 underline text-xs">Ver todos sus casos</a>
            </div>
        </div>
    @endif

    {{-- Datos --}}
    <div class="bg-slate-50 border rounded p-4 mb-4 grid gap-x-6 gap-y-1 text-sm text-slate-800 md:grid-cols-2">
        <p><span class="font-semibold">Cliente:</span> {{ $soporte->cliente->nombre }}</p>
        <p><span class="font-semibold">Abonado:</span> {{ $soporte->cliente->codigo_abonado ?? '—' }} · CC {{ $soporte->cliente->cedula }}</p>
        <p><span class="font-semibold">Tipo:</span> {{ $soporte->etiquetaTipo() }}</p>
        <p><span class="font-semibold">Entró por:</span> {{ $soporte->etiquetaCanal() }}</p>
        @if ($soporte->servicio_afectado)
            <p><span class="font-semibold">Servicio:</span> {{ \App\Models\Soporte::SERVICIOS[$soporte->servicio_afectado] ?? '—' }}</p>
        @endif
        @if ($soporte->tipoFalla)
            <p><span class="font-semibold">Falla reportada:</span> {{ $soporte->tipoFalla->nombre }}</p>
        @endif
        <p><span class="font-semibold">Contrato:</span> {{ $soporte->contrato->numero_contrato ?? 'Sin contrato' }}</p>
        <p><span class="font-semibold">Registrado por:</span> {{ $soporte->usuarioRegistra->name }}</p>
        <p><span class="font-semibold">Ingreso:</span> {{ $soporte->created_at->format('d/m/Y H:i') }}</p>
        <p>
            <span class="font-semibold">Criticidad:</span>
            {{ \App\Models\Soporte::CRITICIDADES[$soporte->criticidad] ?? $soporte->criticidad }}
            @if ($soporte->criticidad_manual)
                <span class="text-xs text-slate-500">(elevada a mano)</span>
            @endif
        </p>
        @if ($soporte->diagnostico)
            <p><span class="font-semibold">Diagnóstico:</span> {{ $soporte->diagnostico->nombre }}</p>
        @endif

        <div class="md:col-span-2 pt-2">
            <p class="font-semibold">Descripción:</p>
            <p class="text-slate-700">{{ $soporte->descripcion }}</p>
        </div>

        @if ($soporte->observaciones_cierre)
            <div class="md:col-span-2 pt-2">
                <p class="font-semibold">Observaciones de cierre:</p>
                <p class="text-slate-700">{{ $soporte->observaciones_cierre }}</p>
            </div>
        @endif

        @if ($soporte->intentos_contacto > 0)
            <p class="md:col-span-2 pt-2 text-orange-800">
                <span class="font-semibold">Intentos de contacto:</span> {{ $soporte->intentos_contacto }}
                @if ($soporte->proximo_intento_at && !$soporte->estaCerrado())
                    · próximo el {{ $soporte->proximo_intento_at->format('d/m/Y H:i') }}
                @endif
            </p>
        @endif

        <div class="md:col-span-2 pt-2 flex flex-wrap gap-4 text-xs text-slate-600">
            @if ($soporte->tiempo_respuesta !== null)
                <span><span class="font-semibold">Respuesta:</span> {{ $soporte->tiempo_respuesta }} min hábiles</span>
            @endif
            @if ($soporte->tiempo_resolucion !== null)
                <span><span class="font-semibold">Resolución:</span> {{ $soporte->tiempo_resolucion }} min hábiles</span>
            @endif
        </div>

        @if ($soporte->motivo_cancelacion)
            <p class="md:col-span-2 pt-2 text-red-700">
                <span class="font-semibold">Motivo de cancelación:</span> {{ $soporte->motivo_cancelacion }}
            </p>
        @endif
    </div>

    {{-- Nivel 3 --}}
    @if ($soporte->escalado_nivel_3)
        <div class="bg-white border rounded p-4 mb-4 text-sm">
            <h2 class="font-semibold text-slate-800 mb-2">Ingeniería de redes</h2>
            <p><span class="font-semibold">Asignado a:</span> {{ $soporte->escaladoA->name ?? '—' }}</p>
            <p><span class="font-semibold">Escalado el:</span> {{ $soporte->fecha_escalamiento?->format('d/m/Y H:i') }}</p>
            <p class="mt-1 text-slate-700">{{ $soporte->motivo_escalamiento }}</p>

            @if ($soporte->respuesta_n3)
                <p class="mt-2 pt-2 border-t"><span class="font-semibold">Respuesta:</span> {{ $soporte->respuesta_n3 }}</p>
            @endif
        </div>
    @endif

    {{-- Cambio de plan --}}
    @if ($soporte->cambioPlan)
        <div class="bg-white border rounded p-4 mb-4 text-sm">
            <h2 class="font-semibold text-slate-800 mb-2">Cambio de plan</h2>
            <p>
                {{ $soporte->cambioPlan->planAnterior->nombre ?? 'Sin registrar' }}
                <span class="mx-2 text-slate-400">&rarr;</span>
                <span class="font-medium">{{ $soporte->cambioPlan->planNuevo->nombre ?? '—' }}</span>
            </p>
            <p class="text-slate-600 mt-1">
                Ejecución: {{ \App\Models\SoporteCambioPlan::TIEMPOS_EJECUCION[$soporte->cambioPlan->tiempo_ejecucion] ?? '—' }}
            </p>
        </div>
    @endif

    {{-- Cambio de titular --}}
    @if ($soporte->cambioTitular)
        <div class="bg-white border rounded p-4 mb-4 text-sm">
            <h2 class="font-semibold text-slate-800 mb-2">Cambio de titular</h2>
            <p>
                {{ $soporte->cliente->nombre }}
                <span class="mx-2 text-slate-400">&rarr;</span>
                <span class="font-medium">{{ $soporte->cambioTitular->titular_nuevo_nombre }}</span>
                <span class="text-slate-500">(CC {{ $soporte->cambioTitular->titular_nuevo_cedula }})</span>
            </p>
            <p class="text-slate-600 mt-1">
                @if ($soporte->cambioTitular->incluye_cambio_plan) Incluye cambio de plan. @endif
                @if ($soporte->cambioTitular->incluye_traslado) Incluye traslado. @endif
            </p>
        </div>
    @endif

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
                <p class="text-xs text-slate-500 mt-1">Hay una visita en curso. Solo un administrador puede reasignar.</p>
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

                @if ($soporte->puedeTransicionarA('solucionado') && !$this->ordenAbierta)
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
                @elseif ($soporte->puedeTransicionarA('enviado_tecnico'))
                    <button wire:click="$set('panel', 'visita')" class="bg-indigo-600 text-white px-4 py-2 rounded">
                        Programar visita
                    </button>
                @endif

                @if ($soporte->puedeTransicionarA('escalado_n3'))
                    <button wire:click="$set('panel', 'escalar')" class="bg-slate-800 text-white px-4 py-2 rounded">
                        Escalar a redes
                    </button>
                @endif

                @if ($soporte->puedeTransicionarA('sin_contacto'))
                    <button wire:click="sinContacto"
                        wire:confirm="¿Registrar un intento de contacto sin respuesta?"
                        class="bg-orange-500 text-white px-4 py-2 rounded">
                        No contesta
                    </button>
                @endif

                @if ($soporte->puedeTransicionarA('seguimiento'))
                    <button wire:click="seguimiento" class="bg-cyan-600 text-white px-4 py-2 rounded">
                        Seguimiento
                    </button>
                @endif

                @if ($soporte->estado === 'sin_contacto')
                    <button wire:click="retomar" class="bg-blue-600 text-white px-4 py-2 rounded">
                        Retomar
                    </button>
                @endif

                @unless ($soporte->esInmediato())
                    <button wire:click="$set('panel', 'criticidad')" class="border border-red-300 text-red-700 px-4 py-2 rounded">
                        Elevar a inmediato
                    </button>
                @endunless

                @if ($soporte->puedeTransicionarA('cancelado'))
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

    {{-- Visitas --}}
    @if ($soporte->ordenesTrabajo->count())
        <div class="bg-white border rounded p-4 mb-4">
            <h2 class="font-semibold text-slate-800 mb-2">Visitas</h2>
            <ul class="text-sm divide-y">
                @foreach ($soporte->ordenesTrabajo as $orden)
                    <li class="py-2 flex justify-between items-center">
                        <span>
                            #{{ $orden->id }} · {{ $orden->tecnico->user->name ?? $orden->tecnico->nombre }}
                            @if ($orden->fecha_programada)
                                · {{ $orden->fecha_programada->format('d/m/Y') }}
                                {{ \App\Models\OrdenTrabajo::FRANJAS[$orden->franja] ?? '' }}
                            @endif
                            <span class="text-slate-500">({{ $orden->etiquetaEstado() }})</span>
                        </span>
                        <a href="{{ route('ordenes.show', $orden) }}" wire:navigate class="text-blue-600">Abrir</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Historial --}}
    @if ($soporte->historialEstados->count())
        <div class="bg-white border rounded p-4">
            <h2 class="font-semibold text-slate-800 mb-2">Historial</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($soporte->historialEstados as $h)
                    <li class="border-l-2 border-slate-300 pl-3">
                        <p class="text-slate-700">
                            <span class="font-medium">{{ \App\Models\Soporte::ESTADOS[$h->estado_nuevo] ?? $h->estado_nuevo }}</span>
                            @if ($h->estado_anterior && $h->estado_anterior !== $h->estado_nuevo)
                                <span class="text-slate-400">(desde {{ \App\Models\Soporte::ESTADOS[$h->estado_anterior] ?? $h->estado_anterior }})</span>
                            @endif
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ $h->usuario->name ?? 'Sistema' }} · {{ $h->created_at->format('d/m/Y H:i') }}
                        </p>
                        @if ($h->motivo)
                            <p class="text-xs text-slate-600 mt-1">{{ $h->motivo }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
