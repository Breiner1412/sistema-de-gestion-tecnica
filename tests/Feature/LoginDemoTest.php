<?php

use App\Models\User;

beforeEach(function () {
    config(['demo.password' => 'clave-demo-de-prueba']);

    foreach (['gerencia@sgt.local' => User::ROL_GERENTE, 'campo1@sgt.local' => User::ROL_TECNICO_CAMPO, 'jefe@sgt.local' => User::ROL_ADMIN] as $email => $rol) {
        User::create([
            'name' => "Cuenta {$rol}", 'email' => $email, 'password' => 'clave-demo-de-prueba',
            'rol' => $rol, 'estado' => 'activo',
        ]);
    }
});

it('en modo demo el login publica las cuentas de prueba y la clave', function () {
    config(['demo.activo' => true]);

    $this->get('/login')
        ->assertOk()
        ->assertSee('Demo pública')
        ->assertSee('gerencia@sgt.local')
        ->assertSee('campo1@sgt.local')
        ->assertSee('clave-demo-de-prueba');
});

it('el login nunca publica una cuenta de administrador', function () {
    config(['demo.activo' => true, 'demo.cuentas_publicas' => ['jefe@sgt.local', 'gerencia@sgt.local']]);

    $this->get('/login')
        ->assertOk()
        ->assertSee('gerencia@sgt.local')
        ->assertDontSee('jefe@sgt.local');
});

it('fuera del modo demo el login no muestra cuentas ni claves', function () {
    config(['demo.activo' => false]);

    $this->get('/login')
        ->assertOk()
        ->assertDontSee('gerencia@sgt.local')
        ->assertDontSee('clave-demo-de-prueba');
});

it('las cuentas publicadas sirven para entrar', function () {
    config(['demo.activo' => true]);

    \Livewire\Volt\Volt::test('pages.auth.login')
        ->set('form.email', 'campo1@sgt.local')
        ->set('form.password', 'clave-demo-de-prueba')
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticated();
});
