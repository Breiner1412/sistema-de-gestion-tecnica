{{-- Datos del caso, escalamiento a redes y cambios de plan o titular. --}}

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
