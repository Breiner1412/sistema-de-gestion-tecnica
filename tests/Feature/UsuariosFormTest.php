<?php

use App\Models\User;
use Livewire\Volt\Volt;

it('guardar exige ser admin aunque el formulario se abriera siéndolo', function () {
    $admin = usuarioCon(User::ROL_ADMIN);
    $this->actingAs($admin);

    $formulario = Volt::test('usuarios.form')
        ->set('name', 'Intruso')
        ->set('email', 'intruso@prueba.local')
        ->set('rol', User::ROL_ADMIN)
        ->set('password', 'una-clave-larga')
        ->set('password_confirmation', 'una-clave-larga');

    // Otra sesión le quita el rol mientras tiene el formulario abierto.
    $admin->update(['rol' => User::ROL_CALL_CENTER]);

    $formulario->call('guardar')->assertForbidden();

    expect(User::where('email', 'intruso@prueba.local')->exists())->toBeFalse();
});

it('el rol es obligatorio: no se guarda una cuenta sin rol', function () {
    $this->actingAs(usuarioCon(User::ROL_ADMIN));
    $otro = usuarioCon(User::ROL_CALL_CENTER);

    Volt::test('usuarios.form', ['usuario' => $otro])
        ->set('rol', '')
        ->call('guardar')
        ->assertHasErrors(['rol' => 'required']);

    expect($otro->refresh()->rol)->toBe(User::ROL_CALL_CENTER);
});

it('un admin no puede quitarse el rol a sí mismo', function () {
    $this->actingAs($admin = usuarioCon(User::ROL_ADMIN));
    usuarioCon(User::ROL_ADMIN); // hay otro, para que no sea el caso del último

    Volt::test('usuarios.form', ['usuario' => $admin])
        ->set('rol', User::ROL_GERENTE)
        ->call('guardar')
        ->assertHasErrors('rol');

    expect($admin->refresh()->rol)->toBe(User::ROL_ADMIN);
});

it('un admin no puede desactivarse a sí mismo', function () {
    $this->actingAs($admin = usuarioCon(User::ROL_ADMIN));

    Volt::test('usuarios.form', ['usuario' => $admin])
        ->set('estado', 'inactivo')
        ->call('guardar')
        ->assertHasErrors('rol');

    expect($admin->refresh()->estado)->toBe('activo');
});

it('no se degrada al último administrador activo', function () {
    $this->actingAs($actor = usuarioCon(User::ROL_ADMIN));
    $otro = usuarioCon(User::ROL_ADMIN);

    // Quien edita sigue con sesión, pero su cuenta ya no cuenta como admin
    // activo: $otro es el único que queda.
    $actor->update(['estado' => 'inactivo']);

    Volt::test('usuarios.form', ['usuario' => $otro])
        ->set('rol', User::ROL_GERENTE)
        ->call('guardar')
        ->assertHasErrors('rol');

    expect($otro->refresh()->rol)->toBe(User::ROL_ADMIN);
});

it('sí deja degradar a un admin cuando quedan otros activos', function () {
    $this->actingAs(usuarioCon(User::ROL_ADMIN));
    $otro = usuarioCon(User::ROL_ADMIN);

    Volt::test('usuarios.form', ['usuario' => $otro])
        ->set('rol', User::ROL_GERENTE)
        ->call('guardar')
        ->assertHasNoErrors();

    expect($otro->refresh()->rol)->toBe(User::ROL_GERENTE);
});

it('en modo demo el admin no cambia el correo ni la clave de una cuenta de prueba', function () {
    config(['demo.activo' => true]);
    $this->actingAs(usuarioCon(User::ROL_ADMIN));
    $demo = User::create([
        'name' => 'Técnico de campo 1', 'email' => 'campo1@sgt.local', 'password' => 'clave-demo',
        'rol' => User::ROL_TECNICO_CAMPO, 'estado' => 'activo',
    ]);

    Volt::test('usuarios.form', ['usuario' => $demo])
        ->set('password', 'otra-clave-nueva')
        ->set('password_confirmation', 'otra-clave-nueva')
        ->call('guardar')
        ->assertHasErrors('email');

    Volt::test('usuarios.form', ['usuario' => $demo])
        ->set('email', 'robada@otro.test')
        ->call('guardar')
        ->assertHasErrors('email');

    expect($demo->refresh()->email)->toBe('campo1@sgt.local');
});
