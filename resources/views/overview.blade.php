@extends('layouts.app')

@section('content')
    <div class="container">
        <h1 class="mb-4">Trading Overview</h1>

        <!-- Bot Accounts Section -->
        <div class="row mb-4">
            @foreach ($bots as $botId)
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                            <h5 class="card-title mb-0">{{ config('bots')[$botId]['name'] }}</h5>
                            <span class="badge" id="bot-status-{{ $botId }}" style="font-size: 0.9rem;">Loading...</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <p><strong>Trading Pair:</strong> <span id="trading-pair-{{ $botId }}">Loading...</span></p>
                                    <p><strong>Timeframe:</strong> <span id="timeframe-{{ $botId }}">Loading...</span></p>
                                    <p><strong>ATR Ratio:</strong> <span id="atr-ratio-{{ $botId }}">Loading...</span></p>
                                    <p><strong>ATR Period:</strong> <span id="atr-period-{{ $botId }}">Loading...</span></p>
                                </div>
                                <div class="col-6">
                                    <p><strong>Exchange:</strong> Binance</p>
                                    <p><strong>Balance:</strong> <span id="balance-{{ $botId }}">Loading...</span> USDT</p>
                                    <p><strong>Current Position:</strong> <span id="position-{{ $botId }}">Loading...</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Active Trades Section -->
        <div class="card mb-4">
            <div class="card-header">Active Trades</div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Bot</th>
                            <th>Trade Open</th>
                            <th>Trading Pair</th>
                            <th>Timeframe</th>
                            <th>Side</th>
                            <th>Entry Price</th>
                            <th>Param</th>
                            <th>Size</th>
                            <th>Size $</th>
                            <th>Stop Loss</th>
                            <th>Profit/Loss (USDT)</th>
                        </tr>
                    </thead>
                    <tbody id="overview-active-trades">
                        <tr><td colspan="11">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            return `${day}.${month} ${hours}:${minutes}:${seconds}`;
        }

        function getBaseAsset(tradingPair) {
            return tradingPair.replace(/USDT$/, '');
        }

        const botSettings = {};

        function fetchBotSettings(botId) {
            return fetch(`/api/${botId}/status`, {
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
                    botSettings[botId] = data.settings || {};
                    const statusEl = document.getElementById(`bot-status-${botId}`);
                    statusEl.textContent = data.running ? 'Running' : 'Stopped';
                    statusEl.className = `badge ${data.running ? 'bg-success' : 'bg-danger'}`;
                    document.getElementById(`trading-pair-${botId}`).textContent = data.settings.trading_pair;
                    document.getElementById(`timeframe-${botId}`).textContent = data.settings.timeframe;
                    document.getElementById(`atr-ratio-${botId}`).textContent = data.settings.atr_ratio;
                    document.getElementById(`atr-period-${botId}`).textContent = data.settings.atr_period;
                })
                .catch(error => {
                    console.error(`Error fetching settings for ${botId}:`, error);
                });
        }

        function fetchBotPosition(botId) {
            return fetch(`/api/${botId}/trades/active`, {
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
                    const positionEl = document.getElementById(`position-${botId}`);
                    if (data.data && data.data.length > 0) {
                        const trade = data.data[0];
                        positionEl.textContent = `${trade.side} ${trade.trading_pair}`;
                    } else {
                        positionEl.textContent = 'No Position';
                    }
                })
                .catch(error => {
                    console.error(`Error fetching position for ${botId}:`, error);
                });
        }

        function fetchBotBalance(botId) {
            return fetch(`/api/${botId}/account`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                cache: 'no-store'
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Failed to fetch balance for ${botId}: ${response.status} ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    document.getElementById(`balance-${botId}`).textContent = data.futures_margin.toFixed(2);
                })
                .catch(error => {
                    console.error(`Error fetching balance for ${botId}:`, error);
                    document.getElementById(`balance-${botId}`).textContent = `Error: ${error.message}`;
                });
        }

        function fetchOverviewActiveTrades() {
            const bots = @json(array_keys(config('bots')));
            const promises = bots.map(bot => fetchBotSettings(bot));
            
            Promise.all(promises).then(() => {
                fetch(`/api/trades/active`, {
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
                        const tbody = document.getElementById('overview-active-trades');
                        tbody.innerHTML = '';
                        if (!data || data.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="11">No active trades</td></tr>';
                            return;
                        }
                        data.forEach(trade => {
                            const botId = trade.bot_id;
                            const settings = botSettings[botId] || {};
                            const atrParam = settings.atr_period && settings.atr_ratio ? `${settings.atr_period}/${settings.atr_ratio}` : 'N/A';
                            const profitLossClass = trade.profit_loss >= 0 ? 'profit-positive' : 'profit-negative';
                            const sizeDollars = (trade.size * trade.entry_price).toFixed(2);
                            const row = `
                                <tr>
                                    <td>${botId}</td>
                                    <td>${formatTimestamp(trade.timestamp)}</td>
                                    <td>${getBaseAsset(trade.trading_pair)}</td>
                                    <td>${trade.timeframe}</td>
                                    <td>${trade.side}</td>
                                    <td>${trade.entry_price.toFixed(2)}</td>
                                    <td>${atrParam}</td>
                                    <td>${Math.round(trade.size)}</td>
                                    <td>${sizeDollars}</td>
                                    <td>${trade.stop_loss ? trade.stop_loss.toFixed(2) : 'N/A'}</td>
                                    <td class="${profitLossClass}">${trade.profit_loss.toFixed(2)}</td>
                                </tr>`;
                            tbody.innerHTML += row;
                        });
                    })
                    .catch(error => console.error('Error fetching overview active trades:', error));
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const bots = @json(array_keys(config('bots')));
            bots.forEach(botId => {
                fetchBotSettings(botId);
                fetchBotPosition(botId);
                fetchBotBalance(botId);
            });
            fetchOverviewActiveTrades();

            setInterval(() => {
                bots.forEach(botId => {
                    fetchBotSettings(botId);
                    fetchBotPosition(botId);
                    fetchBotBalance(botId);
                });
                fetchOverviewActiveTrades();
            }, 10000);
        });
    </script>
@endpush

<style>
    .card-header {
        background-color: #007bff;
        color: white;
        font-weight: 500;
    }
    .card {
        border-radius: 10px;
        transition: transform 0.2s;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2) !important;
    }
    .badge.bg-success {
        background-color: #28a745 !important;
    }
    .badge.bg-danger {
        background-color: #dc3545 !important;
    }
    p {
        margin-bottom: 0.5rem;
    }
    .profit-positive { color: green; }
    .profit-negative { color: red; }
</style>
