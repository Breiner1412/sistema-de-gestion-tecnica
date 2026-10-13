@php($tema = auth()->user()?->tema_campo ?? 'claro')
<!DOCTYPE html>
{{-- La pantalla de campo tiene su propio tema: claro por defecto, porque se
     usa al sol, y oscuro solo si el técnico lo pide para la noche. --}}
<html lang="es" data-tema="{{ $tema }}" @class(['h-full', 'dark' => $tema === 'oscuro'])>
<head>
    <meta charset="utf-8">
    {{-- viewport-fit y user-scalable: el técnico usa esto de pie, con una mano
         y a veces con guantes. Un zoom accidental al tocar un botón estorba. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- La cola del teléfono solo envía los cierres de quien tiene la sesión. --}}
    <meta name="usuario-id" content="{{ auth()->id() }}">
    <meta name="theme-color" content="#11161C">

    <title>Mi ruta · {{ config('app.name') }}</title>

    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('iconos/campo-192.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    @include('partials.tema')
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/campo.js'])

    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="h-full bg-fondo font-sans text-tinta antialiased">

    {{-- Barra de estado de la conexión. Vive fuera del contenido para que
         sobreviva a las navegaciones de Livewire y no parpadee. --}}
    <div x-data="estadoDeConexion" x-cloak
         class="sticky top-0 z-40 text-sm font-semibold text-white transition-colors"
         :class="sinSenal ? 'bg-[#7A4800] dark:bg-[#4A3000]' : (pendientes > 0 ? 'bg-[#193F99] dark:bg-[#182644]' : 'bg-menu')">

        <div class="flex min-h-11 items-center justify-between gap-3 px-4 py-2">
            <a href="{{ route('campo.ruta') }}" class="flex items-center gap-2 text-white no-underline">
                <svg width="18" height="18" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 17.5s5.5-5 5.5-9.5a5.5 5.5 0 0 0-11 0c0 4.5 5.5 9.5 5.5 9.5Z" fill="none" stroke="currentColor" stroke-width="1.6"></path><circle cx="10" cy="8" r="2" fill="none" stroke="currentColor" stroke-width="1.6"></circle></svg>
                Mi ruta
            </a>

            <div class="flex items-center gap-2">
                <template x-if="sinSenal">
                    <span class="flex items-center gap-1.5">
                        <svg width="16" height="16" viewBox="0 0 18 18" aria-hidden="true"><path d="M2 7c4-4 10-4 14 0M4.5 9.8c2.6-2.4 6.4-2.4 9 0M7 12.6c1.2-1 2.8-1 4 0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"></path><path d="M2.5 2.5l13 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
                        Sin señal · se guarda en el teléfono
                    </span>
                </template>
                <template x-if="!sinSenal && pendientes > 0">
                    <span x-text="`Enviando ${pendientes} pendiente${pendientes === 1 ? '' : 's'}…`"></span>
                </template>
                <template x-if="!sinSenal && pendientes === 0">
                    <span class="font-normal text-menu-tinta">{{ auth()->user()->name }}</span>
                </template>

                <span class="h-2.5 w-2.5 rounded-full"
                      :class="sinSenal ? 'bg-[#FFD27A]' : (pendientes > 0 ? 'bg-[#A9C2F8] motion-safe:animate-pulse' : 'bg-[#6FD39C]')"></span>
            </div>
        </div>

        <template x-if="ultimoError">
            <p class="bg-sla-vencido px-4 py-2 text-[13px]" role="alert" x-text="ultimoError"></p>
        </template>

        <template x-if="sesionCaducada">
            <p class="bg-[#7A4800] px-4 py-2 text-[13px] font-medium dark:bg-[#4A3000]">
                Tu sesión se cerró. Lo guardado sigue en el teléfono:
                <a href="{{ route('login') }}" class="font-semibold text-white underline">entra otra vez</a> y se enviará solo.
            </p>
        </template>

        <template x-if="ajenos > 0">
            <p class="bg-menu-activo px-4 py-2 text-[13px] font-medium"
               x-text="`${ajenos} cierre${ajenos === 1 ? '' : 's'} de otra cuenta guardado${ajenos === 1 ? '' : 's'} en este teléfono: se enviará${ajenos === 1 ? '' : 'n'} cuando esa persona vuelva a entrar.`"></p>
        </template>

        {{-- Lo que el servidor no aceptó sigue en el teléfono hasta que el
             técnico decida: la firma y las fotos no se pueden volver a tomar. --}}
        <template x-if="rechazados.length > 0">
            <div class="bg-sla-vencido-oscuro px-4 py-2 text-[13px] font-medium">
                <button type="button" class="underline" @click="verRechazados = !verRechazados"
                        x-text="`${rechazados.length} cierre${rechazados.length === 1 ? '' : 's'} sin aceptar en este teléfono`"></button>

                <ul x-show="verRechazados" class="mt-2 space-y-2">
                    <template x-for="cierre in rechazados" :key="cierre.uuid">
                        <li class="rounded-control bg-black/25 p-2">
                            <p x-text="cierre.error"></p>
                            <p class="text-white/80" x-text="new Date(cierre.creado).toLocaleString()"></p>
                            <div class="mt-1 flex gap-3">
                                <button type="button" class="underline" @click="descargar(cierre.uuid)">Descargar copia</button>
                                <button type="button" class="underline" @click="descartar(cierre.uuid)">Descartar</button>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
        </template>
    </div>

    <main class="mx-auto max-w-xl pb-28">
        {{ $slot }}
    </main>

    <script>
        // El service worker es lo que permite que la pantalla abra sin señal.
        // Su alcance es solo /campo: el resto de la aplicación no pasa por él.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', async () => {
                const alcance = new URL('{{ url('/campo') }}').pathname;

                // La versión anterior se registraba sobre todo el sitio.
                for (const registro of await navigator.serviceWorker.getRegistrations()) {
                    if (new URL(registro.scope).pathname !== alcance) {
                        await registro.unregister();
                    }
                }

                navigator.serviceWorker
                    .register('{{ asset('sw.js') }}', { scope: alcance })
                    .catch((error) => console.warn('No se pudo registrar el service worker', error));
            });
        }
    </script>
</body>
</html>
