<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\PayMongoWebhookController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StudentOrderController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::get('/menu', [MenuController::class, 'index']);
Route::post('/webhooks/paymongo', PayMongoWebhookController::class)->middleware('throttle:120,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware('role:student')->group(function (): void {
        Route::get('/student/orders', [StudentOrderController::class, 'index']);
        Route::post('/student/orders', [StudentOrderController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/student/orders/{order}/payment', [StudentOrderController::class, 'paymentStatus']);
        Route::get('/student/notifications', [StudentOrderController::class, 'notifications']);
        Route::patch('/student/notifications/{notificationId}/read', [StudentOrderController::class, 'markNotificationRead']);
    });

    Route::middleware('role:staff,admin')->prefix('staff')->group(function (): void {
        Route::get('/orders', [StaffController::class, 'orders']);
        Route::patch('/orders/{order}/status', [StaffController::class, 'updateOrderStatus']);
        Route::post('/orders/{order}/cash-payment', [StaffController::class, 'confirmCashPayment']);

        Route::get('/categories', [StaffController::class, 'categories']);
        Route::get('/menu-items', [StaffController::class, 'menu']);
        Route::post('/menu-items', [StaffController::class, 'createMenuItem']);
        Route::patch('/menu-items/{menuItem}', [StaffController::class, 'updateMenuItem']);

        Route::get('/inventory', [StaffController::class, 'inventory']);
        Route::put('/inventory/{menuItem}', [StaffController::class, 'updateInventory']);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function (): void {
        Route::get('/reports', [AdminController::class, 'report']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::patch('/users/{user}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);

        Route::get('/categories', [AdminController::class, 'categories']);
        Route::post('/categories', [AdminController::class, 'createCategory']);
        Route::patch('/categories/{category}', [AdminController::class, 'updateCategory']);
        Route::delete('/categories/{category}', [AdminController::class, 'deleteCategory']);
    });
});
