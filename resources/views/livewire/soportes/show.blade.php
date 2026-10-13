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

<div class="mx-auto flex max-w-[1200px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
    <x-mensajes />

    <x-encabezado :titulo="$soporte->numero_soporte ?? 'Caso #'.$soporte->id"
        :subtitulo="($soporte->cliente->nombre ?? 'Sin cliente').' · '.$soporte->etiquetaTipo().' · entró por '.$soporte->etiquetaCanal()">
        <x-slot:antes>
            <a href="{{ route('soportes.index') }}" wire:navigate class="inline-flex items-center gap-1 text-tinta-3 no-underline hover:text-acento-texto">
                <svg width="13" height="13" viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                Bandeja de casos
            </a>
        </x-slot:antes>
        <x-slot:acciones>
            <x-estado :estado="$soporte->estado" :etiqueta="$soporte->etiquetaEstado()" class="h-7 px-3 text-[13px]" />
            @if ($soporte->esInmediato())
                <x-inmediata />
            @endif
            @if ($soporte->escalado_nivel_3)
                <span class="inline-flex h-[22px] items-center gap-1.5 rounded-full border border-linea px-2 text-xs font-medium text-tinta-2">
                    <span class="size-[7px] rotate-45 rounded-[1px] bg-estado-escalado" aria-hidden="true"></span>
                    Pasó por nivel 3
                </span>
            @endif
        </x-slot:acciones>
    </x-encabezado>

    {{-- Reloj --}}
    @unless ($soporte->estaCerrado())
        @php $semaforo = $soporte->semaforoSla(); @endphp
        <section @class([
            'tarjeta flex flex-wrap items-center justify-between gap-3 p-4',
            'border-sla-vencido' => $semaforo === 'vencido',
        ]) aria-label="Reloj de SLA">
            <div class="flex min-w-0 flex-col gap-1.5">
                <x-sla :soporte="$soporte" class="[&>span:last-child]:w-[220px]" />
                <p class="text-[13px] text-tinta-2">
                    @if ($semaforo === 'pausado')
                        Reloj en pausa: el caso espera a un tercero, el tiempo no corre contra el nivel 2.
                    @elseif ($semaforo === 'vencido')
                        SLA incumplido. Venció el <span class="font-mono">{{ $soporte->sla_vence_at?->format('d/m/Y H:i') ?? '—' }}</span>.
                    @else
                        Quedan {{ $soporte->restanteLegible() }} · vence el <span class="font-mono">{{ $soporte->sla_vence_at?->format('d/m/Y H:i') ?? '—' }}</span>
                    @endif
                </p>
            </div>
            <div class="ml-auto text-right">
                <p class="font-mono text-[22px] font-semibold leading-none">{{ $soporte->porcentajeSla() }}<span class="text-sm text-tinta-3">%</span></p>
                <p class="mt-1 text-xs text-tinta-3">consumido</p>
            </div>
        </section>
    @endunless

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="order-2 flex min-w-0 flex-col gap-4 lg:order-none">
            {{-- Reincidencia --}}
            @if ($this->reportesPrevios > 0)
                @php $dias = config('sla.recurrencia.ventana_dias', 60); @endphp
                <section class="tarjeta border-l-[3px] border-l-recurrente p-4 text-sm">
                    <p class="flex items-center gap-2 font-semibold text-recurrente">
                        <span class="rounded-[3px] bg-recurrente-fondo px-1.5 text-[11px] leading-[18px]">↻ {{ $this->totalDelAbonado }}</span>
                        Abonado reincidente
                    </p>
                    <p class="mt-1 text-tinta">
                        Ya había reportado <strong class="font-mono">{{ $this->reportesPrevios }}</strong>
                        {{ $this->reportesPrevios === 1 ? 'vez' : 'veces' }} en los {{ $dias }} días previos a este caso,
                        y lleva <span class="font-mono">{{ $this->totalDelAbonado }}</span> en total.
                    </p>
                    <p class="mt-1 text-[13px] text-tinta-3">
                        Antes de repetir el mismo procedimiento, vale la pena mirar qué se le diagnosticó las veces anteriores.
                    </p>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[13px] font-medium">
                        <a href="{{ route('clientes.show', $soporte->cliente_id) }}" wire:navigate>Ver ficha del abonado</a>
                        <a href="{{ route('soportes.index', ['q' => $soporte->cliente->codigo_abonado ?? $soporte->cliente->cedula]) }}" wire:navigate>Ver todos sus casos</a>
                    </div>
                </section>
            @endif

            @include('livewire.soportes.partes.datos')

            @include('livewire.soportes.partes.historial')
        </div>

        <aside class="order-1 flex flex-col gap-4 lg:sticky lg:top-6 lg:order-none">
            @include('livewire.soportes.partes.acciones')

            @if ($soporte->estaCerrado())
                <section class="tarjeta p-4 text-sm text-tinta-2">
                    <p class="titulo-seccion mb-1">Caso cerrado</p>
                    @if ($soporte->fecha_cierre)
                        <p>Cerrado el <span class="font-mono">{{ $soporte->fecha_cierre->format('d/m/Y H:i') }}</span>.</p>
                    @endif
                    <p>Responsable: {{ $soporte->tecnicoSoporte->name ?? 'Sin asignar' }}</p>
                </section>
            @endif
        </aside>
    </div>
</div>
