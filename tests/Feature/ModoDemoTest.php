<?php

use App\Models\User;
use App\Support\ModoDemo;

it('en modo demo el correo va al registro aunque el .env diga smtp', function () {
    config(['demo.activo' => true, 'mail.default' => 'smtp']);

    ModoDemo::aplicar();

    expect(config('mail.default'))->toBe('log');
});

it('fuera del modo demo no toca el correo', function () {
    config(['demo.activo' => false, 'mail.default' => 'smtp']);

    ModoDemo::aplicar();

    expect(config('mail.default'))->toBe('smtp');
});

it('reconoce como semilla las cuentas del dominio demo y el admin del .env', function () {
    config(['cuentas.admin.email' => 'Jefe@Ejemplo.test']);

    $demo = new User(['email' => 'gerencia@sgt.local']);
    $admin = new User(['email' => 'jefe@ejemplo.test']);
    $otra = new User(['email' => 'alguien@otro.test']);

    expect(ModoDemo::esCuentaSemilla($demo))->toBeTrue()
        ->and(ModoDemo::esCuentaSemilla($admin))->toBeTrue()
        ->and(ModoDemo::esCuentaSemilla($otra))->toBeFalse();
});

it('solo protege las cuentas semilla cuando el modo demo está activo', function () {
    $demo = new User(['email' => 'gerencia@sgt.local']);

    config(['demo.activo' => false]);
    expect(ModoDemo::protege($demo))->toBeFalse();

    config(['demo.activo' => true]);
    expect(ModoDemo::protege($demo))->toBeTrue();
});

it('en producción APP_DEBUG se apaga aunque el .env diga true', function () {
    config(['app.debug' => true]);
    app()->detectEnvironment(fn () => 'production');

    (new \App\Providers\AppServiceProvider(app()))->boot();

    expect(config('app.debug'))->toBeFalse();
});

it('el ejemplo de producción no trae valores de desarrollo', function () {
    $ejemplo = file_get_contents(base_path('.env.production.example'));

    expect($ejemplo)->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->toContain('SESSION_SECURE_COOKIE=true')
        ->not->toContain('DB_USERNAME=root')
        ->not->toContain('LOG_LEVEL=debug');
});
