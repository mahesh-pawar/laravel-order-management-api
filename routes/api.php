<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use App\Models\Order;
use App\Models\Product;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Requires a valid Sanctum token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store'])->can('create', Product::class);
    Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])->can('update', 'product');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->can('delete', 'product');

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store'])->can('create', Order::class);
    Route::get('/orders/{order}', [OrderController::class, 'show'])->can('view', 'order');
});
