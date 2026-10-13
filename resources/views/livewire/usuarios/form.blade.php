<?php

use App\Models\Tecnico;
use App\Models\User;
use App\Support\ModoDemo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public ?User $usuario = null;

    public string $name = '';
    public string $email = '';
    public string $rol = User::ROL_CALL_CENTER;
    public string $estado = 'activo';
    public string $password = '';
    public string $password_confirmation = '';
    public string $telefono = '';

    public function mount(?User $usuario = null): void
    {
        $usuario?->exists
            ? $this->authorize('update', $usuario)
            : $this->authorize('create', User::class);

        if ($usuario?->exists) {
            $this->usuario = $usuario;

            $this->fill([
                'name' => $usuario->name,
                'email' => $usuario->email,
                'rol' => $usuario->rol,
                'estado' => $usuario->estado,
                'telefono' => (string) $usuario->tecnico?->telefono,
            ]);
        }
    }

    public function guardar()
    {
        // mount() solo corre en la primera carga: cada acción vuelve a preguntar.
        $this->usuario
            ? $this->authorize('update', $this->usuario)
            : $this->authorize('create', User::class);

        $reglas = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->usuario)],
            'rol' => ['required', Rule::in(array_keys(User::ROLES))],
            'estado' => ['required', Rule::in(['activo', 'inactivo'])],
            'telefono' => ['nullable', 'string', 'max:30'],
        ];

        // Al crear la contraseña es obligatoria; al editar solo si se escribe.
        $reglas['password'] = $this->usuario
            ? ['nullable', 'confirmed', Password::min(8)]
            : ['required', 'confirmed', Password::min(8)];

        $datos = $this->validate($reglas, [], [
            'name' => 'nombre',
            'email' => 'correo',
            'password' => 'contraseña',
        ]);

        $this->protegerAdministracion($datos);
        $this->protegerCuentaDemo($datos);

        $atributos = [
            'name' => $datos['name'],
            'email' => $datos['email'],
            'rol' => $datos['rol'],
            'estado' => $datos['estado'],
        ];

        if ($datos['password'] ?? false) {
            $atributos['password'] = Hash::make($datos['password']);
        }

        $usuario = $this->usuario
            ? tap($this->usuario)->update($atributos)
            : User::create($atributos + ['email_verified_at' => now()]);

        $this->sincronizarPerfilTecnico($usuario);

        session()->flash('mensaje', $this->usuario ? 'Usuario actualizado.' : 'Usuario creado.');

        return $this->redirect(route('usuarios.index'), navigate: true);
    }

    /**
     * Nadie se quita a sí mismo la administración, y el sistema nunca se queda
     * sin un administrador activo: sin él no hay quien cree ni reactive cuentas.
     */
    private function protegerAdministracion(array $datos): void
    {
        if (! $this->usuario?->esAdmin()) {
            return;
        }

        $dejaDeSerAdmin = $datos['rol'] !== User::ROL_ADMIN || $datos['estado'] !== 'activo';

        if (! $dejaDeSerAdmin) {
            return;
        }

        if ($this->usuario->is(auth()->user())) {
            throw ValidationException::withMessages([
                'rol' => 'No puedes quitarte el rol de administrador ni desactivar tu propia cuenta.',
            ]);
        }

        $quedanOtros = User::where('rol', User::ROL_ADMIN)
            ->where('estado', 'activo')
            ->whereKeyNot($this->usuario->id)
            ->exists();

        if (! $quedanOtros) {
            throw ValidationException::withMessages([
                'rol' => 'Es el último administrador activo: el sistema no puede quedarse sin uno.',
            ]);
        }
    }

    /** En la demo, las cuentas compartidas conservan su correo y su clave. */
    private function protegerCuentaDemo(array $datos): void
    {
        if (! $this->usuario || ! ModoDemo::protege($this->usuario)) {
            return;
        }

        if ($datos['email'] !== $this->usuario->email || filled($datos['password'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => 'En la demo no se puede cambiar el correo ni la contraseña de las cuentas de prueba.',
            ]);
        }
    }

    /**
     * Un técnico de campo necesita fila propia en 'tecnicos' para poder recibir
     * visitas. Se crea y se retira sola según el rol, sin borrar historial.
     */
    private function sincronizarPerfilTecnico(User $usuario): void
    {
        if ($usuario->rol === User::ROL_TECNICO_CAMPO) {
            Tecnico::updateOrCreate(
                ['user_id' => $usuario->id],
                [
                    'nombre' => $usuario->name,
                    'telefono' => $this->telefono ?: null,
                    'estado' => $usuario->estado,
                ],
            );

            return;
        }

        Tecnico::where('user_id', $usuario->id)->update(['estado' => 'inactivo']);
    }
}; ?>

<div class="mx-auto flex max-w-[680px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
    <x-encabezado :titulo="$usuario ? 'Editar usuario' : 'Nuevo usuario'">
        <x-slot:antes>
            <a href="{{ route('usuarios.index') }}" wire:navigate class="inline-flex items-center gap-1 text-tinta-3 no-underline hover:text-acento-texto">
                <svg width="13" height="13" viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                Usuarios
            </a>
        </x-slot:antes>
    </x-encabezado>

    <form wire:submit="guardar" class="flex flex-col gap-4">

        <section class="tarjeta grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
            <h2 class="titulo-seccion sm:col-span-2">Cuenta</h2>

            <div class="sm:col-span-2">
                <label for="name" class="etiqueta">Nombre completo</label>
                <input id="name" type="text" wire:model="name" class="control h-10">
                @error('name') <p class="error-campo">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="etiqueta">Correo</label>
                <input id="email" type="email" wire:model="email" class="control h-10">
                @error('email') <p class="error-campo">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="estado" class="etiqueta">Estado</label>
                <select id="estado" wire:model="estado" class="control h-10 py-0">
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label for="rol" class="etiqueta">Rol</label>
                <select id="rol" wire:model.live="rol" class="control h-10 py-0">
                    @foreach (\App\Models\User::ROLES as $clave => $etiqueta)
                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
                @error('rol') <p class="error-campo">{{ $message }}</p> @enderror

                <p class="mt-2 rounded-control bg-elevada px-3 py-2 text-[13px] text-tinta-2">
                    @switch($rol)
                        @case('call_center') Registra los casos que no se resolvieron en caja, teléfono o WhatsApp. @break
                        @case('tecnico_soporte') Atiende los casos en remoto. Es el dueño del reloj de SLA. @break
                        @case('ingeniero_redes') Recibe los casos escalados a nivel 3. @break
                        @case('tecnico_campo') Ejecuta las visitas. Solo ve las suyas. @break
                        @case('gerente') Consulta el panel y la operación completa. @break
                        @case('admin') Acceso total, incluida esta pantalla. @break
                    @endswitch
                </p>
            </div>

            @if ($rol === 'tecnico_campo')
                <div class="sm:col-span-2">
                    <label for="telefono" class="etiqueta">Teléfono del técnico</label>
                    <input id="telefono" type="text" wire:model="telefono" class="control h-10 font-mono" inputmode="tel">
                    <p class="mt-1 text-xs text-tinta-3">Se crea automáticamente su perfil para poder asignarle visitas.</p>
                    @error('telefono') <p class="error-campo">{{ $message }}</p> @enderror
                </div>
            @endif
        </section>

        <section class="tarjeta grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
            <div class="sm:col-span-2">
                <h2 class="titulo-seccion">Contraseña</h2>
                @if ($usuario)
                    <p class="mt-1 text-xs text-tinta-3">Déjala en blanco para no cambiarla.</p>
                @endif
            </div>

            <div>
                <label for="password" class="etiqueta">Contraseña</label>
                <input id="password" type="password" wire:model="password" class="control h-10" autocomplete="new-password">
                @error('password') <p class="error-campo">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password-confirmation" class="etiqueta">Confirmar contraseña</label>
                <input id="password-confirmation" type="password" wire:model="password_confirmation" class="control h-10" autocomplete="new-password">
            </div>
        </section>

        <div class="flex gap-2">
            <x-boton variante="primario" type="submit">
                {{ $usuario ? 'Guardar cambios' : 'Crear usuario' }}
            </x-boton>
            <x-boton variante="fantasma" :href="route('usuarios.index')" wire:navigate>Cancelar</x-boton>
        </div>
    </form>
</div>
