<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BotController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function status($bot)
    {
        try {
            $bots = config('bots');
            if (!isset($bots[$bot])) {
                return response()->json(['error' => 'Bot not found'], 404);
            }

            $pidFile = $bots[$bot]['pid_file'];
            $logFile = $bots[$bot]['log_file'];
            $status = [];

            // Read PID
            if (!File::exists($pidFile)) {
                \Log::error("PID file not found at: $pidFile");
            } else {
                \Log::info("PID file found at: $pidFile");
            }

            if (File::exists($pidFile)) {
                $pid = trim(File::get($pidFile));
                \Log::info("PID read from file: $pid");
                if (file_exists("/proc/$pid")) {
                    $status['running'] = true;
                    $status['pid'] = $pid;
                    \Log::info("Process with PID $pid is running");
                } else {
                    $status['running'] = false;
                    $status['pid'] = null;
                    \Log::error("Process with PID $pid is not running");
                }
            } else {
                $status['running'] = false;
                $status['pid'] = null;
            }

            // Read last activity
            if (!File::exists($logFile)) {
                \Log::error("Log file not found at: $logFile");
            } else {
                \Log::info("Log file found at: $logFile");
            }

            if (File::exists($logFile)) {
                $lines = array_reverse(File::lines($logFile)->toArray());
                $lastBarClosed = null;
                foreach ($lines as $line) {
                    if (strpos($line, 'Bar Closed') !== false) {
                        preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2},\d{3})/', $line, $matches);
                        if (isset($matches[1])) {
                            $lastBarClosed = $matches[1];
                            break;
                        }
                    }
                }
                $status['last_activity'] = $lastBarClosed ?: 'No Bar Closed entries found';
            } else {
                $status['last_activity'] = 'Log file not found';
            }

            // Fetch settings from central database
            $settings = DB::connection('central')
                ->table('bot_settings')
                ->where('bot_id', $bot)
                ->first();

            if ($settings) {
                $status['settings'] = [
                    'bot_name' => $settings->bot_name,
                    'atr_period' => $settings->atr_period,
                    'atr_ratio' => $settings->atr_ratio,
                    'position_size' => $settings->position_size,
                    'trading_pair' => $settings->trading_pair,
                    'timeframe' => $settings->timeframe,
                    'stop_loss_offset' => $settings->stop_loss_offset,
                ];
            } else {
                $status['settings'] = null;
            }

            return response()->json($status);
        } catch (\Exception $e) {
            \Log::error("BotController error for $bot: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch bot status'], 500);
        }
    }
}
