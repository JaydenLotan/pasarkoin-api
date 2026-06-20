<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Seller\CoinListingController as SellerCoinListingController;
use App\Http\Controllers\Api\Seller\CoinListingImageController as SellerCoinListingImageController;
use App\Http\Controllers\Api\Admin\CoinListingApprovalController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\Buyer\OrderController as BuyerOrderController;
use App\Http\Controllers\Api\Seller\OrderController as SellerOrderController;

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
        Route::get('orders', [SellerOrderController::class, 'index']);
        Route::get('orders/{order}', [SellerOrderController::class, 'show']);
        Route::patch('orders/{order}/status', [SellerOrderController::class, 'updateStatus']);
    });

    Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('listings', [CoinListingApprovalController::class, 'index']);
        Route::get('listings/pending', [CoinListingApprovalController::class, 'pending']);
        Route::post('listings/{listing}/approve', [CoinListingApprovalController::class, 'approve']);
        Route::post('listings/{listing}/reject', [CoinListingApprovalController::class, 'reject']);
    });


Route::middleware(['auth:sanctum', 'role:buyer'])
    ->prefix('buyer')
    ->group(function () {
        Route::get('orders', [BuyerOrderController::class, 'index']);
        Route::post('orders', [BuyerOrderController::class, 'store']);
        Route::get('orders/{order}', [BuyerOrderController::class, 'show']);
    });



Route::get('/listings', [ListingController::class, 'index']);
Route::get('/listings/{listing}', [ListingController::class, 'show']);