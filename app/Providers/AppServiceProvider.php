<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\MaterialOrden;
use App\Observers\MaterialOrdenObserver;

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
    }
}
