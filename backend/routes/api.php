<?php

use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BlockTimeController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessHourController;
use App\Http\Controllers\admin\CategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\EmployeeController;
use \App\Http\Controllers\FinanceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\PublicHomeController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// 1. Ruta para obtener el usuario
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user()->load('business');
});
/*
|--------------------------------------------------------------------------
| Rutas Públicas (Sin Autenticación)
|--------------------------------------------------------------------------
*/
// Aquí van tus rutas de /login y /register que depuramos antes.

Route::post('/appointments', [AppointmentController::class, 'store']);

Route::get('/business/{businessId}/notifications', [NotificationController::class, 'index']);
Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

// CRUD de Categorias
Route::apiResource('categories', CategoryController::class);

// --- Rutas Públicas de GetBela ---
Route::prefix('public')->group(function () {
    // Listado de servicios (soporta filtros por category_id y búsqueda)
    Route::get('/services', [PublicHomeController::class, 'index']);
    
    // Listado de categorías (para las tarjetas/filtros inferiores)
    Route::get('/categories', [PublicHomeController::class, 'getCategories']);
    
    // Listado de banners publicitarios (para el hero principal a ancho completo)
    Route::get('/banners', [PublicHomeController::class, 'getBanners']);

    Route::get('/services/{id}', [ServiceController::class, 'show']);

    // Ruta para obtener los datos del perfil público de un negocio o estilista
    Route::get('/business/{id}', [BusinessController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Rutas Protegidas (Requieren Login General)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    
    // ====================================================================
    //  1. ZONA EXCLUSIVA DEL DUEÑO (role:owner)
    // ====================================================================
    Route::middleware('role:owner')->group(function () {
        
        // CRUD de Empleados / Estilistas del Salón (El dueño contrata/despide)
        Route::apiResource('employees', EmployeeController::class);

        // CRUD de Servicios (El dueño define precios y tiempos de peluquería/estética)
        Route::apiResource('services', ServiceController::class)->except(['show']);

        // Gestión de Horarios de Atención (Configuración global del local)
        Route::get('/business-hours', [BusinessHourController::class, 'index']);
        Route::put('/business-hours', [BusinessHourController::class, 'updateWeeklyHours']);

        // Módulo Financiero e Informes de Rendimiento (Sensible: solo gerencia)
        Route::get('/finances/dashboard', [FinanceController::class, 'getDashboard']);

        // Eliminación de productos o ajustes severos de inventario
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);

        Route::post('/business/update', [BusinessController::class, 'update']);
    });


    // ====================================================================
    //  2. ZONA COMPARTIDA: OPERACIÓN DEL SALÓN (role:owner,employee)
    // ====================================================================
    Route::middleware('role:owner,employee')->group(function () {

        // Módulo de Ventas de Vitrina (Si el estilista vende una cera, champú, etc.)
        Route::get('/sales', [SaleController::class, 'index']);
        Route::post('/sales', [SaleController::class, 'store']);

        // Módulo de Bloqueos Manuales de Agenda (Para cuando van a almorzar o descansar)
        Route::apiResource('block-times', BlockTimeController::class)->except(['update']);

        // Catálogo de clientes de Bela (Para que el estilista busque la ficha técnica o el historial del cliente)
        Route::get('/clients', [ClientController::class, 'index']);

        // Motor de Citas básico (Todos necesitan interactuar con el flujo de citas)
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
        
        // Control de tiempo en vivo de las Citas (Estatus de ejecución)
        Route::patch('/appointments/{id}/start', [AppointmentController::class, 'startAppointment']);
        Route::patch('/appointments/{id}/finish', [AppointmentController::class, 'finishAppointment']);
        Route::patch('/appointments/{id}/add-extra', [AppointmentController::class, 'addExtraProduct']);

        // Inventario de Productos (Stock) - Ver y ajustar lo diario
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::patch('/products/{id}/stock', [ProductController::class, 'adjustStock']);
    });

});