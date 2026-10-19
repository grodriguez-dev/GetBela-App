<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlockTimeController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessHourController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PublicHomeController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\admin\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user()->load('business');
});

Route::post('/appointments', [AppointmentController::class, 'store']);

Route::get('/business/{businessId}/notifications', [NotificationController::class, 'index']);
Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::apiResource('categories', CategoryController::class);

Route::prefix('public')->group(function () {
    Route::get('/services', [PublicHomeController::class, 'index']);
    Route::get('/categories', [PublicHomeController::class, 'getCategories']);
    Route::get('/banners', [PublicHomeController::class, 'getBanners']);
    Route::get('/contacts', [PublicHomeController::class, 'getContacts']);
    Route::get('/services/{id}', [ServiceController::class, 'show']);
    Route::get('/business/{id}', [BusinessController::class, 'show']);
    Route::get('/appointments/search', [AppointmentController::class, 'searchByPhone']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::apiResource('categories', \App\Http\Controllers\admin\CategoryController::class);
        Route::apiResource('banners', \App\Http\Controllers\admin\BannerController::class);
        Route::apiResource('contacts', \App\Http\Controllers\admin\ContactController::class);
    });

    Route::middleware('role:owner')->group(function () {
        Route::apiResource('employees', EmployeeController::class);
        Route::apiResource('services', ServiceController::class)->except(['show']);
        Route::get('/business-hours', [BusinessHourController::class, 'index']);
        Route::put('/business-hours', [BusinessHourController::class, 'updateWeeklyHours']);
        Route::get('/finances/dashboard', [FinanceController::class, 'getDashboard']);
        Route::get('/settlements', [SettlementController::class, 'index']);
        Route::get('/settlements/preview', [SettlementController::class, 'preview']);
        Route::post('/settlements', [SettlementController::class, 'store']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        Route::post('/business/update', [BusinessController::class, 'update']);
    });

    Route::middleware('role:owner,employee')->group(function () {
        Route::get('/sales', [SaleController::class, 'index']);
        Route::post('/sales', [SaleController::class, 'store']);
        Route::apiResource('block-times', BlockTimeController::class)->except(['update']);
        Route::get('/clients', [ClientController::class, 'index']);
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
        Route::patch('/appointments/{id}/start', [AppointmentController::class, 'startAppointment']);
        Route::patch('/appointments/{id}/finish', [AppointmentController::class, 'finishAppointment']);
        Route::patch('/appointments/{id}/add-extra', [AppointmentController::class, 'addExtraProduct']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::patch('/products/{id}/stock', [ProductController::class, 'adjustStock']);
        Route::apiResource('services', ServiceController::class)->except(['show']);
        Route::get('/services/{id}', [ServiceController::class, 'show']);
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::get('/categories', [\App\Http\Controllers\admin\CategoryController::class, 'index']);
    });
});
