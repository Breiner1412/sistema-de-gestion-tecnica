@php($usuario = rescue(fn () => auth()->user(), null, false))
@php($tema = $usuario?->tema ?? 'sistema')
<!DOCTYPE html>
{{-- Páginas de error con la misma cara que el resto de la aplicación. Sin
     Livewire ni menú: tienen que poder pintarse aunque falle lo demás. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-tema="{{ $tema }}" @class(['dark' => $tema === 'oscuro'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('codigo') · {{ config('app.name', 'Mesa de soporte') }}</title>

        @include('partials.tema')
        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen flex-col bg-fondo font-sans text-tinta antialiased">
        <header class="flex items-center gap-2.5 px-5 py-4 sm:px-8">
            <a href="/" class="flex items-center gap-2.5 text-[15px] font-semibold text-tinta no-underline">
                <x-application-logo class="size-7" />
                Mesa de soporte
            </a>
        </header>

        <main class="flex flex-1 items-center justify-center px-4 pb-16">
            <div class="w-full max-w-[460px] text-center">
                <p class="font-mono text-[56px] font-semibold leading-none text-tinta-3">@yield('codigo')</p>
                <h1 class="mt-4 text-[22px] font-semibold">@yield('titulo')</h1>
                <p class="mt-2 text-[15px] text-tinta-2">@yield('mensaje')</p>
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    @if ($usuario)
                        <x-boton variante="primario" :href="route('dashboard')">Ir al panel</x-boton>
                    @else
                        <x-boton variante="primario" :href="route('login')">Entrar</x-boton>
                    @endif
                    <x-boton onclick="history.back()">Volver</x-boton>
                </div>
            </div>
        </main>
    </body>
</html>
