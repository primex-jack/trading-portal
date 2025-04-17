import './bootstrap';

// Ensure only one instance of polling runs
if (!window.botPollingInitialized) {
    window.botPollingInitialized = true;

    let botId = null;
    let intervalId = null;

    // Function to initialize botId from the page
    function initializeBotId() {
        const botElement = document.querySelector('meta[name="bot-id"]');
        if (botElement) {
            botId = botElement.getAttribute('content');
            console.log('Initialized botId:', botId);
        }
    }

    // Fetch bot status
    function fetchBotStatus() {
        if (!botId) return;
        fetch(`/api/${botId}/status`, {
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
                if (statusEl) statusEl.textContent = data.running ? 'Running' : 'Stopped';
                if (statusEl) statusEl.className = data.running ? 'status-running' : 'status-stopped';
                if (pidEl) pidEl.textContent = data.pid || 'None';
                if (activityEl) activityEl.textContent = data.last_activity;

                // Display config settings
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
    }

    // Fetch active trades
    function fetchActiveTrades() {
        if (!botId) return;
        fetch(`/api/${botId}/trades/active`, {
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
                if (!tbody) return;
                tbody.innerHTML = '';
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8">No active trades</td></tr>';
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
                            <td>${trade.size.toFixed(4)}</td>
                            <td>${trade.stop_loss ? trade.stop_loss.toFixed(2) : 'N/A'}</td>
                        </tr>`;
                    tbody.innerHTML += row;
                });
            })
            .catch(error => console.error('Error fetching active trades:', error));
    }

    // Fetch trade history
    function fetchTradeHistory(page = 1) {
        if (!botId) return;
        fetch(`/api/${botId}/trades/history?page=${page}`, {
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
                const tbody = document.getElementById('trade-history');
                if (!tbody) return;
                tbody.innerHTML = '';
                if (data.data.length === 0) {
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
                if (!pagination) return;
                pagination.innerHTML = '';
                if (data.last_page > 1) {
                    let links = '';
                    if (data.current_page > 1) {
                        links += `<a href="#" onclick="fetchTradeHistory(${data.current_page - 1})" class="btn btn-sm btn-outline-primary mx-1">Previous</a>`;
                    }
                    for (let i = 1; i <= data.last_page; i++) {
                        links += `<a href="#" onclick="fetchTradeHistory(${i})" class="btn btn-sm ${i === data.current_page ? 'btn-primary' : 'btn-outline-primary'} mx-1">${i}</a>`;
                    }
                    if (data.current_page < data.last_page) {
                        links += `<a href="#" onclick="fetchTradeHistory(${data.current_page + 1})" class="btn btn-sm btn-outline-primary mx-1">Next</a>`;
                    }
                    pagination.innerHTML = links;
                }
            })
            .catch(error => console.error('Error fetching trade history:', error));
    }

    // Initialize and start polling
    document.addEventListener('DOMContentLoaded', () => {
        initializeBotId();
        if (!botId) return;

        fetchBotStatus();
        fetchActiveTrades();
        fetchTradeHistory();

        // Polling
        intervalId = setInterval(() => {
            fetchBotStatus();
            fetchActiveTrades();
        }, 10000);
    });

    // Clear interval on page unload
    window.addEventListener('beforeunload', () => {
        if (intervalId) {
            clearInterval(intervalId);
        }
        window.botPollingInitialized = false;
    });

    // Expose functions globally for pagination
    window.fetchTradeHistory = fetchTradeHistory;
}
