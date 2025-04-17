@extends('layouts.app')

@section('content')
    <div class="container-fluid custom-container">
        <h1 class="mb-4 mt-5 text-center heading-main">Trading Overview</h1>

        <!-- Bot Accounts Section -->
        <div class="row mb-5">
            @foreach ($bots as $botId)
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm border-0 h-100 bot-card">
                        <div class="card-header d-flex justify-content-between align-items-center text-white">
                            <h5 class="card-title mb-0">{{ config('bots')[$botId]['name'] }}</h5>
                            <span class="badge" id="bot-status-{{ $botId }}" style="font-size: 0.9rem;">Loading...</span>
                        </div>
                        <div class="card-body p-4">
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
                                    <p class="current-position-label"><strong>Current Position:</strong></p>
                                    <div class="current-position-wrapper">
                                        <span class="badge position-badge" id="position-{{ $botId }}">Loading...</span>
                                    </div>
                                    <p class="position-details">
                                        <span id="position-details-{{ $botId }}">Loading...</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Active Trades Section -->
        <div class="card mb-4 active-trades-card">
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

        function formatNumber(number) {
            return Number(number).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
                    const detailsEl = document.getElementById(`position-details-${botId}`);
                    if (data.data && data.data.length > 0) {
                        const trade = data.data[0];
                        const side = trade.side;
                        const profitLoss = trade.profit_loss;
                        positionEl.textContent = `${side} ${trade.trading_pair}`;
                        positionEl.className = `badge position-badge ${side === 'SHORT' ? 'bg-danger' : 'bg-success'}`;
                        detailsEl.textContent = `Entry: ${formatNumber(trade.entry_price)} | P/L: ${formatNumber(profitLoss)}`;
                        detailsEl.className = profitLoss >= 0 ? 'profit-positive' : 'profit-negative';
                    } else {
                        positionEl.textContent = 'No Position';
                        positionEl.className = 'badge position-badge bg-secondary';
                        detailsEl.textContent = '';
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
                    document.getElementById(`balance-${botId}`).textContent = formatNumber(data.futures_margin);
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
                                    <td>${formatNumber(trade.entry_price)}</td>
                                    <td>${atrParam}</td>
                                    <td>${Math.round(trade.size)}</td>
                                    <td>${formatNumber(sizeDollars)}</td>
                                    <td>${trade.stop_loss ? formatNumber(trade.stop_loss) : 'N/A'}</td>
                                    <td class="${profitLossClass}">${formatNumber(trade.profit_loss)}</td>
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
    .custom-container {
        max-width: 1920px; /* Ensure max-width is applied */
        margin: 0 auto;
        padding: 0 30px; /* Increased padding for better spacing */
    }

    .heading-main {
        font-size: 2.5rem;
        font-weight: 600;
        color: #333;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
    }

    .card-header {
        background: linear-gradient(135deg, #7088ad 0%, #5a6f8f 100%);
        color: white;
        font-weight: 500;
    }

    .bot-card {
        border-radius: 15px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .bot-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15) !important;
    }

    .badge.bg-success {
        background-color: #28a745 !important;
    }

    .badge.bg-danger {
        background-color: #dc3545 !important;
    }

    .badge.bg-secondary {
        background-color: #6c757d !important;
    }

    p {
        margin-bottom: 0.5rem;
    }

    .profit-positive { 
        color: #28a745; 
    }

    .profit-negative { 
        color: #dc3545; 
    }

    .current-position-label {
        margin-bottom: 0.25rem;
    }

    .current-position-wrapper {
        text-align: center;
        margin-bottom: 0.5rem;
    }

    .position-badge {
        font-size: 1.2rem;
        padding: 0.5rem 1rem;
        display: inline-block;
        width: 100%;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .position-badge.bg-success {
        background-color: #28a745 !important;
        color: white;
	padding: 10;
    }

    .position-badge.bg-danger {
        background-color: #dc3545 !important;
        color: white;
	padding: 10;
    }

    .position-badge.bg-secondary {
        background-color: #6c757d !important;
        color: white;
	padding: 10;
    }

    .position-details {
        text-align: center;
        font-size: 1rem;
        font-weight: 500;
    }

    .active-trades-card .card-header {
        background: linear-gradient(135deg, #7088ad 0%, #5a6f8f 100%);
        color: white;
        font-weight: 500;
    }

    .table thead th {
        background-color: #7088ad;
        color: white;
        border-bottom: 2px solid #5a6f8f;
    }

    .table tbody tr:nth-child(odd) {
        background-color: #f8f9fa;
    }

    .table tbody tr:nth-child(even) {
        background-color: #ffffff;
    }

    .table tbody tr:hover {
        background-color: #e9ecef;
        transition: background-color 0.3s ease;
    }
</style>
