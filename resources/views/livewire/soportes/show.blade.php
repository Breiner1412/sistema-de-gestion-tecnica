<?php

use App\Exceptions\TransicionInvalidaException;
use App\Models\Diagnostico;
use App\Models\OrdenTrabajo;
use App\Models\Soporte;
use App\Models\Tecnico;
use App\Models\User;
use Illuminate\Validation\Rule;
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
        $this->authorize('view', $soporte);

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
        return auth()->user()->can('asignar', $this->soporte);
    }

    #[Computed]
    public function puedeTrabajar(): bool
    {
        return auth()->user()->can('trabajar', $this->soporte);
    }

    #[Computed]
    public function puedeCancelar(): bool
    {
        return auth()->user()->can('cancelar', $this->soporte);
    }

    #[Computed]
    public function puedeElevar(): bool
    {
        return auth()->user()->can('elevarCriticidad', $this->soporte);
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
        return auth()->user()->can('responderComoRedes', $this->soporte);
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
        if ($this->ordenAbierta && !auth()->user()->esAdmin()) {
            session()->flash('error', 'El caso ya tiene una visita en curso. Solo un administrador puede reasignarlo.');
            return;
        }

        $this->authorize('asignar', $this->soporte);

        // No basta con que el usuario exista: tiene que ser alguien que pueda
        // llevar el caso (nivel 2 o admin) y estar activo.
        $this->validate(['tecnico_soporte_id' => [
            'required',
            Rule::exists('users', 'id')
                ->whereIn('rol', [User::ROL_TECNICO_SOPORTE, User::ROL_ADMIN])
                ->where('estado', 'activo'),
        ]], [
            'tecnico_soporte_id.exists' => 'Solo se asigna a un técnico de soporte activo.',
        ]);

        $this->soporte->asignarTecnicoSoporte((int) $this->tecnico_soporte_id);
        $this->ok('Responsable asignado.');
    }

    public function resolver(): void
    {
        $this->authorize('trabajar', $this->soporte);

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
        $this->authorize('trabajar', $this->soporte);

        $this->intentar(fn() => $this->soporte->marcarSinContacto(), 'Intento de contacto registrado.');
    }

    public function seguimiento(): void
    {
        $this->authorize('trabajar', $this->soporte);

        $this->intentar(fn() => $this->soporte->cambiarEstado(Soporte::ESTADO_SEGUIMIENTO), 'Caso puesto en seguimiento.');
    }

    public function retomar(): void
    {
        $this->authorize('trabajar', $this->soporte);

        $this->intentar(fn() => $this->soporte->cambiarEstado(Soporte::ESTADO_EN_PROCESO), 'Caso retomado por el nivel 2.');
    }

    public function escalar(): void
    {
        $this->authorize('trabajar', $this->soporte);

        $this->validate([
            'ingeniero_redes_id' => [
                'required',
                Rule::exists('users', 'id')->where('rol', User::ROL_INGENIERO_REDES)->where('estado', 'activo'),
            ],
            'motivo_escalamiento' => 'required|min:10',
        ], [
            'ingeniero_redes_id.exists' => 'Solo se escala a un ingeniero de redes activo.',
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
        $this->authorize('responderComoRedes', $this->soporte);

        $this->validate(['respuesta_n3' => 'required|min:10']);

        $this->intentar(fn() => $this->soporte->devolverDeNivel3($this->respuesta_n3), 'Caso devuelto al nivel 2.');
    }

    public function programarVisita(): void
    {
        $this->authorize('trabajar', $this->soporte);

        if ($orden = $this->ordenAbierta) {
            $this->redirect(route('ordenes.show', $orden), navigate: true);
            return;
        }

        $this->validate([
            'tecnico_campo_id' => ['required', Rule::exists('tecnicos', 'id')->where('estado', 'activo')],
            'fecha_programada' => 'required|date|after_or_equal:today',
            'franja' => 'required|in:manana,tarde',
        ], [
            'tecnico_campo_id.required' => 'Selecciona el técnico que va a hacer la visita.',
            'tecnico_campo_id.exists' => 'Solo se programa la visita con un técnico de campo activo.',
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
        $this->authorize('cancelar', $this->soporte);

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
        $this->authorize('elevarCriticidad', $this->soporte);

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

    @include('livewire.soportes.partes.datos')

    @include('livewire.soportes.partes.acciones')

    @include('livewire.soportes.partes.historial')
</div>
