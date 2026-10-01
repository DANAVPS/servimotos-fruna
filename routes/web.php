<?php

use App\Http\Controllers\CategoriaRepuestoController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DistribuidorController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\OrdenServicioController;
use App\Http\Controllers\PinAutorizacionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TipoClienteController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ruta pública
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect()->route('dashboard');
});

/*
|--------------------------------------------------------------------------
| Webhook de WhatsApp (sin auth, Meta llama directamente)
|--------------------------------------------------------------------------
*/
Route::get('/webhook/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhook/whatsapp', [WhatsAppWebhookController::class, 'handle']);

/*
|--------------------------------------------------------------------------
| Dashboard (Breeze)
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Perfil de usuario (Breeze)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('/profile/pin', [PinAutorizacionController::class, 'update'])->name('profile.pin.update');
});

/*
|--------------------------------------------------------------------------
| Inventario
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('/inventario/buscar', [InventarioController::class, 'buscar'])->name('inventario.buscar');
    Route::get('/inventario/nuevo', [InventarioController::class, 'create'])->name('inventario.create');
    Route::post('/inventario', [InventarioController::class, 'store'])->name('inventario.store');
    Route::get('/inventario/{repuesto}/editar', [InventarioController::class, 'edit'])->name('inventario.edit');
    Route::put('/inventario/{repuesto}', [InventarioController::class, 'update'])->name('inventario.update');
    Route::delete('/inventario/{repuesto}', [InventarioController::class, 'destroy'])->name('inventario.destroy');
});

/*
|--------------------------------------------------------------------------
| Categorías de repuesto y Distribuidores (solo Administradora / SuperAdmin)
|--------------------------------------------------------------------------
| Doble candado: middleware de rol + Policy en cada acción.
*/
Route::middleware(['auth', 'role:administradora'])->group(function () {
    Route::get('/inventario/categorias', [CategoriaRepuestoController::class, 'index'])->name('categorias.index');
    Route::get('/inventario/categorias/nueva', [CategoriaRepuestoController::class, 'create'])->name('categorias.create');
    Route::post('/inventario/categorias', [CategoriaRepuestoController::class, 'store'])->name('categorias.store');
    Route::get('/inventario/categorias/{categoria}/editar', [CategoriaRepuestoController::class, 'edit'])->name('categorias.edit');
    Route::put('/inventario/categorias/{categoria}', [CategoriaRepuestoController::class, 'update'])->name('categorias.update');

    Route::get('/distribuidores', [DistribuidorController::class, 'index'])->name('distribuidores.index');
    Route::get('/distribuidores/nuevo', [DistribuidorController::class, 'create'])->name('distribuidores.create');
    Route::post('/distribuidores', [DistribuidorController::class, 'store'])->name('distribuidores.store');
    Route::get('/distribuidores/{distribuidor}/editar', [DistribuidorController::class, 'edit'])->name('distribuidores.edit');
    Route::put('/distribuidores/{distribuidor}', [DistribuidorController::class, 'update'])->name('distribuidores.update');

    Route::get('/distribuidores/{distribuidor}/solicitud', [DistribuidorController::class, 'solicitud'])->name('distribuidores.solicitud');
    Route::post('/distribuidores/{distribuidor}/solicitud/pdf', [DistribuidorController::class, 'solicitudPdf'])->name('distribuidores.solicitud.pdf');
    Route::post('/distribuidores/{distribuidor}/solicitud/whatsapp', [DistribuidorController::class, 'solicitudWhatsapp'])->name('distribuidores.solicitud.whatsapp');
});

/*
|--------------------------------------------------------------------------
| Órdenes de servicio (Kanban) y Facturación
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/ordenes', [OrdenServicioController::class, 'kanban'])->name('ordenes.kanban');
    Route::get('/ordenes/{orden}', [OrdenServicioController::class, 'show'])->name('ordenes.show');
    Route::patch('/ordenes/{orden}/estado', [OrdenServicioController::class, 'actualizarEstado'])->name('ordenes.estado');

    Route::patch('/ordenes/{orden}/pagar', [OrdenServicioController::class, 'marcarPagada'])
        ->middleware('role:administradora')
        ->name('ordenes.pagar');

    Route::get('/ordenes/{orden}/liquidar', [FacturaController::class, 'liquidar'])->name('facturas.liquidar');
    Route::post('/ordenes/{orden}/factura', [FacturaController::class, 'generar'])->name('facturas.generar');
    Route::get('/facturas/{orden}/pdf', [FacturaController::class, 'descargar'])->name('facturas.pdf');
});

/*
|--------------------------------------------------------------------------
| Clientes
|--------------------------------------------------------------------------
| Lectura: cualquier usuario del taller (el mecánico los necesita al crear
| órdenes). Gestión: solo Administradora / SuperAdmin. Policy en cada acción.
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/buscar', [ClienteController::class, 'buscar'])->name('clientes.buscar');
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->whereNumber('cliente')->name('clientes.show');
});

Route::middleware(['auth', 'role:administradora'])->group(function () {
    Route::get('/clientes/nuevo', [ClienteController::class, 'create'])->name('clientes.create');
    Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');

    // Editar, cambiar estado, motos y reinscribir exigen PIN de autorización.
    Route::middleware('pin')->group(function () {
        Route::get('/clientes/{cliente}/editar', [ClienteController::class, 'edit'])->name('clientes.edit');
        Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
        Route::post('/clientes/{cliente}/reinscribir', [ClienteController::class, 'reinscribir'])->name('clientes.reinscribir');
    });

    Route::get('/tipos-cliente', [TipoClienteController::class, 'index'])->name('tipos-cliente.index');
    Route::get('/tipos-cliente/nuevo', [TipoClienteController::class, 'create'])->name('tipos-cliente.create');
    Route::get('/tipos-cliente/analizar-descuento', [TipoClienteController::class, 'analizarDescuento'])->name('tipos-cliente.analizar');
    Route::post('/tipos-cliente', [TipoClienteController::class, 'store'])->name('tipos-cliente.store');
    Route::get('/tipos-cliente/{tipoCliente}/editar', [TipoClienteController::class, 'edit'])->name('tipos-cliente.edit');
    Route::put('/tipos-cliente/{tipoCliente}', [TipoClienteController::class, 'update'])->name('tipos-cliente.update');
});

require __DIR__.'/auth.php';
