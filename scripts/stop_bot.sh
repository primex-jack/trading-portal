#!/bin/bash

if [ $# -ne 1 ]; then
    echo "Usage: $0 <bot_id>"
    exit 1
fi

BOT_ID=$1
PID_FILE="/home/$BOT_ID/trading_bots/$BOT_ID.pid"

if [ ! -f "$PID_FILE" ]; then
    echo "No PID file found for $BOT_ID. Bot may already be stopped."
    exit 0
fi

PID=$(cat "$PID_FILE")
if ps -p "$PID" > /dev/null; then
    kill "$PID"
    echo "Stopped bot $BOT_ID (PID: $PID)"
else
    echo "Bot $BOT_ID (PID: $PID) is not running."
fi

rm -f "$PID_FILE"
echo "Removed PID file for $BOT_ID"
