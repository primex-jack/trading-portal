<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class TradeController extends Controller
{
    public function activeTrades(Request $request, $bot)
    {
        try {
            $query = DB::connection('central')
                ->table('active_trades')
                ->where('bot_id', $bot)
                ->select(['bot_id', 'trade_id as id', 'timestamp', 'trading_pair', 'timeframe', 'side', 'entry_price', 'size', 'stop_loss', 'profit_loss', 'order_id', 'stop_loss_order_id'])
                ->orderBy('id', 'desc');

            $perPage = $request->input('per_page', 10);
            $trades = $query->paginate($perPage);

            return response()->json($trades, 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            \Log::error("Error fetching active trades for $bot: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch active trades: ' . $e->getMessage()], 500);
        }
    }

    public function tradeHistory(Request $request, $bot)
    {
        try {
            $query = DB::connection('central')
                ->table('trade_history')
                ->where('bot_id', $bot)
                ->select(['bot_id', 'trade_id as id', 'timestamp', 'trading_pair', 'timeframe', 'side', 'entry_price', 'exit_price', 'profit_loss', 'trend', 'order_id', 'stop_loss_order_id'])
                ->orderBy('id', 'desc');

            $perPage = $request->input('per_page', 10);
            $trades = $query->paginate($perPage);

            return response()->json($trades, 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            \Log::error("Error fetching trade history for $bot: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch trade history: ' . $e->getMessage()], 500);
        }
    }

    public function overviewActiveTrades(Request $request)
    {
        try {
            $trades = DB::connection('central')
                ->table('active_trades')
                ->select(['bot_id', 'trade_id as id', 'timestamp', 'trading_pair', 'timeframe', 'side', 'entry_price', 'size', 'stop_loss', 'profit_loss'])
                ->orderBy('bot_id', 'asc')
                ->orderBy('id', 'desc')
                ->get();

            return response()->json($trades, 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            \Log::error("Error fetching active trades for overview: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch active trades: ' . $e->getMessage()], 500);
        }
    }

    public function accountDetails(Request $request, $bot)
{
    try {
        \Log::debug("Fetching account details for bot: $bot");
        $query = DB::connection('central')
            ->table('exchange_accounts')
            ->whereRaw('LOWER(bot_id) = ?', [strtolower($bot)])
            ->select(['balance', 'available_margin', 'open_trades', 'updated_at', 'futures_balance', 'futures_margin']);

        \Log::debug("Executing query for bot: $bot, SQL: " . $query->toSql() . ", Bindings: " . json_encode($query->getBindings()));

        $account = $query->first();

        \Log::debug("Account query result for $bot: " . json_encode($account));

        if (!$account) {
            \Log::warning("No account details found for $bot, returning default response");
            return response()->json([
                'balance' => 0.0,
                'available_margin' => 0.0,
                'open_trades' => 0,
                'updated_at' => date('c'),
                'futures_balance' => 0.0,
                'futures_margin' => 0.0
            ], 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }

        return response()->json($account, 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    } catch (\Exception $e) {
        \Log::error("Error fetching account details for $bot: " . $e->getMessage());
        return response()->json(['error' => 'Failed to fetch account details: ' . $e->getMessage()], 500);
    }
}

}
