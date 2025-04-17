<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TradeController;
use App\Http\Controllers\Api\BotController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    $bots = array_keys(config('bots'));
    return view('overview', compact('bots'));
});

Route::get('/dashboard/{bot}', function ($bot) {
    $bots = array_keys(config('bots'));
    if (!in_array($bot, $bots)) {
        abort(404);
    }
    return view('dashboard', compact('bots', 'bot'));
});

Route::prefix('api')->group(function () {
    Route::get('{bot}/status', [BotController::class, 'status']);
    Route::get('{bot}/trades/active', [TradeController::class, 'activeTrades']);
    Route::get('{bot}/trades/history', [TradeController::class, 'tradeHistory']);
    Route::get('trades/active', [TradeController::class, 'overviewActiveTrades']);
    Route::get('{bot}/account', [TradeController::class, 'accountDetails']);
});
