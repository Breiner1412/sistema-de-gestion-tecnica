<?php

namespace App\Providers;

use App\Models\MaterialOrden;
use App\Observers\MaterialOrdenObserver;
use App\Support\ModoDemo;
use App\Support\PerfilDeConsultas;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        MaterialOrden::observe(MaterialOrdenObserver::class);

        ModoDemo::aplicar();

        // Los enlaces que se arman sin petición delante (los correos que manda
        // la cola, los comandos) salen de APP_URL: si es https, todo es https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceHttps();
        }

        // Solo hace algo con PERFIL_CONSULTAS=true; apagado, ni se engancha.
        PerfilDeConsultas::escuchar();
    }
}
