<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_prueba-proxy', fn () => request()->getScheme().'|'.request()->ip().'|'.url('/campo'));
});

it('detrás del proxy de confianza respeta https y la IP real del visitante', function () {
    config(['trustedproxy.proxies' => '172.30.0.2']);

    $this->withServerVariables(['REMOTE_ADDR' => '172.30.0.2'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '190.85.1.10', 'X-Forwarded-Port' => '443'])
        ->get('/_prueba-proxy')
        ->assertSee('https|190.85.1.10|https://');
});

it('no cree en las cabeceras de quien no es el proxy', function () {
    config(['trustedproxy.proxies' => '172.30.0.2']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '1.2.3.4'])
        ->get('/_prueba-proxy')
        ->assertSee('http|203.0.113.9|http://');
});

it('sin TRUSTED_PROXIES no confía en nadie', function () {
    config(['trustedproxy.proxies' => null]);

    $this->withServerVariables(['REMOTE_ADDR' => '172.30.0.2'])
        ->withHeaders(['X-Forwarded-For' => '1.2.3.4'])
        ->get('/_prueba-proxy')
        ->assertSee('|172.30.0.2|');
});
