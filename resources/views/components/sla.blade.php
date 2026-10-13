@props(['soporte', 'barra' => true])

{{--
    Semáforo de SLA de un caso, con sus cuatro señales: forma, palabra, barra y
    tiempo. Nunca depende solo del color. "Vencido" es el único relleno sólido
    (y su barra va rayada): lo único que grita en la bandeja.
--}}
@php
    $cerrado = $soporte->estaCerrado();
    $estado = $cerrado ? 'cerrado' : $soporte->semaforoSla();
    $porcentaje = $cerrado ? 0 : $soporte->porcentajeSla();

    $motivoPausa = match ($soporte->estado) {
        \App\Models\Soporte::ESTADO_ENVIADO_TECNICO => 'visita',
        \App\Models\Soporte::ESTADO_ESCALADO_N3 => 'redes',
        \App\Models\Soporte::ESTADO_SIN_CONTACTO => 'cliente',
        default => 'en espera',
    };

    $vence = $soporte->sla_vence_at;
    $desde = $vence ? ($vence->isToday() ? $vence->format('H:i') : $vence->format('d/m')) : null;

    [$palabra, $tiempo, $chip, $barraColor] = match ($estado) {
        'vencido' => ['Vencido', $desde ? "desde {$desde}" : null, 'bg-sla-vencido text-white', null],
        'riesgo' => ['En riesgo', $soporte->restanteLegible(), 'bg-sla-riesgo-fondo text-sla-riesgo', 'bg-sla-riesgo-barra'],
        'atencion' => ['Atención', $soporte->restanteLegible(), 'bg-sla-atencion-fondo text-sla-atencion', 'bg-sla-atencion-barra'],
        'pausado' => ['En pausa', $motivoPausa, 'border border-dashed border-sla-pausa-borde text-sla-pausa', null],
        'cerrado' => ['Cerrado', null, 'bg-elevada text-tinta-3', null],
        default => ['En plazo', $soporte->restanteLegible(), 'bg-sla-ok-fondo text-sla-ok', 'bg-sla-ok-barra'],
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-1']) }}>
    <span class="inline-flex h-[22px] items-center gap-1.5 self-start whitespace-nowrap rounded px-[7px] text-xs font-semibold {{ $chip }}">
        @unless ($cerrado)
            <x-sla-glifo :estado="$estado" :tamano="11" :invertido="$estado === 'vencido'" />
        @endunless
        {{ $palabra }}
        @if ($tiempo)
            <span class="{{ $estado === 'pausado' ? '' : 'font-mono' }} font-medium">· {{ $tiempo }}</span>
        @endif
    </span>

    @if ($barra && ! $cerrado)
        <span class="relative block h-[3px] w-[132px] max-w-full rounded-full bg-linea" aria-hidden="true">
            @if ($estado === 'vencido')
                <span class="absolute inset-0 rounded-full" style="background: repeating-linear-gradient(135deg, rgb(var(--c-sla-vencido)) 0 5px, rgb(var(--c-sla-vencido-oscuro)) 5px 10px)"></span>
            @elseif ($estado === 'pausado')
                <span class="absolute inset-y-0 left-0 rounded-full" style="width: {{ $porcentaje }}%; background: repeating-linear-gradient(90deg, rgb(var(--c-sla-pausa-borde)) 0 4px, transparent 4px 7px)"></span>
            @else
                <span class="absolute inset-y-0 left-0 rounded-full {{ $barraColor }}" style="width: {{ max(2, $porcentaje) }}%"></span>
            @endif
        </span>
    @endif
</div>
