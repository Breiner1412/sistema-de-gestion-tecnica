<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

function cuentaDemo(): User
{
    return User::create([
        'name' => 'Gerencia Técnica',
        'email' => 'gerencia@sgt.local',
        'password' => Hash::make('clave-demo'),
        'rol' => User::ROL_GERENTE,
        'estado' => 'activo',
        'email_verified_at' => now(),
    ]);
}

beforeEach(fn () => config(['demo.activo' => true]));

it('en modo demo una cuenta semilla no se puede borrar', function () {
    $this->actingAs($cuenta = cuentaDemo());

    Volt::test('profile.delete-user-form')
        ->set('password', 'clave-demo')
        ->call('deleteUser')
        ->assertForbidden();

    expect(User::find($cuenta->id))->not->toBeNull();
});

it('en modo demo una cuenta semilla no cambia su contraseña', function () {
    $this->actingAs($cuenta = cuentaDemo());

    Volt::test('profile.update-password-form')
        ->set('current_password', 'clave-demo')
        ->set('password', 'otra-clave-nueva')
        ->set('password_confirmation', 'otra-clave-nueva')
        ->call('updatePassword')
        ->assertForbidden();

    expect(Hash::check('clave-demo', $cuenta->refresh()->password))->toBeTrue();
});

it('en modo demo una cuenta semilla no cambia su correo ni su nombre', function () {
    $this->actingAs($cuenta = cuentaDemo());

    Volt::test('profile.update-profile-information-form')
        ->set('name', 'Otro nombre')
        ->set('email', 'secuestrada@otro.test')
        ->call('updateProfileInformation')
        ->assertForbidden();

    expect($cuenta->refresh()->email)->toBe('gerencia@sgt.local')
        ->and($cuenta->name)->toBe('Gerencia Técnica');
});

it('en modo demo el perfil de una cuenta semilla muestra el aviso y no los formularios', function () {
    $this->actingAs(cuentaDemo());

    $this->get('/profile')
        ->assertOk()
        ->assertSee('cuenta compartida de la demo')
        ->assertDontSeeVolt('profile.delete-user-form');
});

it('fuera del modo demo la cuenta sí se puede editar', function () {
    config(['demo.activo' => false]);
    $this->actingAs($cuenta = cuentaDemo());

    Volt::test('profile.update-profile-information-form')
        ->set('name', 'Gerencia renombrada')
        ->set('email', 'gerencia@sgt.local')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($cuenta->refresh()->name)->toBe('Gerencia renombrada');
});
