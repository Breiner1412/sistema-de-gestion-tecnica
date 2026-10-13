{{-- Datos del caso, escalamiento a redes y cambios de plan o titular. --}}

@php
    $dato = 'flex flex-col gap-0.5';
    $rotulo = 'text-xs font-medium text-tinta-3';
@endphp

{{-- Datos --}}
<section class="tarjeta p-4">
    <h2 class="titulo-seccion mb-3">Datos del caso</h2>

    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 xl:grid-cols-3">
        <div class="{{ $dato }}">
            <dt class="{{ $rotulo }}">Cliente</dt>
            <dd class="font-medium">{{ $soporte->cliente->nombre }}</dd>
        </div>
        <div class="{{ $dato }}">
            <dt class="{{ $rotulo }}">Abonado · cédula</dt>
            <dd class="font-mono text-[13px]">{{ $soporte->cliente->codigo_abonado ?? '—' }} · CC {{ $soporte->cliente->cedula }}</dd>
        </div>
        <div class="{{ $dato }}">
            <dt class="{{ $rotulo }}">Contrato</dt>
            <dd class="font-mono text-[13px]">{{ $soporte->contrato->numero_contrato ?? 'Sin contrato' }}</dd>
        </div>
        <div class="{{ $dato }}">
            <dt class="{{ $rotulo }}">Tipo</dt>
            <dd>{{ $soporte->etiquetaTipo() }}</dd>
        </div>
        <div class="{{ $dato }}">
            <dt class="{{ $rotulo }}">Entró por</dt>
            <dd>{{ $soporte->etiquetaCanal() }}</dd>
        </div>
        @if ($soporte->servicio_afectado)
            <div class="{{ $dato }}">
                <dt class="{{ $rotulo }}">Servicio</dt>
                <dd>{{ \App\Models\Soporte::SERVICIOS[$soporte->servicio_afectado] ?? '—' }}</dd>
            </div>
        @endif
        @if ($soporte->tipoFalla)
            <div class="{{ $dato }}">
                <dt class="{{ $rotulo }}">Falla reportada</dt>
                <dd>{{ $soporte->tipoFalla->nombre }}</dd>
            </div>
        @endif
        <div class="{{ $dato }}">
            <dt class="{{ $rotulo }}">Criticidad</dt>
            <dd>
                {{ \App\Models\Soporte::CRITICIDADES[$soporte->criticidad] ?? $soporte->criticidad }}
                @if ($soporte->criticidad_manual)
                    <span class="text-xs text-tinta-3">(elevada a mano)</span>
                @endif
            </dd>
        </div>
        @if ($soporte->diagnostico)
            <div class="{{ $dato }}">
                <dt class="{{ $rotulo }}">Diagnóstico</dt>
                <dd>{{ $soporte->diagnostico->nombre }}</dd>
            </div>
        @endif
        <div class="{{ $dato }}">
            <dt class="{{ $rotulo }}">Registrado por</dt>
            <dd>{{ $soporte->usuarioRegistra->name }}</dd>
        </div>
        <div class="{{ $dato }}">
            <dt class="{{ $rotulo }}">Ingreso</dt>
            <dd class="font-mono text-[13px]">{{ $soporte->created_at->format('d/m/Y H:i') }}</dd>
        </div>
    </dl>

    <div class="mt-4 border-t border-linea-suave pt-3">
        <p class="{{ $rotulo }}">Descripción</p>
        <p class="mt-0.5 whitespace-pre-line text-sm">{{ $soporte->descripcion }}</p>
    </div>

    @if ($soporte->observaciones_cierre)
        <div class="mt-3">
            <p class="{{ $rotulo }}">Observaciones de cierre</p>
            <p class="mt-0.5 whitespace-pre-line text-sm">{{ $soporte->observaciones_cierre }}</p>
        </div>
    @endif

    @if ($soporte->intentos_contacto > 0)
        <p class="mt-3 flex items-center gap-2 text-sm text-sla-riesgo">
            <svg width="14" height="14" viewBox="0 0 20 20" aria-hidden="true"><path d="M5.5 2.5h2.2l1.2 3.6-1.7 1.2a9 9 0 0 0 5.5 5.5l1.2-1.7 3.6 1.2v2.2a1.6 1.6 0 0 1-1.7 1.6A14 14 0 0 1 3.9 4.2a1.6 1.6 0 0 1 1.6-1.7Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path></svg>
            <span><span class="font-semibold">Intentos de contacto:</span> <span class="font-mono">{{ $soporte->intentos_contacto }}</span>
            @if ($soporte->proximo_intento_at && !$soporte->estaCerrado())
                · próximo el <span class="font-mono">{{ $soporte->proximo_intento_at->format('d/m/Y H:i') }}</span>
            @endif
            </span>
        </p>
    @endif

    @if ($soporte->tiempo_respuesta !== null || $soporte->tiempo_resolucion !== null)
        <div class="mt-3 flex flex-wrap gap-4 text-xs text-tinta-3">
            @if ($soporte->tiempo_respuesta !== null)
                <span>Respuesta: <span class="font-mono text-tinta">{{ $soporte->tiempo_respuesta }}</span> min hábiles</span>
            @endif
            @if ($soporte->tiempo_resolucion !== null)
                <span>Resolución: <span class="font-mono text-tinta">{{ $soporte->tiempo_resolucion }}</span> min hábiles</span>
            @endif
        </div>
    @endif

    @if ($soporte->motivo_cancelacion)
        <p class="mt-3 text-sm text-peligro">
            <span class="font-semibold">Motivo de cancelación:</span> {{ $soporte->motivo_cancelacion }}
        </p>
    @endif
</section>

{{-- Nivel 3 --}}
@if ($soporte->escalado_nivel_3)
    <section class="tarjeta p-4 text-sm">
        <h2 class="titulo-seccion mb-2 flex items-center gap-2">
            <span class="size-[7px] rotate-45 rounded-[1px] bg-estado-escalado" aria-hidden="true"></span>
            Ingeniería de redes
        </h2>
        <p><span class="text-tinta-3">Asignado a:</span> {{ $soporte->escaladoA->name ?? '—' }}</p>
        <p><span class="text-tinta-3">Escalado el:</span> <span class="font-mono text-[13px]">{{ $soporte->fecha_escalamiento?->format('d/m/Y H:i') }}</span></p>
        <p class="mt-2">{{ $soporte->motivo_escalamiento }}</p>

        @if ($soporte->respuesta_n3)
            <p class="mt-3 border-t border-linea-suave pt-3"><span class="font-semibold">Respuesta:</span> {{ $soporte->respuesta_n3 }}</p>
        @endif
    </section>
@endif

{{-- Cambio de plan --}}
@if ($soporte->cambioPlan)
    <section class="tarjeta p-4 text-sm">
        <h2 class="titulo-seccion mb-2">Cambio de plan</h2>
        <p>
            {{ $soporte->cambioPlan->planAnterior->nombre ?? 'Sin registrar' }}
            <span class="mx-2 text-tinta-3">&rarr;</span>
            <span class="font-semibold">{{ $soporte->cambioPlan->planNuevo->nombre ?? '—' }}</span>
        </p>
        <p class="mt-1 text-tinta-3">
            Ejecución: {{ \App\Models\SoporteCambioPlan::TIEMPOS_EJECUCION[$soporte->cambioPlan->tiempo_ejecucion] ?? '—' }}
        </p>
    </section>
@endif

{{-- Cambio de titular --}}
@if ($soporte->cambioTitular)
    <section class="tarjeta p-4 text-sm">
        <h2 class="titulo-seccion mb-2">Cambio de titular</h2>
        <p>
            {{ $soporte->cliente->nombre }}
            <span class="mx-2 text-tinta-3">&rarr;</span>
            <span class="font-semibold">{{ $soporte->cambioTitular->titular_nuevo_nombre }}</span>
            <span class="font-mono text-[13px] text-tinta-3">(CC {{ $soporte->cambioTitular->titular_nuevo_cedula }})</span>
        </p>
        <p class="mt-1 text-tinta-3">
            @if ($soporte->cambioTitular->incluye_cambio_plan) Incluye cambio de plan. @endif
            @if ($soporte->cambioTitular->incluye_traslado) Incluye traslado. @endif
        </p>
    </section>
@endif
