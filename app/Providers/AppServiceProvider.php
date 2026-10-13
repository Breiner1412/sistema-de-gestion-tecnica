<?php

namespace App\Providers;

use App\Models\MaterialOrden;
use App\Observers\MaterialOrdenObserver;
use App\Support\ModoDemo;
use App\Support\PerfilDeConsultas;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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

        $this->nuncaDepurarEnProduccion();

        ModoDemo::aplicar();

        // Los enlaces que se arman sin petición delante (los correos que manda
        // la cola, los comandos) salen de APP_URL: si es https, todo es https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceHttps();
        }

        $this->limitarPeticiones();

        Event::listen(QueueBusy::class, fn (QueueBusy $evento) => Log::error('Cola atascada', [
            'conexion' => $evento->connectionName,
            'cola' => $evento->queue,
            'pendientes' => $evento->size,
        ]));

        // Solo hace algo con PERFIL_CONSULTAS=true; apagado, ni se engancha.
        PerfilDeConsultas::escuchar();
    }

    /**
     * APP_DEBUG=true en producción muestra a cualquier visitante la traza de
     * un error, con rutas, consultas y variables. Si el .env lo trae por
     * descuido, se apaga igual y queda escrito en el registro.
     */
    private function nuncaDepurarEnProduccion(): void
    {
        if ($this->app->isProduction() && config('app.debug')) {
            config(['app.debug' => false]);
            Log::critical('APP_DEBUG=true en producción: se apagó. Corrige el .env.');
        }
    }

    /**
     * Límites por usuario (o por IP sin sesión) para lo que se puede martillar.
     * La IP es la real del visitante gracias a TRUSTED_PROXIES: sin eso, todos
     * los visitantes compartían el contador del proxy.
     */
    private function limitarPeticiones(): void
    {
        $quien = fn (Request $peticion) => $peticion->user()?->id ?? $peticion->ip();

        // La cola del celular: un envío por cierre, más algún reintento.
        RateLimiter::for('campo', fn (Request $peticion) => Limit::perMinute(30)->by('campo|'.$quien($peticion)));

        // Cada clic en una pantalla de Livewire es una petición: holgado para
        // una persona, corto para un script.
        RateLimiter::for('livewire', fn (Request $peticion) => Limit::perMinute(240)->by('livewire|'.$quien($peticion)));

        Livewire::setUpdateRoute(fn ($accion) => Route::post('/livewire/update', $accion)
            ->middleware(['web', 'throttle:livewire'])
            ->name('livewire.update'));
    }
}
