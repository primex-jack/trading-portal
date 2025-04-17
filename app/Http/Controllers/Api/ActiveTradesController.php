<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ActiveTradesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        try {
            $trades = DB::connection('central')
                ->table('active_trades')
                ->select(['bot_id', 'trade_id as id', 'timestamp', 'trading_pair', 'timeframe', 'side', 'entry_price', 'size', 'stop_loss', 'profit_loss'])
                ->get();
            return response()->json(['data' => $trades]);
        } catch (\Exception $e) {
            \Log::error("Error fetching all active trades: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch active trades'], 500);
        }
    }
}
