<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Api\TradeController;
use App\Http\Controllers\Api\BotController;

// Authentication routes
Auth::routes();

// Dashboard routes (protected by auth)
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard/{bot}', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/overview', [DashboardController::class, 'overview'])->name('overview');
    Route::get('/', function () {
        return redirect()->route('overview');
    });
});

// API routes (protected by auth)
Route::middleware(['auth'])->prefix('api')->group(function () {
    Route::get('/{bot}/trades/active', [TradeController::class, 'activeTrades']);
    Route::get('/{bot}/trades/history', [TradeController::class, 'tradeHistory']);
    Route::get('/{bot}/status', [BotController::class, 'status']);
    Route::get('/trades/active', [TradeController::class, 'overviewActiveTrades']);
});

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
