<!DOCTYPE html>
{{-- Sin sesión no hay preferencia guardada: se sigue el tema del sistema. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-tema="sistema">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Mesa de soporte') }}</title>

        @include('partials.tema')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-fondo font-sans text-tinta antialiased">
        <div class="flex min-h-screen flex-col">
            <header class="flex items-center gap-2.5 px-5 py-4 sm:px-8">
                <a href="/" wire:navigate class="flex items-center gap-2.5 text-[15px] font-semibold text-tinta no-underline">
                    <x-application-logo class="size-7" />
                    Mesa de soporte
                </a>
            </header>

            <main class="flex flex-1 items-start justify-center px-4 pb-12 pt-4 sm:items-center sm:pt-0">
                <div class="w-full max-w-[420px]">
                    <div class="tarjeta p-6 sm:p-8">
                        {{ $slot }}
                    </div>
                    <p class="mt-4 text-center text-xs text-tinta-3">Mesa de soporte técnico de un proveedor de internet · demo de portafolio</p>
                </div>
            </main>
        </div>
        @include('partials.limpiar-campo')
    </body>
</html>
