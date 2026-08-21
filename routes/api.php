<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClerkWebhookController as ApiClerkWebhookController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// أي مستخدم مسجل دخول (أي دور)
Route::middleware('auth:clerk')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);

    // مؤقتاً public (بدون تسجيل دخول) - راجع المجموعة تحت. رجّعهم هون لما نرجع نفعّل الحماية.
    // Route::get('/products', [ProductController::class, 'index']);
    // Route::get('/products/{product}', [ProductController::class, 'show']);
    // Route::get('/categories', [CategoryController::class, 'index']);
    // Route::get('/categories/{category}', [CategoryController::class, 'show']);
});

Route::group([], function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
});

// Admin و Manager بس
Route::middleware(['auth:clerk', 'role:admin,manager'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

// Admin بس
Route::middleware(['auth:clerk', 'role:admin'])->group(function () {
    // Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
});
Route::post('/webhooks/clerk', [ApiClerkWebhookController::class, 'handle']);

Route::middleware(['auth:clerk', 'role:admin,manager'])->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::patch('/products/{product}/toggle-active', [ProductController::class, 'toggleActive']);
    Route::delete('/products/{product}/images/{image}', [ProductController::class, 'destroyImage']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
});

Route::middleware(['auth:clerk', 'role:admin'])->group(function () {
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
});
