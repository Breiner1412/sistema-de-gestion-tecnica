<?php

namespace App\Providers;

use App\Models\MaterialOrden;
use App\Observers\MaterialOrdenObserver;
use App\Support\PerfilDeConsultas;
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

        // Solo hace algo con PERFIL_CONSULTAS=true; apagado, ni se engancha.
        PerfilDeConsultas::escuchar();
    }
}
