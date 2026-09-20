<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\OrdenServicioController;
use App\Http\Controllers\ProfileController;
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
});

/*
|--------------------------------------------------------------------------
| Inventario
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('/inventario/nuevo', [InventarioController::class, 'create'])->name('inventario.create');
    Route::post('/inventario', [InventarioController::class, 'store'])->name('inventario.store');
    Route::get('/inventario/{repuesto}/editar', [InventarioController::class, 'edit'])->name('inventario.edit');
    Route::put('/inventario/{repuesto}', [InventarioController::class, 'update'])->name('inventario.update');
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
| Clientes (solo Administradora / SuperAdmin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:administradora'])->group(function () {
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/nuevo', [ClienteController::class, 'create'])->name('clientes.create');
    Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
    Route::get('/clientes/{cliente}/editar', [ClienteController::class, 'edit'])->name('clientes.edit');
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
    Route::post('/clientes/{cliente}/reinscribir', [ClienteController::class, 'reinscribir'])->name('clientes.reinscribir');
});

require __DIR__.'/auth.php';
