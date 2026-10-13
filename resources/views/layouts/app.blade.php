@php($tema = auth()->user()?->tema ?? 'sistema')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-tema="{{ $tema }}" @class(['dark' => $tema === 'oscuro'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($titulo) ? $titulo.' · ' : '' }}{{ config('app.name', 'Mesa de soporte') }}</title>

        @include('partials.tema')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-fondo font-sans text-tinta antialiased">
        <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-control focus:bg-superficie focus:px-3 focus:py-2">Saltar al contenido</a>

        <div class="lg:flex">
            <livewire:layout.navigation />

            <main id="contenido" class="min-w-0 flex-1">
                @if (isset($header))
                    <header class="border-b border-linea bg-superficie">
                        <div class="mx-auto max-w-7xl px-4 py-5 sm:px-7">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                {{ $slot }}
            </main>
        </div>
    </body>
</html>
