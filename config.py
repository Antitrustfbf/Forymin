import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Bot configuration
BOT_TOKEN = os.getenv('BOT_TOKEN')
if not BOT_TOKEN:
    raise ValueError("BOT_TOKEN environment variable is not set")

# Database configuration
DATABASE_PATH = "chat_statistics.db"

# Bot settings
BOT_USERNAME = os.getenv('BOT_USERNAME', '')
WEBHOOK_URL = os.getenv('WEBHOOK_URL', '')

# Statistics settings
MAX_MESSAGES_PER_CHAT = 10000  # Maximum messages to store per chat
STATS_UPDATE_INTERVAL = 300  # Update stats every 5 minutes