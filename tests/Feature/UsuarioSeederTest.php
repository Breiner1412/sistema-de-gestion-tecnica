<?php

use App\Models\User;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Support\Facades\Hash;

it('crea el administrador con el correo y la clave del .env', function () {
    config([
        'cuentas.admin.email' => 'jefe@ejemplo.test',
        'cuentas.admin.password' => 'una-clave-larga-del-env',
    ]);

    $this->seed(UsuarioSeeder::class);

    $admin = User::where('email', 'jefe@ejemplo.test')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->rol)->toBe(User::ROL_ADMIN)
        ->and(Hash::check('una-clave-larga-del-env', $admin->password))->toBeTrue();
});

it('no crea ningún administrador si falta el .env', function () {
    config(['cuentas.admin.email' => null, 'cuentas.admin.password' => null]);

    $this->seed(UsuarioSeeder::class);

    expect(User::where('rol', User::ROL_ADMIN)->count())->toBe(0);
});

it('no siembra correos reales: solo el dominio de prueba', function () {
    config(['cuentas.admin.email' => null, 'cuentas.admin.password' => null]);

    $this->seed(UsuarioSeeder::class);

    $ajenos = User::pluck('email')->reject(fn ($email) => str_ends_with($email, '@sgt.local'));

    expect($ajenos->all())->toBe([]);
});

it('rechaza una clave de admin corta en producción', function () {
    config([
        'cuentas.admin.email' => 'jefe@ejemplo.test',
        'cuentas.admin.password' => 'corta',
    ]);
    app()->detectEnvironment(fn () => 'production');

    // Se llama directo: el comando db:seed pediría confirmación en producción.
    expect(fn () => app(UsuarioSeeder::class)->run())
        ->toThrow(RuntimeException::class, 'ADMIN_PASSWORD');
});
