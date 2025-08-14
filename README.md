# 🤖 Telegram Chat Statistics Bot

A powerful Telegram bot built with **aiogram 3.x** that automatically collects and analyzes statistics from your Telegram chats. Track message activity, user engagement, and chat performance metrics in real-time.

## ✨ Features

- **📊 Automatic Statistics Collection**: Tracks all messages, users, and chat activity automatically
- **👥 User Analytics**: Monitor individual user participation and engagement
- **📈 Activity Metrics**: Daily activity charts and message type distribution
- **🔄 Real-time Updates**: Statistics update in real-time as messages are sent
- **💾 Local Storage**: All data stored locally in SQLite database
- **🔒 Privacy Focused**: Only stores message metadata, not content
- **📱 Interactive Interface**: Rich inline keyboards and formatted statistics
- **⚡ High Performance**: Built with async/await for optimal performance

## 🚀 Quick Start

### 1. Prerequisites

- Python 3.8+
- Telegram Bot Token (get from [@BotFather](https://t.me/BotFather))

### 2. Installation

```bash
# Clone the repository
git clone <your-repo-url>
cd telegram-chat-statistics-bot

# Install dependencies
pip install -r requirements.txt

# Copy environment file
cp .env.example .env
```

### 3. Configuration

Edit `.env` file with your bot token:

```bash
BOT_TOKEN=your_actual_bot_token_here
```

### 4. Run the Bot

```bash
# Start in polling mode (recommended for development)
python main.py

# Or start in webhook mode (for production)
WEBHOOK_URL=https://yourdomain.com/webhook python main.py
```

## 📋 Commands

| Command | Description |
|---------|-------------|
| `/start` | Welcome message and bot introduction |
| `/stats` | View current chat statistics |
| `/mystats` | View your personal statistics |
| `/chatstats <id>` | View statistics for specific chat by ID |
| `/help` | Show help information |

## 🗄️ Database Schema

The bot uses SQLite with the following tables:

- **`chats`**: Chat information and metadata
- **`messages`**: Individual message records
- **`user_stats`**: Per-user statistics per chat
- **`chat_stats`**: Aggregated chat statistics

## 🔧 Configuration Options

| Variable | Default | Description |
|----------|---------|-------------|
| `BOT_TOKEN` | Required | Your Telegram bot token |
| `DATABASE_PATH` | `chat_statistics.db` | SQLite database file path |
| `MAX_MESSAGES_PER_CHAT` | `10000` | Maximum messages to store per chat |
| `STATS_UPDATE_INTERVAL` | `300` | Cleanup interval in seconds |

## 📊 Statistics Collected

### Chat Metrics
- Total message count
- Unique user count
- Active users (last 7 days)
- Last activity timestamp
- Chat creation date

### User Metrics
- Message count per chat
- First and last message timestamps
- Cross-chat participation
- Activity ranking

### Message Analytics
- Message type distribution (text, photo, video, etc.)
- Daily activity patterns
- User contribution rankings

## 🏗️ Architecture

```
├── main.py              # Main bot application
├── handlers.py          # Command and message handlers
├── database.py          # Database operations
├── config.py            # Configuration management
├── requirements.txt     # Python dependencies
└── .env                 # Environment variables
```

## 🚀 Deployment

### Development (Polling Mode)
```bash
python main.py
```

### Production (Webhook Mode)
1. Set up a web server (nginx, Apache)
2. Configure SSL certificate
3. Set `WEBHOOK_URL` in environment
4. Run with webhook mode

### Docker Deployment
```dockerfile
FROM python:3.9-slim
WORKDIR /app
COPY requirements.txt .
RUN pip install -r requirements.txt
COPY . .
CMD ["python", "main.py"]
```

## 🔒 Privacy & Security

- **No Message Content**: Only metadata is stored (timestamps, user IDs, message types)
- **Local Storage**: All data stays on your server
- **User Consent**: Users can request their data or opt out
- **Data Retention**: Configurable message retention limits

## 📈 Usage Examples

### View Chat Statistics
```
/stats
```
Shows comprehensive statistics for the current chat including:
- Total messages and users
- Top contributors
- Message type distribution
- Activity metrics

### Personal Statistics
```
/mystats
```
Displays your activity across all tracked chats:
- Total message count
- Chats participated
- Activity breakdown

### Specific Chat Analysis
```
/chatstats 123456789
```
Analyze any chat by providing its ID.

## 🛠️ Development

### Adding New Features
1. Extend the `ChatStatisticsDB` class in `database.py`
2. Add new handlers in `handlers.py`
3. Update the main bot class in `main.py`

### Testing
```bash
# Run with debug logging
python -m logging -l DEBUG main.py
```

### Database Management
```bash
# View database contents
sqlite3 chat_statistics.db ".tables"
sqlite3 chat_statistics.db "SELECT * FROM chats LIMIT 5;"
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

- **Issues**: Create a GitHub issue
- **Documentation**: Check the code comments and this README
- **Telegram**: Contact the bot developer

## 🔮 Future Features

- [ ] Export statistics to CSV/Excel
- [ ] Advanced analytics and charts
- [ ] Custom time period analysis
- [ ] User behavior insights
- [ ] Chat comparison tools
- [ ] Automated reports
- [ ] Integration with external analytics

---

**Made with ❤️ using aiogram 3.x**