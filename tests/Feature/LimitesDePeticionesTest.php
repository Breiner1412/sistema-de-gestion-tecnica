<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

it('la cola del celular tiene límite por usuario', function () {
    $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visita = visitaDePrueba($usuario);
    $this->actingAs($usuario);

    foreach (range(1, 30) as $i) {
        $this->postJson(route('campo.iniciar', $visita))->assertOk();
    }

    $this->postJson(route('campo.iniciar', $visita))->assertStatus(429);
});

it('el límite de un usuario no frena a otro', function () {
    $uno = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visitaUno = visitaDePrueba($uno);
    $otro = usuarioCon(User::ROL_TECNICO_CAMPO);
    $visitaOtro = visitaDePrueba($otro);

    foreach (range(1, 31) as $i) {
        $this->actingAs($uno)->postJson(route('campo.iniciar', $visitaUno));
    }

    $this->actingAs($otro)->postJson(route('campo.iniciar', $visitaOtro))->assertOk();
});

it('las peticiones de Livewire pasan por su límite', function () {
    $ruta = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($r) => $r->uri() === 'livewire/update' && in_array('POST', $r->methods(), true));

    expect($ruta)->not->toBeNull()
        ->and($ruta->gatherMiddleware())->toContain('throttle:livewire');
});
