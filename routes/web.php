<?php

use App\Http\Controllers\CierreVisitaController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::middleware(['auth'])->group(function () {
    Volt::route('dashboard', 'dashboard.index')
        ->middleware('verified')
        ->name('dashboard');

    Route::view('profile', 'profile')->name('profile');

    // Gestión de casos: primer nivel, nivel 2, redes y dirección.
    Route::middleware('role:admin,gerente,call_center,tecnico_soporte,ingeniero_redes')->group(function () {
        Volt::route('/soportes', 'soportes.index')->name('soportes.index');
        Volt::route('/soportes/crear', 'soportes.create')->name('soportes.create');
        Volt::route('/soportes/{soporte}', 'soportes.show')->name('soportes.show');

        // Maestro de clientes. Las rutas literales van antes que las de parámetro.
        Volt::route('/clientes', 'clientes.index')->name('clientes.index');
        Volt::route('/clientes/crear', 'clientes.form')->name('clientes.create');
        Volt::route('/clientes/{cliente}/editar', 'clientes.form')->name('clientes.edit');
        Volt::route('/clientes/recurrentes', 'clientes.recurrentes')->name('clientes.recurrentes');
        Volt::route('/clientes/{cliente}', 'clientes.show')->name('clientes.show');
    });

    // Informes de rendimiento: la gestión los escribe, el técnico lee el suyo.
    Route::middleware('role:admin,gerente,tecnico_soporte')->group(function () {
        Volt::route('/informes', 'informes.index')->name('informes.index');
        Volt::route('/informes/{informe}', 'informes.show')->name('informes.show');
    });

    // Órdenes de trabajo: incluye al técnico de campo, que solo ve las suyas.
    Route::middleware('role:admin,gerente,call_center,tecnico_soporte,ingeniero_redes,tecnico_campo')->group(function () {
        Volt::route('/ordenes', 'ordenes.index')->name('ordenes.index');
        Volt::route('/ordenes/{orden}', 'ordenes.show')->name('ordenes.show');
    });

    // Pantalla de campo. Va aparte de /ordenes porque el técnico la usa con una
    // mano, de pie y con guantes: otra maquetación, otro layout y un cierre que
    // puede quedarse guardado en el teléfono hasta que vuelva la señal.
    Route::middleware('role:admin,gerente,tecnico_campo')->group(function () {
        Volt::route('/campo', 'campo.ruta')->name('campo.ruta');
        Volt::route('/campo/visitas/{orden}', 'campo.visita')->name('campo.visita');

        // Estas dos las llama el JavaScript de la cola, no un formulario.
        Route::post('/campo/visitas/{orden}/inicio', [CierreVisitaController::class, 'iniciar'])
            ->name('campo.iniciar');
        Route::post('/campo/visitas/{orden}/cierre', [CierreVisitaController::class, 'cerrar'])
            ->name('campo.cerrar');
    });

    // Bodega.
    Route::middleware('role:admin,gerente')->group(function () {
        Volt::route('/inventario', 'inventario.index')->name('inventario.index');
        Volt::route('/inventario/{material}', 'inventario.show')->name('inventario.show');
    });

    // Administración del equipo.
    Route::middleware('role:admin')->group(function () {
        Volt::route('/usuarios', 'usuarios.index')->name('usuarios.index');
        Volt::route('/usuarios/crear', 'usuarios.form')->name('usuarios.create');
        Volt::route('/usuarios/{usuario}/editar', 'usuarios.form')->name('usuarios.edit');
    });
});

require __DIR__ . '/auth.php';
