import sqlite3
import json
import time
import logging
from datetime import datetime, timezone
import requests
import os
from binance.um_futures import UMFutures  # Using the same Binance client as the bots

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/var/www/html/trading-portal/storage/logs/sync_bots.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# Script Version
SCRIPT_VERSION = "1.0.1"  # Updated for dynamic API credentials

# Paths
CENTRAL_DB_PATH = "/var/www/html/trading-portal/storage/central_trades.db"
BOTS_CONFIG = {
    'bot1': {
        'db_path': '/home/bot1/trading_bots/trade_history.db',
        'config_path': '/home/bot1/trading_bots/config.json',
    },
    'bot2': {
        'db_path': '/home/bot2/trading_bots/trade_history.db',
        'config_path': '/home/bot2/trading_bots/config.json',
    },
    'bot3': {
        'db_path': '/home/bot3/trading_bots/trade_history.db',
        'config_path': '/home/bot3/trading_bots/config.json',
    },
}

# Sync intervals (in seconds)
DATA_SYNC_INTERVAL = 10  # Sync trades and settings every 10 seconds
PL_UPDATE_INTERVAL = 60  # Update Profit/Loss every 60 seconds

def connect_to_db(db_path):
    """Connect to an SQLite database with error handling."""
    try:
        conn = sqlite3.connect(db_path, timeout=10)
        conn.row_factory = sqlite3.Row  # Return rows as dictionaries
        return conn
    except sqlite3.Error as e:
        logger.error(f"Error connecting to database {db_path}: {e}")
        return None

def fetch_bot_data(bot_id, bot_config):
    """Fetch trades, settings, and API credentials from a bot's database and config file."""
    bot_data = {'active_trade': None, 'trade_history': [], 'settings': None, 'api_key': None, 'api_secret': None}

    # Read config
    try:
        with open(bot_config['config_path'], 'r') as f:
            config = json.load(f)
        bot_data['settings'] = {
            'bot_id': bot_id,
            'bot_name': config.get('bot_name', bot_id),
            'atr_period': config.get('atr_period', 0),
            'atr_ratio': config.get('atr_ratio', 0.0),
            'position_size': config.get('position_size', 0.0),
            'trading_pair': config.get('trading_pair', ''),
            'timeframe': config.get('timeframe', ''),
            'stop_loss_offset': config.get('stop_loss_offset', 0.0),
        }
        bot_data['api_key'] = config.get('binance_api_key', '')
        bot_data['api_secret'] = config.get('binance_api_secret', '')
        if not bot_data['api_key'] or not bot_data['api_secret']:
            logger.error(f"No Binance API credentials found for {bot_id}")
    except Exception as e:
        logger.error(f"Error reading config for {bot_id}: {e}")

    # Read trades from bot's database
    bot_db = connect_to_db(bot_config['db_path'])
    if not bot_db:
        return bot_data

    try:
        cursor = bot_db.cursor()
        # Fetch active trade
        cursor.execute("SELECT * FROM trades WHERE exit_price IS NULL LIMIT 1")
        active_trade = cursor.fetchone()
        if active_trade:
            bot_data['active_trade'] = {
                'bot_id': bot_id,
                'trade_id': active_trade['id'],
                'timestamp': active_trade['timestamp'],
                'trading_pair': active_trade['trading_pair'],
                'timeframe': active_trade['timeframe'],
                'side': active_trade['side'],
                'entry_price': active_trade['entry_price'],
                'size': active_trade['size'],
                'stop_loss': active_trade['stop_loss'],
                'stop_loss_order_id': active_trade['stop_loss_order_id'],
                'order_id': active_trade['order_id'],
                'profit_loss': 0.0,  # Will be updated separately
                'last_updated': datetime.now(timezone.utc).isoformat()
            }

        # Fetch trade history
        cursor.execute("SELECT * FROM trades WHERE exit_price IS NOT NULL ORDER BY id")
        bot_data['trade_history'] = [
            {
                'bot_id': bot_id,
                'trade_id': trade['id'],
                'timestamp': trade['timestamp'],
                'trading_pair': trade['trading_pair'],
                'timeframe': trade['timeframe'],
                'side': trade['side'],
                'entry_price': trade['entry_price'],
                'size': trade['size'],
                'exit_price': trade['exit_price'],
                'stop_loss': trade['stop_loss'],
                'profit_loss': trade['profit_loss'],
                'trend': trade['trend'],
                'order_id': trade['order_id'],
                'stop_loss_order_id': trade['stop_loss_order_id'],
            }
            for trade in cursor.fetchall()
        ]

        bot_db.close()
    except sqlite3.Error as e:
        logger.error(f"Error querying database for {bot_id}: {e}")
        if bot_db:
            bot_db.close()

    return bot_data

def sync_to_central_db(bot_id, bot_data, central_db):
    """Sync bot data to the central database."""
    try:
        cursor = central_db.cursor()

        # Sync bot metadata
        cursor.execute("""
            INSERT OR REPLACE INTO bots (bot_id, bot_name, trading_pair, timeframe, last_sync_timestamp)
            VALUES (?, ?, ?, ?, ?)
        """, (
            bot_id,
            bot_data['settings']['bot_name'],
            bot_data['settings']['trading_pair'],
            bot_data['settings']['timeframe'],
            datetime.now(timezone.utc).isoformat()
        ))

        # Sync settings
        cursor.execute("""
            INSERT OR REPLACE INTO bot_settings (bot_id, bot_name, atr_period, atr_ratio, position_size, trading_pair, timeframe, stop_loss_offset)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        """, (
            bot_id,
            bot_data['settings']['bot_name'],
            bot_data['settings']['atr_period'],
            bot_data['settings']['atr_ratio'],
            bot_data['settings']['position_size'],
            bot_data['settings']['trading_pair'],
            bot_data['settings']['timeframe'],
            bot_data['settings']['stop_loss_offset']
        ))

        # Sync active trade
        existing_trade = cursor.execute("SELECT * FROM active_trades WHERE bot_id = ?", (bot_id,)).fetchone()
        if bot_data['active_trade']:
            # Check if the trade matches the existing one
            trade_matches = existing_trade and (
                existing_trade['trade_id'] == bot_data['active_trade']['trade_id'] and
                existing_trade['side'] == bot_data['active_trade']['side'] and
                existing_trade['entry_price'] == bot_data['active_trade']['entry_price'] and
                existing_trade['size'] == bot_data['active_trade']['size']
            )

            if trade_matches:
                # Update only the fields that might have changed, preserving profit_loss and last_updated
                cursor.execute("""
                    UPDATE active_trades
                    SET timestamp = ?, trading_pair = ?, timeframe = ?, stop_loss = ?, stop_loss_order_id = ?, order_id = ?
                    WHERE bot_id = ?
                """, (
                    bot_data['active_trade']['timestamp'],
                    bot_data['active_trade']['trading_pair'],
                    bot_data['active_trade']['timeframe'],
                    bot_data['active_trade']['stop_loss'],
                    bot_data['active_trade']['stop_loss_order_id'],
                    bot_data['active_trade']['order_id'],
                    bot_id
                ))
            else:
                # Replace the trade if it doesn't match (new trade), resetting profit_loss
                cursor.execute("DELETE FROM active_trades WHERE bot_id = ?", (bot_id,))
                cursor.execute("""
                    INSERT INTO active_trades (bot_id, trade_id, timestamp, trading_pair, timeframe, side, entry_price, size, stop_loss, stop_loss_order_id, order_id, profit_loss, last_updated)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """, (
                    bot_id,
                    bot_data['active_trade']['trade_id'],
                    bot_data['active_trade']['timestamp'],
                    bot_data['active_trade']['trading_pair'],
                    bot_data['active_trade']['timeframe'],
                    bot_data['active_trade']['side'],
                    bot_data['active_trade']['entry_price'],
                    bot_data['active_trade']['size'],
                    bot_data['active_trade']['stop_loss'],
                    bot_data['active_trade']['stop_loss_order_id'],
                    bot_data['active_trade']['order_id'],
                    bot_data['active_trade']['profit_loss'],
                    bot_data['active_trade']['last_updated']
                ))
        else:
            # If no active trade, remove from central database
            cursor.execute("DELETE FROM active_trades WHERE bot_id = ?", (bot_id,))

        # Sync trade history
        cursor.execute("DELETE FROM trade_history WHERE bot_id = ?", (bot_id,))
        for trade in bot_data['trade_history']:
            cursor.execute("""
                INSERT INTO trade_history (bot_id, trade_id, timestamp, trading_pair, timeframe, side, entry_price, size, exit_price, stop_loss, profit_loss, trend, order_id, stop_loss_order_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                trade['bot_id'],
                trade['trade_id'],
                trade['timestamp'],
                trade['trading_pair'],
                trade['timeframe'],
                trade['side'],
                trade['entry_price'],
                trade['size'],
                trade['exit_price'],
                trade['stop_loss'],
                trade['profit_loss'],
                trade['trend'],
                trade['order_id'],
                trade['stop_loss_order_id']
            ))

        central_db.commit()
        logger.info(f"Successfully synced data for {bot_id}")
    except sqlite3.Error as e:
        logger.error(f"Error syncing data for {bot_id} to central database: {e}")
        central_db.rollback()

def update_profit_loss(central_db, bot_data_list):
    """Update Profit/Loss for all active trades using current mark prices."""
    try:
        cursor = central_db.cursor()
        # Gather all active trades and their associated API credentials
        active_trades = []
        for bot_data in bot_data_list:
            if bot_data['active_trade'] and bot_data['api_key'] and bot_data['api_secret']:
                active_trades.append({
                    'bot_id': bot_data['active_trade']['bot_id'],
                    'trading_pair': bot_data['active_trade']['trading_pair'],
                    'side': bot_data['active_trade']['side'],
                    'entry_price': bot_data['active_trade']['entry_price'],
                    'size': bot_data['active_trade']['size'],
                    'api_key': bot_data['api_key'],
                    'api_secret': bot_data['api_secret']
                })

        # Get unique trading pairs
        trading_pairs = set(trade['trading_pair'] for trade in active_trades)

        # Fetch mark prices for all trading pairs, trying each bot's credentials
        mark_prices = {}
        for trading_pair in trading_pairs:
            fetched = False
            for trade in active_trades:
                if trade['trading_pair'] != trading_pair:
                    continue
                try:
                    binance_client = UMFutures(
                        key=trade['api_key'],
                        secret=trade['api_secret'],
                        base_url="https://fapi.binance.com"
                    )
                    price_data = binance_client.mark_price(symbol=trading_pair)
                    mark_prices[trading_pair] = float(price_data['markPrice'])
                    logger.info(f"Fetched mark price for {trading_pair}: {mark_prices[trading_pair]}")
                    fetched = True
                    break
                except Exception as e:
                    logger.error(f"Error fetching mark price for {trading_pair} with {trade['bot_id']} credentials: {e}")
            if not fetched:
                logger.warning(f"Could not fetch mark price for {trading_pair}")

        # Update Profit/Loss for each active trade
        for trade in active_trades:
            bot_id = trade['bot_id']
            trading_pair = trade['trading_pair']
            side = trade['side']
            entry_price = trade['entry_price']
            size = trade['size']

            if trading_pair not in mark_prices:
                logger.warning(f"No mark price available for {trading_pair}")
                continue

            current_price = mark_prices[trading_pair]
            profit_loss = (current_price - entry_price) * size if side == 'LONG' else (entry_price - current_price) * size
            profit_loss = round(profit_loss, 2)

            cursor.execute("""
                UPDATE active_trades
                SET profit_loss = ?, last_updated = ?
                WHERE bot_id = ?
            """, (
                profit_loss,
                datetime.now(timezone.utc).isoformat(),
                bot_id
            ))

        central_db.commit()
        logger.info("Updated Profit/Loss for active trades")
    except sqlite3.Error as e:
        logger.error(f"Error updating Profit/Loss: {e}")
        central_db.rollback()

def main():
    logger.info("Starting bot sync script...")
    central_db = connect_to_db(CENTRAL_DB_PATH)
    if not central_db:
        logger.error("Failed to connect to central database. Exiting.")
        return

    last_pl_update = 0
    try:
        while True:
            # Sync data for each bot
            bot_data_list = []
            for bot_id, bot_config in BOTS_CONFIG.items():
                logger.debug(f"Syncing data for {bot_id}")
                bot_data = fetch_bot_data(bot_id, bot_config)
                bot_data_list.append(bot_data)
                sync_to_central_db(bot_id, bot_data, central_db)

            # Update Profit/Loss every PL_UPDATE_INTERVAL seconds
            current_time = time.time()
            if current_time - last_pl_update >= PL_UPDATE_INTERVAL:
                update_profit_loss(central_db, bot_data_list)
                last_pl_update = current_time

            time.sleep(DATA_SYNC_INTERVAL)
    except KeyboardInterrupt:
        logger.info("Shutting down sync script...")
    finally:
        central_db.close()

if __name__ == "__main__":
    main()
