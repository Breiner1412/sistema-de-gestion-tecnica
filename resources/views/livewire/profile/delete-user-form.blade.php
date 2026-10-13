<?php

use App\Livewire\Actions\Logout;
use App\Support\ModoDemo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        // La cuenta es de todos los visitantes: borrarla rompe la demo al siguiente.
        abort_if(ModoDemo::protege(Auth::user()), 403, 'Las cuentas de la demo no se pueden borrar.');

        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        // Una cuenta con casos, visitas o informes a su nombre no se puede
        // borrar: la base lo impide para no perder el historial. Se avisa en
        // vez de reventar con un error 500; lo que procede es desactivarla.
        $usuario = Auth::user();

        try {
            DB::transaction(fn () => $usuario->delete());
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'password' => 'Tu cuenta tiene casos o informes a su nombre y no se puede borrar. Pide al administrador que la desactive.',
            ]);
        }

        // La fila ya no existe: sin esto, logout() renueva el token de
        // "recordarme" con un save() que volvería a insertar al usuario.
        $usuario->setRememberToken(null);
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-6">
    <header>
        <h2 class="text-base font-semibold text-tinta">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-tinta-3">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Delete Account') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">

            <h2 class="text-base font-semibold text-tinta">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1 text-sm text-tinta-3">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full sm:w-3/4"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Delete Account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
