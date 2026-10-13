<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $usuario = auth()->user();
    $gestion = $usuario->tieneRol(...\App\Models\User::ROLES_GESTION);

    // [ruta, patrón activo, texto, icono, visible, navegación de Livewire]
    // "Mi ruta" carga la página entera: la pantalla de campo tiene su propio tema.
    $enlaces = array_filter([
        ['dashboard', 'dashboard', 'Panel', 'panel', true, true],
        ['soportes.index', 'soportes.*', 'Bandeja de casos', 'bandeja', $gestion, true],
        ['ordenes.index', 'ordenes.*', $usuario->tieneRol('tecnico_campo') ? 'Mis visitas' : 'Visitas de campo', 'visitas', true, true],
        ['campo.ruta', 'campo.*', 'Mi ruta', 'ruta', $usuario->tieneRol('admin', 'gerente', 'tecnico_campo'), false],
        ['clientes.index', 'clientes.*', 'Clientes', 'clientes', $gestion, true],
        ['informes.index', 'informes.*', 'Informes', 'informes', $usuario->tieneRol('admin', 'gerente', 'tecnico_soporte'), true],
        ['inventario.index', 'inventario.*', 'Inventario', 'inventario', $usuario->tieneRol('admin', 'gerente'), true],
        ['usuarios.index', 'usuarios.*', 'Usuarios', 'usuarios', $usuario->esAdmin(), true],
    ], fn ($enlace) => $enlace[4]);

    $iniciales = collect(explode(' ', $usuario->name))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
@endphp

<div x-data="{ abierto: false }" class="no-imprimir lg:sticky lg:top-0 lg:h-screen lg:w-[232px] lg:shrink-0">

    {{-- Barra superior en móvil --}}
    <div class="sticky top-0 z-40 flex h-14 items-center gap-3 bg-menu px-3 lg:hidden">
        <button type="button" x-on:click="abierto = true" aria-label="Abrir el menú" :aria-expanded="abierto"
            class="flex size-11 items-center justify-center rounded-control text-white hover:bg-menu-activo">
            <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>
        </button>
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2 text-[15px] font-semibold text-white no-underline">
            <x-application-logo class="size-7" /> Mesa de soporte
        </a>
    </div>

    {{-- Fondo del cajón en móvil --}}
    <div x-show="abierto" x-transition.opacity x-on:click="abierto = false" class="fixed inset-0 z-40 bg-black/50 lg:hidden" style="display: none;"></div>

    <nav aria-label="Principal"
        x-on:keydown.escape.window="abierto = false"
        :class="abierto ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-50 flex w-[264px] -translate-x-full flex-col gap-1 overflow-y-auto bg-menu px-3 py-4 transition-transform duration-200 lg:static lg:z-auto lg:h-full lg:w-full lg:translate-x-0 lg:border-r lg:border-menu-borde">

        <div class="flex items-center justify-between px-2 pb-4">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5 text-[15px] font-semibold text-white no-underline">
                <x-application-logo class="size-[26px]" /> Mesa de soporte
            </a>
            <button type="button" x-on:click="abierto = false" aria-label="Cerrar el menú" class="flex size-10 items-center justify-center rounded-control text-menu-tinta hover:bg-menu-activo lg:hidden">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>
            </button>
        </div>

        @foreach ($enlaces as [$ruta, $patron, $texto, $icono, $visible, $navegar])
            <x-nav-link :href="route($ruta)" :active="request()->routeIs($patron)" :wire:navigate="$navegar">
                <span class="flex items-center gap-2.5">
                    <svg width="17" height="17" viewBox="0 0 20 20" aria-hidden="true" class="shrink-0 opacity-80">
                        @switch($icono)
                            @case('panel')<path d="M3 3h6v8H3zM11 3h6v5h-6zM11 10h6v7h-6zM3 13h6v4H3z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path>@break
                            @case('bandeja')<path d="M3 11.5 5 4h10l2 7.5V16H3Zm0 0h4.5l1 2h3l1-2H17" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path>@break
                            @case('visitas')<path d="M12.5 4.5a3.5 3.5 0 0 0-4.6 4.4L3.5 13.3a1.6 1.6 0 0 0 2.2 2.2l4.4-4.4a3.5 3.5 0 0 0 4.4-4.6l-2 2-2-.4-.4-2Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path>@break
                            @case('ruta')<path d="M10 17.5s5.5-5 5.5-9.5a5.5 5.5 0 0 0-11 0c0 4.5 5.5 9.5 5.5 9.5Z" fill="none" stroke="currentColor" stroke-width="1.5"></path><circle cx="10" cy="8" r="2" fill="none" stroke="currentColor" stroke-width="1.5"></circle>@break
                            @case('clientes')<circle cx="7.5" cy="7" r="2.8" fill="none" stroke="currentColor" stroke-width="1.5"></circle><path d="M2.5 16c.6-2.8 2.6-4.3 5-4.3s4.4 1.5 5 4.3M13 4.6a2.6 2.6 0 0 1 0 5M14.5 11.9c1.6.5 2.6 1.9 3 4.1" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>@break
                            @case('informes')<path d="M5 2.5h7l3 3v12H5Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path><path d="M8 14v-3M10.5 14V9M13 14v-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>@break
                            @case('inventario')<path d="M3 6.5 10 3l7 3.5v7L10 17l-7-3.5Z M3 6.5 10 10l7-3.5M10 10v7" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path>@break
                            @default<circle cx="10" cy="7" r="3" fill="none" stroke="currentColor" stroke-width="1.5"></circle><path d="M4 17c.7-3 3-4.5 6-4.5s5.3 1.5 6 4.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                        @endswitch
                    </svg>
                    {{ $texto }}
                </span>
            </x-nav-link>
        @endforeach

        <div class="flex-1"></div>

        {{-- Tema: se aplica al momento y se recuerda en la cuenta. --}}
        <div x-data="selectorTema(@js(route('preferencias.tema')))" role="radiogroup" aria-label="Tema de la interfaz"
            class="mx-1 mb-2 grid grid-cols-3 gap-0.5 rounded-tarjeta border border-menu-borde bg-black/20 p-[3px]">
            @foreach (['claro' => 'Claro', 'oscuro' => 'Oscuro', 'sistema' => 'Sistema'] as $valor => $nombre)
                <button type="button" role="radio" x-on:click="elegir(@js($valor))" :aria-checked="tema === @js($valor)"
                    :class="tema === @js($valor) ? 'bg-menu-activo text-white font-semibold' : 'text-menu-tinta hover:text-white'"
                    class="flex h-8 items-center justify-center gap-1 rounded-control text-xs">
                    <svg width="13" height="13" viewBox="0 0 16 16" aria-hidden="true">
                        @if ($valor === 'claro')
                            <circle cx="8" cy="8" r="3" fill="none" stroke="currentColor" stroke-width="1.5"></circle><path d="M8 1v2M8 13v2M1 8h2M13 8h2M3 3l1.4 1.4M11.6 11.6 13 13M3 13l1.4-1.4M11.6 4.4 13 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                        @elseif ($valor === 'oscuro')
                            <path d="M13.5 10.2A6 6 0 0 1 5.8 2.5a6 6 0 1 0 7.7 7.7Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"></path>
                        @else
                            <rect x="1.5" y="2.5" width="13" height="9" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.5"></rect><path d="M5.5 14.5h5M8 11.5v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                        @endif
                    </svg>
                    {{ $nombre }}
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-2.5 border-t border-menu-borde px-2 pt-3">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-menu-activo text-xs font-semibold text-white" aria-hidden="true">{{ $iniciales }}</span>
            <div class="flex min-w-0 flex-col">
                <span class="truncate text-[13px] text-white" x-data="{{ json_encode(['name' => $usuario->name]) }}" x-text="name"
                    x-on:profile-updated.window="name = $event.detail.name">{{ $usuario->name }}</span>
                <span class="truncate text-xs text-menu-tinta">{{ $usuario->etiquetaRol() }}</span>
            </div>
        </div>
        <div class="flex gap-1 px-1 pt-2">
            <a href="{{ route('profile') }}" wire:navigate class="flex h-9 flex-1 items-center justify-center rounded-control text-[13px] text-menu-tinta no-underline hover:bg-menu-activo hover:text-white">{{ __('Profile') }}</a>
            <button type="button" wire:click="logout" class="flex h-9 flex-1 items-center justify-center rounded-control text-[13px] text-menu-tinta hover:bg-menu-activo hover:text-white">{{ __('Log Out') }}</button>
        </div>
    </nav>
</div>
