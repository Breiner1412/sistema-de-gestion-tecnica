<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    {{-- viewport-fit y user-scalable: el técnico usa esto de pie, con una mano
         y a veces con guantes. Un zoom accidental al tocar un botón estorba. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">

    <title>Mi ruta · {{ config('app.name') }}</title>

    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('iconos/campo-192.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/campo.js'])

    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="h-full bg-slate-100 font-sans antialiased text-slate-800">

    {{-- Barra de estado de la conexión. Vive fuera del contenido para que
         sobreviva a las navegaciones de Livewire y no parpadee. --}}
    <div x-data="estadoDeConexion" x-cloak
         class="sticky top-0 z-40 text-sm font-medium text-white transition-colors"
         :class="sinSenal ? 'bg-amber-600' : (pendientes > 0 ? 'bg-sky-700' : 'bg-slate-900')">

        <div class="flex items-center justify-between px-4 py-2">
            <a href="{{ route('campo.ruta') }}" class="font-semibold">Mi ruta</a>

            <div class="flex items-center gap-2">
                <template x-if="sinSenal">
                    <span>Sin señal · se guarda en el teléfono</span>
                </template>
                <template x-if="!sinSenal && pendientes > 0">
                    <span x-text="`Enviando ${pendientes} pendiente${pendientes === 1 ? '' : 's'}…`"></span>
                </template>
                <template x-if="!sinSenal && pendientes === 0">
                    <span class="text-slate-300">{{ auth()->user()->name }}</span>
                </template>

                <span class="h-2.5 w-2.5 rounded-full"
                      :class="sinSenal ? 'bg-amber-300' : (pendientes > 0 ? 'bg-sky-300 animate-pulse' : 'bg-emerald-400')"></span>
            </div>
        </div>

        <template x-if="ultimoError">
            <p class="bg-red-700 px-4 py-2 text-xs" x-text="ultimoError"></p>
        </template>
    </div>

    <main class="mx-auto max-w-xl pb-24">
        {{ $slot }}
    </main>

    <script>
        // El service worker es lo que permite que la pantalla abra sin señal.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(() => {});
            });
        }
    </script>
</body>
</html>
