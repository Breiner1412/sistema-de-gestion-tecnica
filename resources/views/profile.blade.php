<x-app-layout>
    <div class="mx-auto flex max-w-[760px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
        <x-encabezado :titulo="__('Profile')" :subtitulo="auth()->user()->email" />

        @if (\App\Support\ModoDemo::protege(auth()->user()))
            <div class="flex items-start gap-3 rounded-tarjeta border border-linea bg-elevada p-4 text-sm text-tinta-2">
                <svg class="mt-0.5 shrink-0" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="3.5" y="7" width="9" height="6.5" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"></rect><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2" fill="none" stroke="currentColor" stroke-width="1.5"></path></svg>
                <p>
                    Esta es una cuenta compartida de la demo: su nombre, su correo y su contraseña
                    no se pueden cambiar, y la cuenta no se puede borrar.
                </p>
            </div>
        @else
            <div class="tarjeta p-4 sm:p-6">
                <livewire:profile.update-profile-information-form />
            </div>

            <div class="tarjeta p-4 sm:p-6">
                <livewire:profile.update-password-form />
            </div>

            <div class="tarjeta border-peligro-borde p-4 sm:p-6">
                <livewire:profile.delete-user-form />
            </div>
        @endif
    </div>
</x-app-layout>
