<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index($bot)
    {
        $bots = config('bots');
        if (!isset($bots[$bot])) {
            abort(404, 'Bot not found');
        }
        return view('dashboard', ['bot' => $bot, 'bots' => array_keys($bots)]);
    }

    public function overview()
    {
        $bots = config('bots');
        return view('overview', ['bots' => array_keys($bots)]);
    }
}
