#!/bin/bash

# Telegram Chat Statistics Bot Startup Script

echo "🤖 Starting Telegram Chat Statistics Bot..."

# Check if Python is installed
if ! command -v python3 &> /dev/null; then
    echo "❌ Python 3 is not installed. Please install Python 3.8+ first."
    exit 1
fi

# Check if .env file exists
if [ ! -f .env ]; then
    echo "⚠️  .env file not found. Creating from template..."
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "✅ .env file created from template."
        echo "   Please edit .env and add your bot token before running again."
        exit 1
    else
        echo "❌ .env.example not found. Please create .env file manually."
        exit 1
    fi
fi

# Check if requirements are installed
if [ ! -d "venv" ] && [ ! -d ".venv" ]; then
    echo "🔧 Setting up virtual environment..."
    python3 -m venv venv
    source venv/bin/activate
    pip install -r requirements.txt
    echo "✅ Virtual environment setup complete."
else
    echo "🔧 Activating virtual environment..."
    if [ -d "venv" ]; then
        source venv/bin/activate
    else
        source .venv/bin/activate
    fi
fi

# Run tests first
echo "🧪 Running setup tests..."
python3 test_bot.py
if [ $? -ne 0 ]; then
    echo "❌ Tests failed. Please fix the issues before starting the bot."
    exit 1
fi

echo "✅ Tests passed. Starting bot..."

# Start the bot
python3 main.py