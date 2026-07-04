<?php

use App\Http\Controllers\DisputeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ProLinker API Routes — v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // =========================================================================
    // PUBLIC — No authentication required
    // =========================================================================
    Route::get('services',         [ServiceController::class, 'index']);
    Route::get('services/{id}',    [ServiceController::class, 'show']);
    Route::get('categories',       [ServiceController::class, 'categories']);

    // =========================================================================
    // AUTHENTICATED — All roles
    // =========================================================================
    Route::middleware('auth:sanctum')->group(function () {

        // ── Wallet: shared ────────────────────────────────────────────────────
        Route::get('wallet/summary', [WalletController::class, 'summary']);

        // ── Notifications ─────────────────────────────────────────────────────
        Route::get('notifications',                         [NotificationController::class, 'index']);
        Route::get('notifications/unread-count',            [NotificationController::class, 'unreadCount']);
        Route::patch('notifications/{id}/read',             [NotificationController::class, 'markAsRead']);
        Route::patch('notifications/read-all',              [NotificationController::class, 'markAllAsRead']);
        Route::delete('notifications/{id}',                 [NotificationController::class, 'destroy']);
        Route::delete('notifications/clear-all',            [NotificationController::class, 'clearAll']);

        // ── Order: shared view ────────────────────────────────────────────────
        Route::get('orders/{id}',   [OrderController::class, 'show']);

        // ── Client routes ─────────────────────────────────────────────────────
        Route::prefix('client')->group(function () {
            // Orders
            Route::get('orders',                    [OrderController::class, 'myOrders']);
            Route::post('orders',                   [OrderController::class, 'place']);
            Route::patch('orders/{id}/complete',    [OrderController::class, 'complete']);
            Route::patch('orders/{id}/cancel',      [OrderController::class, 'cancel']);

            // Disputes
            Route::post('orders/{orderId}/dispute', [DisputeController::class, 'open']);
            Route::get('disputes',                  [DisputeController::class, 'myDisputes']);

            // Wallet
            Route::post('wallet/deposit',                   [WalletController::class, 'deposit']);
            Route::get('wallet/deposits',                   [WalletController::class, 'myDeposits']);
            Route::get('wallet/deposits/{id}/receipt',      [WalletController::class, 'viewDepositReceipt']);
        });

        // ── Seller routes ─────────────────────────────────────────────────────
        Route::prefix('seller')->group(function () {
            // Services
            Route::get('services',              [ServiceController::class, 'myServices']);
            Route::post('services',             [ServiceController::class, 'store']);
            Route::put('services/{id}',         [ServiceController::class, 'update']);
            Route::delete('services/{id}',      [ServiceController::class, 'destroy']);

            // Orders
            Route::get('orders',                    [OrderController::class, 'sellerOrders']);
            Route::patch('orders/{id}/accept',      [OrderController::class, 'accept']);
            Route::patch('orders/{id}/deliver',     [OrderController::class, 'deliver']);

            // Wallet
            Route::post('wallet/withdraw',                      [WalletController::class, 'withdraw']);
            Route::get('wallet/withdrawals',                    [WalletController::class, 'myWithdrawals']);
            Route::get('wallet/withdrawals/{id}/receipt',       [WalletController::class, 'viewWithdrawalReceipt']);
        });

        // ── Admin routes ──────────────────────────────────────────────────────
        Route::prefix('admin')->group(function () {
            // Orders overview
            Route::get('orders',    [OrderController::class, 'adminIndex']);

            // Deposits
            Route::get('deposits',                  [WalletController::class, 'adminListDeposits']);
            Route::get('deposits/{id}/receipt',     [WalletController::class, 'adminViewDepositReceipt']);
            Route::patch('deposits/{id}/approve',   [WalletController::class, 'approveDeposit']);
            Route::patch('deposits/{id}/reject',    [WalletController::class, 'rejectDeposit']);

            // Withdrawals
            Route::get('withdrawals',               [WalletController::class, 'adminListWithdrawals']);
            Route::patch('withdrawals/{id}/approve', [WalletController::class, 'approveWithdrawal']);
            Route::patch('withdrawals/{id}/reject',  [WalletController::class, 'rejectWithdrawal']);

            // Disputes
            Route::get('disputes',                          [DisputeController::class, 'adminIndex']);
            Route::get('disputes/{id}',                     [DisputeController::class, 'adminShow']);
            Route::patch('disputes/{id}/assign',            [DisputeController::class, 'assign']);
            Route::patch('disputes/{id}/resolve/client',    [DisputeController::class, 'resolveForClient']);
            Route::patch('disputes/{id}/resolve/seller',    [DisputeController::class, 'resolveForSeller']);
            Route::patch('disputes/{id}/close',             [DisputeController::class, 'closeWithNoAction']);
        });
    });
});

// ── Auth ─────────────────────────────────────────────────────────────────────
Route::prefix('v1/auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });
});
