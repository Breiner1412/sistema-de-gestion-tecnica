<?php

use App\Models\Tecnico;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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
        abort_unless(auth()->user()->esAdmin(), 403);

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
        $reglas = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->usuario)],
            'rol' => [Rule::in(array_keys(User::ROLES))],
            'estado' => [Rule::in(['activo', 'inactivo'])],
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

<div class="p-6 max-w-2xl">
    <a href="{{ route('usuarios.index') }}" wire:navigate class="text-blue-600 text-sm">&larr; Volver a usuarios</a>

    <h1 class="text-2xl font-bold mt-2 mb-4 text-slate-800">
        {{ $usuario ? 'Editar usuario' : 'Nuevo usuario' }}
    </h1>

    <form wire:submit="guardar" class="space-y-5">

        <div class="bg-white border rounded p-4 grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="block font-medium mb-1 text-slate-800">Nombre completo</label>
                <input type="text" wire:model="name" class="w-full border rounded p-2">
                @error('name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block font-medium mb-1 text-slate-800">Correo</label>
                <input type="email" wire:model="email" class="w-full border rounded p-2">
                @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block font-medium mb-1 text-slate-800">Estado</label>
                <select wire:model="estado" class="w-full border rounded p-2">
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block font-medium mb-1 text-slate-800">Rol</label>
                <select wire:model.live="rol" class="w-full border rounded p-2">
                    @foreach (\App\Models\User::ROLES as $clave => $etiqueta)
                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
                @error('rol') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror

                <p class="text-xs text-slate-500 mt-2">
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
                <div class="md:col-span-2">
                    <label class="block font-medium mb-1 text-slate-800">Teléfono del técnico</label>
                    <input type="text" wire:model="telefono" class="w-full border rounded p-2">
                    <p class="text-xs text-slate-500 mt-1">Se crea automáticamente su perfil para poder asignarle visitas.</p>
                    @error('telefono') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
            @endif
        </div>

        <div class="bg-white border rounded p-4 grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <h2 class="font-semibold text-slate-800">Contraseña</h2>
                @if ($usuario)
                    <p class="text-xs text-slate-500">Déjala en blanco para no cambiarla.</p>
                @endif
            </div>

            <div>
                <label class="block font-medium mb-1 text-slate-800">Contraseña</label>
                <input type="password" wire:model="password" class="w-full border rounded p-2" autocomplete="new-password">
                @error('password') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block font-medium mb-1 text-slate-800">Confirmar contraseña</label>
                <input type="password" wire:model="password_confirmation" class="w-full border rounded p-2" autocomplete="new-password">
            </div>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">
                {{ $usuario ? 'Guardar cambios' : 'Crear usuario' }}
            </button>
            <a href="{{ route('usuarios.index') }}" wire:navigate class="px-4 py-2 rounded border">Cancelar</a>
        </div>
    </form>
</div>
