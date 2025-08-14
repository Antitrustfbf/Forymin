import asyncio
import logging
from aiogram import Bot, Dispatcher
from aiogram.fsm.storage.memory import MemoryStorage
from aiogram.webhook.aiohttp import SimpleRequestHandler, setup_application
from aiohttp import web
import os
from datetime import datetime, timedelta

from config import BOT_TOKEN, DATABASE_PATH, WEBHOOK_URL, STATS_UPDATE_INTERVAL, MAX_MESSAGES_PER_CHAT
from database import ChatStatisticsDB
from handlers import router

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('bot.log'),
        logging.StreamHandler()
    ]
)

logger = logging.getLogger(__name__)

class ChatStatisticsBot:
    def __init__(self):
        self.bot = Bot(token=BOT_TOKEN)
        self.storage = MemoryStorage()
        self.dp = Dispatcher(storage=self.storage)
        self.db = ChatStatisticsDB(DATABASE_PATH)
        
        # Setup handlers
        self.dp.include_router(router)
        
        # Setup middleware
        self.setup_middleware()
        
        # Setup periodic tasks
        self.setup_periodic_tasks()
    
    def setup_middleware(self):
        """Setup middleware for dependency injection"""
        from aiogram.fsm.middleware import BaseMiddleware
        from typing import Any, Awaitable, Callable, Dict
        
        class DatabaseMiddleware(BaseMiddleware):
            def __init__(self, database: ChatStatisticsDB):
                super().__init__()
                self.database = database
            
            async def __call__(
                self,
                handler: Callable[[Any, Dict[str, Any]], Awaitable[Any]],
                event: Any,
                data: Dict[str, Any]
            ) -> Any:
                # Inject database into handler
                data["db"] = self.database
                return await handler(event, data)
        
        self.dp.message.middleware(DatabaseMiddleware(self.db))
        self.dp.callback_query.middleware(DatabaseMiddleware(self.db))
    
    def setup_periodic_tasks(self):
        """Setup periodic tasks for maintenance"""
        async def cleanup_old_messages():
            """Periodically clean up old messages to prevent database bloat"""
            while True:
                try:
                    await asyncio.sleep(STATS_UPDATE_INTERVAL)
                    await self.db.cleanup_old_messages(MAX_MESSAGES_PER_CHAT)
                    logger.info("Periodic cleanup completed")
                except Exception as e:
                    logger.error(f"Error during periodic cleanup: {e}")
        
        # Start periodic task
        asyncio.create_task(cleanup_old_messages())
    
    async def on_startup(self, webhook_url: str = None):
        """Bot startup handler"""
        logger.info("Starting Chat Statistics Bot...")
        
        if webhook_url:
            # Setup webhook
            await self.bot.set_webhook(url=webhook_url)
            logger.info(f"Webhook set to {webhook_url}")
        else:
            # Delete webhook for polling
            await self.bot.delete_webhook()
            logger.info("Webhook deleted, using polling mode")
        
        # Test database connection
        try:
            await self.db.add_chat(
                chat_id=0,
                chat_type="test",
                title="Test Chat"
            )
            logger.info("Database connection test successful")
        except Exception as e:
            logger.error(f"Database connection test failed: {e}")
            raise
        
        logger.info("Bot started successfully!")
    
    async def on_shutdown(self):
        """Bot shutdown handler"""
        logger.info("Shutting down Chat Statistics Bot...")
        
        # Close bot session
        await self.bot.session.close()
        
        # Close database connections
        # SQLite connections are automatically closed, but we can add cleanup here if needed
        
        logger.info("Bot shutdown complete")
    
    async def start_polling(self):
        """Start bot in polling mode"""
        try:
            await self.on_startup()
            await self.dp.start_polling(self.bot)
        except Exception as e:
            logger.error(f"Error during polling: {e}")
        finally:
            await self.on_shutdown()
    
    async def start_webhook(self, webhook_url: str, webhook_path: str = "/webhook"):
        """Start bot in webhook mode"""
        try:
            await self.on_startup(webhook_url)
            
            # Setup webhook handler
            app = web.Application()
            webhook_handler = SimpleRequestHandler(
                dispatcher=self.dp,
                bot=self.bot
            )
            webhook_handler.register(app, path=webhook_path)
            
            # Setup application
            setup_application(app, self.dp, bot=self.bot)
            
            # Start webhook
            runner = web.AppRunner(app)
            await runner.setup()
            
            site = web.TCPSite(runner, "0.0.0.0", 8000)
            await site.start()
            
            logger.info(f"Webhook started on port 8000, path: {webhook_path}")
            
            # Keep running
            while True:
                await asyncio.sleep(3600)  # Sleep for 1 hour
                
        except Exception as e:
            logger.error(f"Error during webhook: {e}")
        finally:
            await self.on_shutdown()

async def main():
    """Main function"""
    bot = ChatStatisticsBot()
    
    # Check if webhook URL is configured
    if WEBHOOK_URL:
        logger.info("Starting bot in webhook mode")
        await bot.start_webhook(WEBHOOK_URL)
    else:
        logger.info("Starting bot in polling mode")
        await bot.start_polling()

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        logger.info("Bot stopped by user")
    except Exception as e:
        logger.error(f"Unexpected error: {e}")