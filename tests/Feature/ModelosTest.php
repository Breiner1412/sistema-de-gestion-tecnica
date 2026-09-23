<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Esta prueba nace de un error concreto.
 *
 * Eloquent deduce el nombre de la tabla del nombre de la clase, y con nombres
 * compuestos se equivoca: de `EquipoInstalado` saca `equipo_instalados`, porque
 * pluraliza solo la última palabra. La tabla se llama `equipos_instalados`.
 *
 * Lo malo no fue el error, fue cómo se manifestó: la ficha del abonado solo
 * consultaba esa tabla si el cliente tenía contratos, así que la mayoría de las
 * fichas abrían bien y unas pocas reventaban. Parecía lentitud —siete segundos—
 * y en realidad eran siete segundos de Laravel dibujando la página de error.
 *
 * Una vuelta por todos los modelos cuesta milisegundos y cierra la puerta a esa
 * clase entera de fallo, que siempre aparece tarde y disfrazada de otra cosa.
 */
it('cada modelo apunta a una tabla que existe', function () {
    $modelos = collect(glob(app_path('Models/*.php')))
        ->map(fn(string $ruta) => 'App\\Models\\'.basename($ruta, '.php'))
        ->filter(fn(string $clase) => class_exists($clase) && is_subclass_of($clase, Model::class));

    expect($modelos)->not->toBeEmpty();

    $rotos = $modelos
        ->reject(fn(string $clase) => Schema::hasTable((new $clase)->getTable()))
        ->map(fn(string $clase) => $clase.' → '.(new $clase)->getTable())
        ->values()
        ->all();

    expect($rotos)->toBe([]);
});
