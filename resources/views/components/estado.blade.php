@props(['estado', 'etiqueta' => null])

{{--
    Estado de un caso o de una visita. Contorno y punto, nunca relleno: así no
    compite con el semáforo de SLA, que es el que debe saltar a la vista.
--}}
@php
    $etiqueta ??= \App\Models\Soporte::ESTADOS[$estado] ?? \App\Models\OrdenTrabajo::ESTADOS[$estado] ?? $estado;

    // punto: hueco | lleno | rombo | listo | tachado
    [$punto, $color] = match ($estado) {
        'en_proceso', 'en_progreso' => ['lleno', 'bg-estado-proceso'],
        'seguimiento' => ['lleno', 'bg-estado-seguimiento'],
        'sin_contacto' => ['lleno', 'bg-estado-sincontacto'],
        'enviado_tecnico', 'programada' => ['lleno', 'bg-estado-enviado'],
        'escalado_n3' => ['rombo', 'bg-estado-escalado'],
        // Registros (clientes, contratos, usuarios): activo y suspendido con punto lleno, retirado tachado.
        'activo', 'activa' => ['lleno', 'bg-sla-ok-barra'],
        'suspendido' => ['lleno', 'bg-sla-atencion-barra'],
        'solucionado', 'completado' => ['listo', 'text-sla-ok'],
        'cancelado', 'cerrado_sin_contacto', 'no_realizada', 'retirado' => ['tachado', ''],
        default => ['hueco', 'border-tinta-3'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex h-[22px] items-center gap-1.5 whitespace-nowrap rounded-full border border-linea px-2 text-xs font-medium '.(in_array($punto, ['listo', 'tachado'], true) ? 'bg-elevada' : 'bg-superficie').' '.($punto === 'tachado' ? 'text-tinta-3' : 'text-tinta')]) }}>
    @switch($punto)
        @case('lleno')
            <span class="size-[7px] rounded-full {{ $color }}" aria-hidden="true"></span>
            @break
        @case('rombo')
            <span class="size-[7px] rotate-45 rounded-[1px] {{ $color }}" aria-hidden="true"></span>
            @break
        @case('listo')
            <svg class="{{ $color }}" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M1.5 5.2 4 7.6 8.6 2.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            @break
        @case('tachado')
            <svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M2 5h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path></svg>
            @break
        @default
            <span class="size-[7px] rounded-full border-[1.5px] {{ $color }}" aria-hidden="true"></span>
    @endswitch
    <span class="{{ $punto === 'tachado' ? 'line-through' : '' }} {{ $punto === 'listo' ? 'text-sla-ok' : '' }}">{{ $etiqueta }}</span>
</span>
