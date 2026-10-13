<?php

use App\Livewire\Forms\LoginForm;
use App\Support\ModoDemo;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    public function with(): array
    {
        return [
            'cuentasDemo' => ModoDemo::cuentasPublicas(),
            'claveDemo' => config('demo.password'),
            'horaReinicio' => config('demo.reinicio.hora'),
        ];
    }
}; ?>

<div>
    <h1 class="text-[22px] font-semibold leading-tight">Entrar</h1>
    <p class="mt-1 text-sm text-tinta-3">Mesa de soporte técnico: casos, visitas y SLA.</p>

    @if ($cuentasDemo->isNotEmpty() && filled($claveDemo))
        <section class="mt-5 rounded-tarjeta border border-acento/30 bg-acento-tinte p-4 text-sm" aria-labelledby="demo-titulo">
            <p id="demo-titulo" class="font-semibold text-acento-tinte-texto">Demo pública</p>
            <p class="mt-1 text-tinta-2">
                Entre con cualquiera de estas cuentas. La contraseña es
                <code class="rounded-[4px] bg-superficie px-1.5 py-0.5 font-mono text-[13px] text-tinta">{{ $claveDemo }}</code>.
            </p>

            <ul class="mt-3 flex flex-col gap-1">
                @foreach ($cuentasDemo as $cuenta)
                    <li>
                        <button type="button"
                                class="flex w-full flex-col items-start rounded-control px-2 py-1.5 text-left transition-colors hover:bg-superficie"
                                x-on:click="$wire.set('form.email', @js($cuenta->email)); $wire.set('form.password', @js($claveDemo))">
                            <span class="font-medium text-acento-texto">{{ $cuenta->etiquetaRol() }}</span>
                            <span class="font-mono text-xs text-tinta-3">{{ $cuenta->email }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>

            <p class="mt-3 border-t border-acento/20 pt-3 text-[13px] text-tinta-2">
                Los datos se reinician todas las noches a las {{ $horaReinicio }} (hora de Colombia):
                lo que haga aquí no dura más de un día, y a esa hora se cierran todas las sesiones.
            </p>
        </section>
    @endif

    <!-- Session Status -->
    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form wire:submit="login" class="mt-5 flex flex-col gap-4">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="block w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="form.password" id="password" class="block w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <label for="remember" class="inline-flex items-center gap-2 text-sm text-tinta-2">
            <input wire:model="form.remember" id="remember" type="checkbox" class="size-4 rounded border-linea bg-superficie text-acento focus:ring-acento-texto" name="remember">
            {{ __('Remember me') }}
        </label>

        <x-primary-button class="h-10 w-full">
            {{ __('Log in') }}
        </x-primary-button>

        @if (Route::has('password.request'))
            <a class="self-center text-sm" href="{{ route('password.request') }}" wire:navigate>
                {{ __('Forgot your password?') }}
            </a>
        @endif
    </form>
</div>
