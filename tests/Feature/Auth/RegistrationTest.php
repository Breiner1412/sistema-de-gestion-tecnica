<?php

namespace Tests\Feature\Auth;

use App\Models\User;

test('no hay pantalla de registro', function () {
    $this->get('/register')->assertNotFound();
});

test('no se puede crear una cuenta desde fuera', function () {
    $this->post('/register', [
        'name' => 'Visitante',
        'email' => 'visitante@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::where('email', 'visitante@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

test('la portada no enlaza al registro', function () {
    $this->get('/')->assertOk()->assertDontSee('/register');
});
