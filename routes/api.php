<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;
use App\Models\Order;
use App\Models\Product;
use App\Http\Middleware\VerifyWebhookSignature;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Payment provider webhook — authenticated by HMAC signature, not by user token
Route::post('/webhooks/payment', PaymentWebhookController::class)
    ->middleware(VerifyWebhookSignature::class);

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
