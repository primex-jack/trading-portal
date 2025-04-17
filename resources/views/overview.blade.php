@extends('layouts.app')

@section('content')
    <h1>Trading Overview</h1>

    <!-- Active Trades -->
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

        // Fetch bot settings for ATR_PERIOD and ATR_RATIO
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
                })
                .catch(error => console.error(`Error fetching settings for ${botId}:`, error));
        }

        // Fetch active trades for all bots
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

        // Initial fetch
        document.addEventListener('DOMContentLoaded', () => {
            fetchOverviewActiveTrades();

            // Polling for updates
            setInterval(() => {
                fetchOverviewActiveTrades();
            }, 10000);
        });
    </script>
@endpush
