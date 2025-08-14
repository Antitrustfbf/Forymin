from aiogram import Router, F
from aiogram.types import Message, CallbackQuery
from aiogram.filters import Command, CommandStart
from aiogram.fsm.context import FSMContext
from aiogram.fsm.state import State, StatesGroup
from aiogram.utils.keyboard import InlineKeyboardBuilder
from aiogram.enums import ParseMode
import logging
from datetime import datetime
from typing import Union

from database import ChatStatisticsDB
from config import MAX_MESSAGES_PER_CHAT

logger = logging.getLogger(__name__)
router = Router()

class StatsStates(StatesGroup):
    waiting_for_chat_id = State()

@router.message(CommandStart())
async def cmd_start(message: Message):
    """Handle /start command"""
    welcome_text = (
        "🤖 **Chat Statistics Bot**\n\n"
        "I help you collect and analyze statistics from your Telegram chats!\n\n"
        "**Available commands:**\n"
        "/stats - Get statistics for current chat\n"
        "/mystats - Get your personal statistics\n"
        "/chatstats <chat_id> - Get statistics for specific chat\n"
        "/help - Show this help message\n\n"
        "**Features:**\n"
        "• Automatic message tracking\n"
        "• User activity analysis\n"
        "• Chat performance metrics\n"
        "• Daily activity charts\n\n"
        "Add me to your chats to start collecting statistics!"
    )
    
    await message.answer(welcome_text, parse_mode=ParseMode.MARKDOWN)

@router.message(Command("help"))
async def cmd_help(message: Message):
    """Handle /help command"""
    help_text = (
        "📊 **Chat Statistics Bot Help**\n\n"
        "**Commands:**\n"
        "• `/stats` - Get current chat statistics\n"
        "• `/mystats` - Get your personal statistics\n"
        "• `/chatstats <chat_id>` - Get stats for specific chat\n"
        "• `/help` - Show this help message\n\n"
        "**How it works:**\n"
        "1. Add me to your chat\n"
        "2. I'll automatically track all messages\n"
        "3. Use commands to view statistics\n"
        "4. Get insights about chat activity\n\n"
        "**Privacy:**\n"
        "I only store message metadata, not content.\n"
        "All data is stored locally and securely."
    )
    
    await message.answer(help_text, parse_mode=ParseMode.MARKDOWN)

@router.message(Command("stats"))
async def cmd_stats(message: Message, db: ChatStatisticsDB):
    """Handle /stats command - show current chat statistics"""
    chat_id = message.chat.id
    
    # Check if it's a private chat
    if message.chat.type == "private":
        await message.answer(
            "❌ This command only works in group chats or channels.\n"
            "Add me to a group to collect statistics!"
        )
        return
    
    # Get chat statistics
    stats = await db.get_chat_statistics(chat_id)
    
    if not stats:
        await message.answer(
            "📊 No statistics available for this chat yet.\n"
            "I'll start collecting data from now on!"
        )
        return
    
    # Format statistics
    chat_info = stats['chat_info']
    chat_stats = stats['chat_stats']
    
    stats_text = (
        f"📊 **Chat Statistics: {chat_info['title'] or 'Unknown Chat'}**\n\n"
        f"**General Info:**\n"
        f"• Chat ID: `{chat_info['chat_id']}`\n"
        f"• Type: {chat_info['chat_type'].title()}\n"
        f"• Created: {chat_info['created_at'][:10] if chat_info['created_at'] else 'Unknown'}\n\n"
        f"**Activity Metrics:**\n"
        f"• Total Messages: **{chat_stats['total_messages']:,}**\n"
        f"• Total Users: **{chat_stats['total_users']}**\n"
        f"• Active Users (7 days): **{chat_stats['active_users']}**\n"
        f"• Last Activity: {chat_stats['last_activity'][:16] if chat_stats['last_activity'] else 'Never'}\n\n"
    )
    
    # Add top users
    if stats['top_users']:
        stats_text += "**🏆 Top Contributors:**\n"
        for i, user in enumerate(stats['top_users'][:5], 1):
            username = user['username'] or user['first_name'] or f"User{user['user_id']}"
            stats_text += f"{i}. {username}: **{user['message_count']}** messages\n"
        stats_text += "\n"
    
    # Add message types
    if stats['message_types']:
        stats_text += "**📝 Message Types:**\n"
        for msg_type in stats['message_types']:
            stats_text += f"• {msg_type['type'].title()}: {msg_type['count']}\n"
    
    # Create inline keyboard for more options
    builder = InlineKeyboardBuilder()
    builder.button(text="📈 Daily Activity", callback_data=f"daily_activity_{chat_id}")
    builder.button(text="👥 User Details", callback_data=f"user_details_{chat_id}")
    builder.button(text="🔄 Refresh", callback_data=f"refresh_stats_{chat_id}")
    
    await message.answer(
        stats_text, 
        parse_mode=ParseMode.MARKDOWN,
        reply_markup=builder.as_markup()
    )

@router.message(Command("mystats"))
async def cmd_mystats(message: Message, db: ChatStatisticsDB):
    """Handle /mystats command - show user's personal statistics"""
    user_id = message.from_user.id
    
    # Get user statistics across all chats
    user_stats = await db.get_user_statistics(user_id)
    
    if not user_stats or user_stats['total_messages'] == 0:
        await message.answer(
            "📊 You don't have any message statistics yet.\n"
            "Start chatting in groups where I'm present to collect data!"
        )
        return
    
    # Format user statistics
    stats_text = (
        f"👤 **Your Statistics**\n\n"
        f"**Overall Activity:**\n"
        f"• Total Messages: **{user_stats['total_messages']:,}**\n"
        f"• Chats Participated: **{user_stats['chats_participated']}**\n\n"
    )
    
    # Add chat-specific stats
    if user_stats['chat_stats']:
        stats_text += "**📱 Activity by Chat:**\n"
        for chat_stat in user_stats['chat_stats'][:5]:  # Show top 5 chats
            stats_text += (
                f"• Chat {chat_stat['chat_id']}: "
                f"**{chat_stat['message_count']}** messages\n"
            )
        
        if len(user_stats['chat_stats']) > 5:
            stats_text += f"... and {len(user_stats['chat_stats']) - 5} more chats\n"
    
    await message.answer(stats_text, parse_mode=ParseMode.MARKDOWN)

@router.message(Command("chatstats"))
async def cmd_chatstats(message: Message, state: FSMContext, db: ChatStatisticsDB):
    """Handle /chatstats command - request chat ID for statistics"""
    args = message.text.split()
    
    if len(args) == 2:
        try:
            chat_id = int(args[1])
            await show_chat_stats(message, chat_id, db)
        except ValueError:
            await message.answer(
                "❌ Invalid chat ID. Please provide a valid numeric chat ID.\n"
                "Example: `/chatstats 123456789`",
                parse_mode=ParseMode.MARKDOWN
            )
    else:
        await message.answer(
            "📊 **Chat Statistics by ID**\n\n"
            "Please provide the chat ID you want to analyze.\n"
            "Example: `/chatstats 123456789`\n\n"
            "**How to find chat ID:**\n"
            "1. Forward a message from the chat to @userinfobot\n"
            "2. Or use @RawDataBot in the target chat\n"
            "3. Look for the 'chat' -> 'id' field",
            parse_mode=ParseMode.MARKDOWN
        )

async def show_chat_stats(message: Message, chat_id: int, db: ChatStatisticsDB):
    """Show statistics for a specific chat ID"""
    stats = await db.get_chat_statistics(chat_id)
    
    if not stats:
        await message.answer(
            f"❌ No statistics available for chat ID `{chat_id}`.\n"
            "This chat might not be tracked by the bot yet.",
            parse_mode=ParseMode.MARKDOWN
        )
        return
    
    # Format statistics (similar to cmd_stats)
    chat_info = stats['chat_info']
    chat_stats = stats['chat_stats']
    
    stats_text = (
        f"📊 **Chat Statistics: {chat_info['title'] or f'Chat {chat_id}'}**\n\n"
        f"**General Info:**\n"
        f"• Chat ID: `{chat_info['chat_id']}`\n"
        f"• Type: {chat_info['chat_type'].title()}\n"
        f"• Created: {chat_info['created_at'][:10] if chat_info['created_at'] else 'Unknown'}\n\n"
        f"**Activity Metrics:**\n"
        f"• Total Messages: **{chat_stats['total_messages']:,}**\n"
        f"• Total Users: **{chat_stats['total_users']}**\n"
        f"• Active Users (7 days): **{chat_stats['active_users']}**\n"
        f"• Last Activity: {chat_stats['last_activity'][:16] if chat_stats['last_activity'] else 'Never'}\n\n"
    )
    
    # Add top users
    if stats['top_users']:
        stats_text += "**🏆 Top Contributors:**\n"
        for i, user in enumerate(stats['top_users'][:5], 1):
            username = user['username'] or user['first_name'] or f"User{user['user_id']}"
            stats_text += f"{i}. {username}: **{user['message_count']}** messages\n"
    
    await message.answer(stats_text, parse_mode=ParseMode.MARKDOWN)

@router.callback_query(F.data.startswith("daily_activity_"))
async def show_daily_activity(callback: CallbackQuery, db: ChatStatisticsDB):
    """Show daily activity chart for a chat"""
    chat_id = int(callback.data.split("_")[-1])
    stats = await db.get_chat_statistics(chat_id)
    
    if not stats or not stats['daily_activity']:
        await callback.answer("No daily activity data available", show_alert=True)
        return
    
    # Create simple text-based chart
    activity_text = f"📈 **Daily Activity - Last 7 Days**\n\n"
    
    for day_data in stats['daily_activity']:
        date = day_data['date']
        count = day_data['count']
        bar = "█" * min(count, 20)  # Simple bar chart
        activity_text += f"{date}: {bar} ({count})\n"
    
    await callback.message.edit_text(
        activity_text,
        parse_mode=ParseMode.MARKDOWN
    )

@router.callback_query(F.data.startswith("user_details_"))
async def show_user_details(callback: CallbackQuery, db: ChatStatisticsDB):
    """Show detailed user information for a chat"""
    chat_id = int(callback.data.split("_")[-1])
    stats = await db.get_chat_statistics(chat_id)
    
    if not stats or not stats['top_users']:
        await callback.answer("No user data available", show_alert=True)
        return
    
    users_text = f"👥 **Top Users in Chat**\n\n"
    
    for i, user in enumerate(stats['top_users'], 1):
        username = user['username'] or user['first_name'] or f"User{user['user_id']}"
        first_msg = user['first_message'][:10] if user['first_message'] else 'Unknown'
        last_msg = user['last_message'][:10] if user['last_message'] else 'Unknown'
        
        users_text += (
            f"**{i}. {username}**\n"
            f"   Messages: {user['message_count']}\n"
            f"   First: {first_msg} | Last: {last_msg}\n\n"
        )
    
    # Add back button
    builder = InlineKeyboardBuilder()
    builder.button(text="🔙 Back to Stats", callback_data=f"back_to_stats_{chat_id}")
    
    await callback.message.edit_text(
        users_text,
        parse_mode=ParseMode.MARKDOWN,
        reply_markup=builder.as_markup()
    )

@router.callback_query(F.data.startswith("back_to_stats_"))
async def back_to_stats(callback: CallbackQuery, db: ChatStatisticsDB):
    """Go back to main statistics view"""
    chat_id = int(callback.data.split("_")[-1])
    stats = await db.get_chat_statistics(chat_id)
    
    if not stats:
        await callback.answer("Statistics not available", show_alert=True)
        return
    
    # Create a mock message object for the stats command
    class MockMessage:
        def __init__(self, chat_id):
            self.chat = type('Chat', (), {'id': chat_id, 'type': 'group'})()
    
    mock_message = MockMessage(chat_id)
    await cmd_stats(mock_message, db)

@router.callback_query(F.data.startswith("refresh_stats_"))
async def refresh_stats(callback: CallbackQuery, db: ChatStatisticsDB):
    """Refresh statistics for a chat"""
    chat_id = int(callback.data.split("_")[-1])
    await callback.answer("Refreshing statistics...")
    
    # Create a mock message object for the stats command
    class MockMessage:
        def __init__(self, chat_id):
            self.chat = type('Chat', (), {'id': chat_id, 'type': 'group'})()
    
    mock_message = MockMessage(chat_id)
    await cmd_stats(mock_message, db)

@router.message()
async def handle_message(message: Message, db: ChatStatisticsDB):
    """Handle all incoming messages to collect statistics"""
    try:
        # Skip bot messages and service messages
        if message.from_user.is_bot or not message.from_user:
            return
        
        chat_id = message.chat.id
        user_id = message.from_user.id
        
        # Determine message type
        message_type = "text"
        message_text = None
        
        if message.text:
            message_type = "text"
            message_text = message.text[:100]  # Store first 100 chars
        elif message.photo:
            message_type = "photo"
        elif message.video:
            message_type = "video"
        elif message.audio:
            message_type = "audio"
        elif message.document:
            message_type = "document"
        elif message.voice:
            message_type = "voice"
        elif message.sticker:
            message_type = "sticker"
        elif message.animation:
            message_type = "animation"
        else:
            message_type = "other"
        
        # Add chat to database
        await db.add_chat(
            chat_id=chat_id,
            chat_type=message.chat.type,
            title=message.chat.title,
            username=message.chat.username,
            first_name=message.chat.first_name,
            last_name=message.chat.last_name
        )
        
        # Add message to database
        await db.add_message(
            message_id=message.message_id,
            chat_id=chat_id,
            user_id=user_id,
            username=message.from_user.username,
            first_name=message.from_user.first_name,
            last_name=message.from_user.last_name,
            message_type=message_type,
            message_text=message_text
        )
        
        logger.debug(f"Message {message.message_id} from user {user_id} in chat {chat_id} processed")
        
    except Exception as e:
        logger.error(f"Error processing message: {e}")

@router.message(F.new_chat_members)
async def handle_new_chat_members(message: Message, db: ChatStatisticsDB):
    """Handle new chat members (including bot being added to chats)"""
    try:
        chat_id = message.chat.id
        
        # Check if bot was added
        for new_member in message.new_chat_members:
            if new_member.is_bot and new_member.username == "your_bot_username":  # Replace with actual username
                await message.answer(
                    "🤖 **Chat Statistics Bot Activated!**\n\n"
                    "I'm now tracking messages in this chat to collect statistics.\n\n"
                    "**Available commands:**\n"
                    "• `/stats` - View chat statistics\n"
                    "• `/mystats` - View your personal stats\n"
                    "• `/help` - Show help information\n\n"
                    "Start chatting and I'll collect data automatically!"
                )
                break
        
        # Add chat to database
        await db.add_chat(
            chat_id=chat_id,
            chat_type=message.chat.type,
            title=message.chat.title,
            username=message.chat.username,
            first_name=message.chat.first_name,
            last_name=message.chat.last_name
        )
        
    except Exception as e:
        logger.error(f"Error handling new chat members: {e}")

@router.message(F.left_chat_member)
async def handle_left_chat_member(message: Message, db: ChatStatisticsDB):
    """Handle when members leave the chat"""
    try:
        # Update chat stats when members leave
        chat_id = message.chat.id
        
        # You could add logic here to update user statistics
        # For now, we'll just log the event
        logger.info(f"User left chat {chat_id}: {message.left_chat_member.id}")
        
    except Exception as e:
        logger.error(f"Error handling left chat member: {e}")