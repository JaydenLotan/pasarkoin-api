<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Seller\CoinListingController as SellerCoinListingController;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => 'PasarKoin API',
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:sanctum', 'role:seller'])->get('/seller/test', function () {
    return response()->json([
        'message' => 'Seller route working',
    ]);
});

Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/test', function () {
    return response()->json([
        'message' => 'Admin route working',
    ]);
});

Route::middleware(['auth:sanctum', 'role:buyer'])->get('/buyer/test', function () {
    return response()->json([
        'message' => 'Buyer route working',
    ]);
});

Route::middleware(['auth:sanctum', 'role:seller'])
    ->prefix('seller')
    ->group(function () {
        Route::apiResource('listings', SellerCoinListingController::class);
    });