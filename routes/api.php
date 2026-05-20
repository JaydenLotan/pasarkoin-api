<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Seller\CoinListingController as SellerCoinListingController;
use App\Http\Controllers\Api\Seller\CoinListingImageController as SellerCoinListingImageController;
use App\Http\Controllers\Api\Admin\CoinListingApprovalController;

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

        Route::get('listings/{listing}/images', [SellerCoinListingImageController::class, 'index']);
        Route::post('listings/{listing}/images', [SellerCoinListingImageController::class, 'store']);
        Route::delete('listings/{listing}/images/{image}', [SellerCoinListingImageController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('listings', [CoinListingApprovalController::class, 'index']);
        Route::get('listings/pending', [CoinListingApprovalController::class, 'pending']);
        Route::post('listings/{listing}/approve', [CoinListingApprovalController::class, 'approve']);
        Route::post('listings/{listing}/reject', [CoinListingApprovalController::class, 'reject']);
    });