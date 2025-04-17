@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="mb-4">
            <label for="bot-select">Select Bot:</label>
            <select id="bot-select" onchange="window.switchBot(this.value)">
                @foreach ($bots as $botId)
                    <option value="{{ $botId }}" {{ $bot == $botId ? 'selected' : '' }}>{{ config('bots')[$botId]['name'] }}</option>
                @endforeach
            </select>
        </div>

        <h1 class="mb-4">{{ config('bots')[$bot]['name'] }} Dashboard</h1>

        <!-- Bot Status -->
        <div class="card mb-4">
            <div class="card-header">Bot Status</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p>Status: <span id="bot-status" class="status-stopped">Loading...</span></p>
                        <p>PID: <span id="bot-pid">Loading...</span></p>
                        <p>Last Activity: <span id="bot-last-activity">Loading...</span></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Settings</h6>
                        <p>Bot Name: <span id="bot-name">Loading...</span></p>
                        <p>ATR Period: <span id="atr-period">Loading...</span></p>
                        <p>ATR Ratio: <span id="atr-ratio">Loading...</span></p>
                        <p>Position Size: <span id="position-size">Loading...</span></p>
                        <p>Trading Pair: <span id="trading-pair">Loading...</span></p>
                        <p>Timeframe: <span id="timeframe">Loading...</span></p>
                        <p>Stop Loss Offset: <span id="stop-loss-offset">Loading...</span></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Trades -->
        <div class="card mb-4">
            <div class="card-header">Active Trades</div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Time</th>
                            <th>Trading Pair</th>
                            <th>Timeframe</th>
                            <th>Side</th>
                            <th>Entry Price</th>
                            <th>Size</th>
                            <th>Stop Loss</th>
                            <th>Profit/Loss (USDT)</th>
                            <th>Order ID</th>
                            <th>Stop Loss Order ID</th>
                        </tr>
                    </thead>
                    <tbody id="active-trades">
                        <tr><td colspan="11">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Trade History -->
        <div class="card">
            <div class="card-header">Trade History</div>
            <div class="card-body">
                <button onclick="window.loadTradeHistory()" class="btn btn-primary mb-2">Load Trade History</button>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Time</th>
                            <th>Trading Pair</th>
                            <th>Timeframe</th>
                            <th>Side</th>
                            <th>Entry Price</th>
                            <th>Exit Price</th>
                            <th>Profit/Loss</th>
                            <th>Trend</th>
                            <th>Order ID</th>
                            <th>Stop Loss Order ID</th>
                        </tr>
                    </thead>
                    <tbody id="trade-history">
                        <tr><td colspan="11">Loading...</td></tr>
                    </tbody>
                </table>
                <div id="history-pagination"></div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            // Define botId globally
            window.botId = '{{ $bot }}';

            // Function to switch bot
            window.switchBot = function(newBotId) {
                if (window.intervalId) {
                    clearInterval(window.intervalId);
                }
                window.location.href = '/dashboard/' + newBotId + '?_=' + new Date().getTime();
            };

            // Fetch bot status
            window.fetchBotStatus = function() {
                fetch(`/api/${window.botId}/status`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    cache: 'no-store'
                })
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        const statusEl = document.getElementById('bot-status');
                        const pidEl = document.getElementById('bot-pid');
                        const activityEl = document.getElementById('bot-last-activity');
                        statusEl.textContent = data.running ? 'Running' : 'Stopped';
                        statusEl.className = data.running ? 'status-running' : 'status-stopped';
                        pidEl.textContent = data.pid || 'None';
                        activityEl.textContent = data.last_activity;

                        if (data.settings) {
                            document.getElementById('bot-name').textContent = data.settings.bot_name;
                            document.getElementById('atr-period').textContent = data.settings.atr_period;
                            document.getElementById('atr-ratio').textContent = data.settings.atr_ratio;
                            document.getElementById('position-size').textContent = data.settings.position_size;
                            document.getElementById('trading-pair').textContent = data.settings.trading_pair;
                            document.getElementById('timeframe').textContent = data.settings.timeframe;
                            document.getElementById('stop-loss-offset').textContent = data.settings.stop_loss_offset;
                        } else {
                            document.getElementById('bot-name').textContent = 'N/A';
                            document.getElementById('atr-period').textContent = 'N/A';
                            document.getElementById('atr-ratio').textContent = 'N/A';
                            document.getElementById('position-size').textContent = 'N/A';
                            document.getElementById('trading-pair').textContent = 'N/A';
                            document.getElementById('timeframe').textContent = 'N/A';
                            document.getElementById('stop-loss-offset').textContent = 'N/A';
                        }
                    })
                    .catch(error => console.error('Error fetching bot status:', error));
            };

            // Fetch active trades
            window.fetchActiveTrades = function() {
                fetch(`/api/${window.botId}/trades/active`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    cache: 'no-store'
                })
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        const tbody = document.getElementById('active-trades');
                        tbody.innerHTML = '';
                        if (data.data.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="11">No active trades</td></tr>';
                            return;
                        }
                        data.data.forEach(trade => {
                            const profitLossClass = trade.profit_loss >= 0 ? 'profit-positive' : 'profit-negative';
                            const row = `
                                <tr>
                                    <td>${trade.id}</td>
                                    <td>${trade.timestamp}</td>
                                    <td>${trade.trading_pair}</td>
                                    <td>${trade.timeframe}</td>
                                    <td>${trade.side}</td>
                                    <td>${trade.entry_price.toFixed(2)}</td>
                                    <td>${trade.size ? trade.size.toFixed(4) : 'N/A'}</td>
                                    <td>${trade.stop_loss ? trade.stop_loss.toFixed(2) : 'N/A'}</td>
                                    <td class="${profitLossClass}">${trade.profit_loss.toFixed(2)}</td>
                                    <td>${trade.order_id || 'N/A'}</td>
                                    <td>${trade.stop_loss_order_id || 'N/A'}</td>
                                </tr>`;
                            tbody.innerHTML += row;
                        });
                    })
                    .catch(error => console.error('Error fetching active trades:', error));
            };

            // Fetch trade history
            window.loadTradeHistory = function(page = 1) {
                fetch(`/api/${window.botId}/trades/history?page=${page}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    cache: 'no-store'
                })
                    .then(response => {
                        if (!response.ok) throw new Error(`Network response was not ok: ${response.statusText}`);
                        return response.json();
                    })
                    .then(data => {
                        const tbody = document.getElementById('trade-history');
                        tbody.innerHTML = '';
                        if (!data || !data.data || data.data.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="11">No trade history</td></tr>';
                            return;
                        }
                        data.data.forEach(trade => {
                            const row = `
                                <tr>
                                    <td>${trade.id}</td>
                                    <td>${trade.timestamp}</td>
                                    <td>${trade.trading_pair}</td>
                                    <td>${trade.timeframe}</td>
                                    <td>${trade.side}</td>
                                    <td>${trade.entry_price.toFixed(2)}</td>
                                    <td>${trade.exit_price ? trade.exit_price.toFixed(2) : 'N/A'}</td>
                                    <td>${trade.profit_loss ? trade.profit_loss.toFixed(2) : 'N/A'}</td>
                                    <td>${trade.trend}</td>
                                    <td>${trade.order_id}</td>
                                    <td>${trade.stop_loss_order_id || 'N/A'}</td>
                                </tr>`;
                            tbody.innerHTML += row;
                        });

                        const pagination = document.getElementById('history-pagination');
                        pagination.innerHTML = '';
                        if (data.last_page > 1) {
                            let links = '';
                            if (data.current_page > 1) {
                                links += `<a href="#" onclick="window.loadTradeHistory(${data.current_page - 1})" class="btn btn-sm btn-outline-primary mx-1">Previous</a>`;
                            }
                            for (let i = 1; i <= data.last_page; i++) {
                                links += `<a href="#" onclick="window.loadTradeHistory(${i})" class="btn btn-sm ${i === data.current_page ? 'btn-primary' : 'btn-outline-primary'} mx-1">${i}</a>`;
                            }
                            if (data.current_page < data.last_page) {
                                links += `<a href="#" onclick="window.loadTradeHistory(${data.current_page + 1})" class="btn btn-sm btn-outline-primary mx-1">Next</a>`;
                            }
                            pagination.innerHTML = links;
                        }
                    })
                    .catch(error => console.error('Error fetching trade history:', error));
            };

            // Initial fetches
            document.addEventListener('DOMContentLoaded', () => {
                window.fetchBotStatus();
                window.fetchActiveTrades();
                window.loadTradeHistory();

                // Polling for all fetches
                window.intervalId = setInterval(() => {
                    window.fetchBotStatus();
                    window.fetchActiveTrades();
                    window.loadTradeHistory();
                }, 10000);
            });

            // Clear interval on page unload to prevent stale polling
            window.addEventListener('beforeunload', () => {
                if (window.intervalId) {
                    clearInterval(window.intervalId);
                }
                sessionStorage.removeItem('intervalId');
            });
        </script>
        <style>
            .status-running { color: green; }
            .status-stopped { color: red; }
            .profit-positive { color: green; }
            .profit-negative { color: red; }
        </style>
    @endpush
@endsection
