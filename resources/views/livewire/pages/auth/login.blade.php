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
        ];
    }
}; ?>

<div>
    @if ($cuentasDemo->isNotEmpty() && filled($claveDemo))
        <div class="mb-6 rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
            <p class="font-semibold">Demo pública</p>
            <p class="mt-1">
                Entre con cualquiera de estas cuentas. La contraseña es
                <code class="rounded bg-white px-1 font-mono">{{ $claveDemo }}</code>.
            </p>

            <ul class="mt-3 space-y-1">
                @foreach ($cuentasDemo as $cuenta)
                    <li>
                        <button type="button"
                                class="text-left underline hover:text-blue-700"
                                x-on:click="$wire.set('form.email', @js($cuenta->email)); $wire.set('form.password', @js($claveDemo))">
                            {{ $cuenta->etiquetaRol() }}
                        </button>
                        <span class="text-blue-700">· {{ $cuenta->email }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</div>
