@php $tema = auth()->user()?->tema ?? 'sistema'; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-tema="{{ $tema }}" @class(['dark' => $tema === 'oscuro'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Mesa de soporte') }}</title>

        @include('partials.tema')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-fondo font-sans text-tinta antialiased">
        <header class="mx-auto flex max-w-[1100px] items-center justify-between gap-3 px-4 py-4 sm:px-6">
            <a href="/" class="flex items-center gap-2.5 text-[15px] font-semibold text-tinta no-underline">
                <x-application-logo class="size-7" />
                Mesa de soporte
            </a>

            @auth
                <x-boton variante="primario" :href="route('dashboard')">Ir al panel</x-boton>
            @else
                <x-boton variante="primario" :href="route('login')">Entrar</x-boton>
            @endauth
        </header>

        <main class="mx-auto flex max-w-[1100px] flex-col gap-12 px-4 pb-16 pt-6 sm:px-6 lg:pt-12">
            <section class="grid items-center gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
                <div>
                    <p class="titulo-seccion text-acento-texto">Proveedor de internet · soporte técnico</p>
                    <h1 class="mt-3 text-[34px] font-semibold leading-[1.1] tracking-[-0.01em] sm:text-[44px]">
                        Cada caso con su reloj, y el que vence primero arriba.
                    </h1>
                    <p class="mt-4 max-w-[52ch] text-[17px] text-tinta-2">
                        Bandeja de casos con SLA en horario hábil, escalamiento a ingeniería de redes,
                        visitas de campo que se cierran sin señal e informes mensuales por técnico.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-2">
                        @auth
                            <x-boton variante="primario" :href="route('soportes.index')">Abrir la bandeja</x-boton>
                        @else
                            <x-boton variante="primario" :href="route('login')">Entrar a la demo</x-boton>
                        @endauth
                    </div>
                </div>

                {{-- Muestra ilustrativa del semáforo: forma, palabra, barra y tiempo. --}}
                <figure class="tarjeta overflow-hidden" aria-label="Ejemplo de la bandeja de casos">
                    <div class="flex items-center justify-between border-b border-linea bg-elevada px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.06em] text-tinta-3">
                        <span>Caso</span><span>SLA</span>
                    </div>
                    @php
                        $muestra = [
                            ['Sin internet · Cra 12', 'vencido', 'Vencido', 'desde 09:40', 'bg-sla-vencido text-white', 100],
                            ['Lentitud · Barrio Centro', 'riesgo', 'En riesgo', '1 h 10 min', 'bg-sla-riesgo-fondo text-sla-riesgo', 86],
                            ['Sin TV · Calle 40', 'atencion', 'Atención', '5 h 20 min', 'bg-sla-atencion-fondo text-sla-atencion', 62],
                            ['Cambio de plan', 'ok', 'En plazo', '3 d 4 h', 'bg-sla-ok-fondo text-sla-ok', 18],
                            ['Visita programada', 'pausado', 'En pausa', 'visita', 'border border-dashed border-sla-pausa-borde text-sla-pausa', 40],
                        ];
                    @endphp
                    <ul>
                        @foreach ($muestra as [$caso, $estado, $palabra, $tiempo, $chip, $porcentaje])
                            <li class="flex items-center justify-between gap-3 border-b border-linea-suave px-4 py-2.5 last:border-0">
                                <span class="min-w-0 truncate text-[13px] font-medium">{{ $caso }}</span>
                                <span class="flex shrink-0 flex-col items-end gap-1">
                                    <span class="inline-flex h-[22px] items-center gap-1.5 whitespace-nowrap rounded px-[7px] text-xs font-semibold {{ $chip }}">
                                        <x-sla-glifo :estado="$estado" :tamano="11" :invertido="$estado === 'vencido'" />
                                        {{ $palabra }}
                                        <span class="{{ $estado === 'pausado' ? '' : 'font-mono' }} font-medium">· {{ $tiempo }}</span>
                                    </span>
                                    <span class="relative block h-[3px] w-[120px] rounded-full bg-linea" aria-hidden="true">
                                        @if ($estado === 'vencido')
                                            <span class="absolute inset-0 rounded-full" style="background: repeating-linear-gradient(135deg, rgb(var(--c-sla-vencido)) 0 5px, rgb(var(--c-sla-vencido-oscuro)) 5px 10px)"></span>
                                        @elseif ($estado === 'pausado')
                                            <span class="absolute inset-y-0 left-0 rounded-full" style="width: {{ $porcentaje }}%; background: repeating-linear-gradient(90deg, rgb(var(--c-sla-pausa-borde)) 0 4px, transparent 4px 7px)"></span>
                                        @else
                                            <span class="absolute inset-y-0 left-0 rounded-full bg-sla-{{ $estado }}-barra" style="width: {{ $porcentaje }}%"></span>
                                        @endif
                                    </span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </figure>
            </section>

            <section class="grid gap-3 sm:grid-cols-3">
                @php
                    $piezas = [
                        ['Semáforo que no depende del color', 'Cada estado tiene su forma, su palabra, su barra y su tiempo. Solo lo vencido va relleno.'],
                        ['Campo sin señal', 'El técnico cierra la visita con fotos y firma aunque no haya red; se envía sola al volver la señal.'],
                        ['Informe del mes', 'Cifras por técnico frente al equipo y un espacio de retroalimentación que se imprime limpio.'],
                    ];
                @endphp
                @foreach ($piezas as [$titulo, $texto])
                    <div class="tarjeta p-5">
                        <h2 class="text-[15px] font-semibold">{{ $titulo }}</h2>
                        <p class="mt-1.5 text-sm text-tinta-2">{{ $texto }}</p>
                    </div>
                @endforeach
            </section>
        </main>

        <footer class="border-t border-linea py-6 text-center text-xs text-tinta-3">
            Mesa de soporte técnico de un proveedor de internet · demo de portafolio
        </footer>
    </body>
</html>
