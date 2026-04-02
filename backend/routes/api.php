<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\FinanceController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\RecipeController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\StockMovementController;
use App\Http\Controllers\API\TableController;
use App\Http\Controllers\API\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // Dashboard (head_manager only)
    Route::middleware(['auth:sanctum', 'role:head_manager'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
    });

    // User management (head_manager only)
    Route::middleware(['auth:sanctum', 'role:head_manager'])->group(function () {
        Route::get('/users', [AuthController::class, 'users']);
        Route::post('/users', [AuthController::class, 'createUser']);
        Route::put('/users/{id}', [AuthController::class, 'updateUser']);
        Route::put('/users/{id}/deactivate', [AuthController::class, 'deactivateUser']);
    });

    // Product routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/low-stock', [ProductController::class, 'lowStock'])->middleware('role:kasir,head_manager');
        Route::get('/products/{id}', [ProductController::class, 'show']);

        Route::middleware('role:head_manager')->group(function () {
            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        });
    });

    // Stock movement routes
    Route::middleware(['auth:sanctum', 'role:kasir,head_manager'])->group(function () {
        Route::post('/stock-movements', [StockMovementController::class, 'store']);
        Route::get('/stock-movements', [StockMovementController::class, 'index']);
    });

    // Recipe routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/products/{id}/recipes', [RecipeController::class, 'index']);

        Route::middleware('role:head_manager')->group(function () {
            Route::post('/products/{id}/recipes', [RecipeController::class, 'store']);
            Route::put('/recipes/{id}', [RecipeController::class, 'update']);
            Route::delete('/recipes/{id}', [RecipeController::class, 'destroy']);
        });
    });

    // Table routes
    Route::middleware(['auth:sanctum', 'role:kasir,head_manager'])->group(function () {
        Route::get('/tables', [TableController::class, 'index']);
        Route::get('/tables/{id}', [TableController::class, 'show']);
    });

    Route::middleware(['auth:sanctum', 'role:head_manager'])->group(function () {
        Route::post('/tables', [TableController::class, 'store']);
        Route::put('/tables/{id}', [TableController::class, 'update']);
        Route::get('/tables/{id}/qr', [TableController::class, 'qr']);
    });

    // Public menu endpoint (no auth)
    Route::get('/tables/{identifier}/menu', [TableController::class, 'menu']);

    // Order routes
    // POST has no auth — handles both guest (self_order) and authenticated (kasir) requests
    Route::post('/orders', [OrderController::class, 'store']);

    Route::middleware(['auth:sanctum', 'role:kasir,head_manager'])->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
        // by-code MUST be before {id} to avoid routing conflicts
        Route::get('/orders/by-code/{code}', [OrderController::class, 'byCode']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    });

    // Transaction routes
    Route::middleware(['auth:sanctum', 'role:kasir,head_manager'])->group(function () {
        Route::post('/transactions/checkout', [TransactionController::class, 'checkout']);
        Route::get('/transactions/{id}/receipt', [TransactionController::class, 'receipt']);
    });

    // Finance routes
    Route::middleware(['auth:sanctum', 'role:finance,head_manager'])->group(function () {
        Route::get('/expenses', [FinanceController::class, 'indexExpenses']);
        Route::get('/income', [FinanceController::class, 'indexIncome']);
        Route::put('/income/{id}/validate', [FinanceController::class, 'validateIncome']);
        Route::get('/finance/summary', [FinanceController::class, 'summary']);
    });

    Route::middleware(['auth:sanctum', 'role:finance'])->group(function () {
        Route::post('/expenses', [FinanceController::class, 'storeExpense']);
    });

    // Report routes
    Route::middleware(['auth:sanctum', 'role:finance,head_manager'])->group(function () {
        Route::get('/reports/stock', [ReportController::class, 'stock']);
        Route::get('/reports/stock-movement', [ReportController::class, 'stockMovement']);
        Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss']);
    });
});
