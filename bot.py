import logging
import asyncio
import re
from datetime import datetime, timedelta
from typing import Dict, Optional

from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup, ChatMember
from telegram.ext import (
    Application, CommandHandler, MessageHandler, CallbackQueryHandler,
    ContextTypes, filters
)
from telegram.constants import ParseMode

from config import BOT_TOKEN, RUSSIAN_TEXTS, DEFAULT_CHAT_SETTINGS
from database import DatabaseManager
from moderation import ModerationManager

# Настройка логирования
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)
logger = logging.getLogger(__name__)

class TelegramBot:
    def __init__(self):
        self.db = DatabaseManager()
        self.moderation = ModerationManager()
        self.application = Application.builder().token(BOT_TOKEN).build()
        self.setup_handlers()
        
        # Запускаем периодическую очистку данных
        asyncio.create_task(self.periodic_cleanup())
    
    def setup_handlers(self):
        """Настройка обработчиков команд и сообщений"""
        
        # Основные команды
        self.application.add_handler(CommandHandler("start", self.start_command))
        self.application.add_handler(CommandHandler("help", self.help_command))
        self.application.add_handler(CommandHandler("rules", self.rules_command))
        self.application.add_handler(CommandHandler("profile", self.profile_command))
        
        # Административные команды
        self.application.add_handler(CommandHandler("admin", self.admin_command))
        self.application.add_handler(CommandHandler("settings", self.settings_command))
        self.application.add_handler(CommandHandler("statistics", self.statistics_command))
        self.application.add_handler(CommandHandler("backup", self.backup_command))
        
        # Команды модерации
        self.application.add_handler(CommandHandler("warn", self.warn_command))
        self.application.add_handler(CommandHandler("ban", self.ban_command))
        self.application.add_handler(CommandHandler("unban", self.unban_command))
        self.application.add_handler(CommandHandler("mute", self.mute_command))
        self.application.add_handler(CommandHandler("unmute", self.unmute_command))
        
        # Настройки модерации
        self.application.add_handler(CommandHandler("auto_delete", self.auto_delete_command))
        self.application.add_handler(CommandHandler("anti_spam", self.anti_spam_command))
        self.application.add_handler(CommandHandler("welcome_message", self.welcome_message_command))
        self.application.add_handler(CommandHandler("rules_auto", self.rules_auto_command))
        
        # Обработчики callback кнопок
        self.application.add_handler(CallbackQueryHandler(self.button_callback))
        
        # Обработчик всех сообщений для модерации
        self.application.add_handler(MessageHandler(filters.ALL, self.handle_message))
        
        # Обработчик новых участников
        self.application.add_handler(MessageHandler(filters.StatusUpdate.NEW_CHAT_MEMBERS, self.handle_new_member))
        
        # Обработчик выхода участников
        self.application.add_handler(MessageHandler(filters.StatusUpdate.LEFT_CHAT_MEMBER, self.handle_member_left))
    
    async def start_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик команды /start"""
        chat = update.effective_chat
        user = update.effective_user
        
        # Добавляем чат в базу данных
        self.db.add_chat(
            chat_id=chat.id,
            chat_type=chat.type,
            title=chat.title or chat.username or str(chat.id),
            username=chat.username
        )
        
        # Добавляем пользователя
        self.db.add_user(
            user_id=user.id,
            chat_id=chat.id,
            username=user.username,
            first_name=user.first_name,
            last_name=user.last_name
        )
        
        # Проверяем, является ли пользователь администратором
        is_admin = await self.is_user_admin(chat.id, user.id)
        if is_admin:
            role = 'admin'
        else:
            role = 'member'
        
        self.db.update_user_role(user.id, chat.id, role)
        
        # Отправляем приветственное сообщение
        welcome_text = RUSSIAN_TEXTS['welcome']
        if chat.type != 'private':
            welcome_text += f"\n\nЧат: {chat.title}"
        
        await update.message.reply_text(welcome_text, parse_mode=ParseMode.MARKDOWN)
    
    async def help_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик команды /help"""
        await update.message.reply_text(
            RUSSIAN_TEXTS['help'],
            parse_mode=ParseMode.MARKDOWN
        )
    
    async def rules_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик команды /rules"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        rules = settings.get('rules', DEFAULT_CHAT_SETTINGS['rules'])
        await update.message.reply_text(
            f"📋 **Правила чата:**\n\n{rules}",
            parse_mode=ParseMode.MARKDOWN
        )
    
    async def profile_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик команды /profile"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        
        user_info = self.db.get_user(user_id, chat_id)
        if not user_info:
            await update.message.reply_text("❌ Информация о пользователе не найдена.")
            return
        
        profile_text = f"""👤 **Ваш профиль:**

🆔 ID: `{user_info['user_id']}`
👤 Имя: {user_info['first_name']}
📛 Роль: {user_info['role']}
⚠️ Предупреждения: {user_info['warnings']}
🚫 Заблокирован: {'Да' if user_info['is_banned'] else 'Нет'}
🔇 Замучен: {'Да' if user_info['is_muted'] else 'Нет'}
📅 Дата вступления: {user_info['join_date']}
🕐 Последняя активность: {user_info['last_activity']}"""
        
        await update.message.reply_text(profile_text, parse_mode=ParseMode.MARKDOWN)
    
    async def admin_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик команды /admin"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        
        # Проверяем права администратора
        if not await self.is_user_admin(chat_id, user_id):
            await update.message.reply_text(RUSSIAN_TEXTS['not_admin'])
            return
        
        # Создаем клавиатуру администратора
        keyboard = [
            [InlineKeyboardButton("🔧 Настройки чата", callback_data="admin_settings")],
            [InlineKeyboardButton("👥 Управление пользователями", callback_data="admin_users")],
            [InlineKeyboardButton("📊 Статистика", callback_data="admin_stats")],
            [InlineKeyboardButton("🛡️ Модерация", callback_data="admin_moderation")],
            [InlineKeyboardButton("📝 Логи действий", callback_data="admin_logs")]
        ]
        
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(
            RUSSIAN_TEXTS['admin_panel'],
            reply_markup=reply_markup,
            parse_mode=ParseMode.MARKDOWN
        )
    
    async def settings_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик команды /settings"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        
        if not await self.is_user_admin(chat_id, user_id):
            await update.message.reply_text(RUSSIAN_TEXTS['not_admin'])
            return
        
        settings = self.db.get_chat_settings(chat_id)
        
        settings_text = f"""⚙️ **Настройки чата:**

🚫 Автоудаление: {'Включено' if settings.get('auto_delete') else 'Отключено'}
🔄 Антиспам: {'Включен' if settings.get('anti_spam') else 'Отключен'}
⚠️ Макс. предупреждения: {settings.get('max_warnings', 3)}
📝 Логирование: {'Включено' if settings.get('log_actions') else 'Отключено'}
🤖 Автомодерация: {'Включена' if settings.get('auto_moderation') else 'Отключена'}"""
        
        keyboard = [
            [InlineKeyboardButton("🔧 Изменить настройки", callback_data="edit_settings")],
            [InlineKeyboardButton("🛡️ Настройки модерации", callback_data="moderation_settings")],
            [InlineKeyboardButton("🔙 Назад", callback_data="admin_back")]
        ]
        
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(
            settings_text,
            reply_markup=reply_markup,
            parse_mode=ParseMode.MARKDOWN
        )
    
    async def statistics_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик команды /statistics"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        
        if not await self.is_user_admin(chat_id, user_id):
            await update.message.reply_text(RUSSIAN_TEXTS['not_admin'])
            return
        
        stats = self.db.get_chat_statistics(chat_id)
        mod_stats = self.moderation.get_moderation_stats(chat_id)
        
        stats_text = f"""📊 **Статистика чата:**

👥 Всего пользователей: {stats.get('total_users', 0)}
🚫 Заблокированных: {stats.get('banned_users', 0)}
🔇 Замученных: {stats.get('muted_users', 0)}

🛡️ **Модерация (за {stats.get('period_days', 7)} дней):**
⚠️ Предупреждения: {stats.get('moderation_actions', {}).get('warning', 0)}
🚫 Баны: {stats.get('moderation_actions', {}).get('ban', 0)}
🔇 Муты: {stats.get('moderation_actions', {}).get('mute', 0)}

⚙️ **Настройки модерации:**
🔄 Порог спама: {mod_stats['settings']['spam_threshold']} сообщ./мин
📝 Порог флуда: {mod_stats['settings']['flood_threshold']} повт.
🤖 Автомут: {'Включен' if mod_stats['settings']['auto_mute'] else 'Отключен'}"""
        
        await update.message.reply_text(stats_text, parse_mode=ParseMode.MARKDOWN)
    
    async def warn_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик команды /warn"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        
        if not await self.is_user_admin(chat_id, user_id):
            await update.message.reply_text(RUSSIAN_TEXTS['not_admin'])
            return
        
        if not context.args:
            await update.message.reply_text("❌ Укажите пользователя: /warn @username [причина]")
            return
        
        # Извлекаем username и причину
        target_username = context.args[0].lstrip('@')
        reason = ' '.join(context.args[1:]) if len(context.args) > 1 else None
        
        # Находим пользователя по username
        target_user = await self.find_user_by_username(chat_id, target_username)
        if not target_user:
            await update.message.reply_text(RUSSIAN_TEXTS['user_not_found'])
            return
        
        # Добавляем предупреждение
        if self.db.add_warning(target_user.id, chat_id, user_id, reason):
            reason_text = f" по причине: {reason}" if reason else ""
            await update.message.reply_text(
                f"⚠️ Пользователь @{target_username} получил предупреждение{reason_text}",
                parse_mode=ParseMode.MARKDOWN
            )
            
            # Уведомляем пользователя
            try:
                await context.bot.send_message(
                    chat_id=target_user.id,
                    text=f"⚠️ Вы получили предупреждение в чате {update.effective_chat.title}{reason_text}"
                )
            except:
                pass  # Игнорируем ошибки отправки
        else:
            await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
    
    async def ban_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик команды /ban"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        
        if not await self.is_user_admin(chat_id, user_id):
            await update.message.reply_text(RUSSIAN_TEXTS['not_admin'])
            return
        
        if not context.args:
            await update.message.reply_text("❌ Укажите пользователя: /ban @username [причина]")
            return
        
        target_username = context.args[0].lstrip('@')
        reason = ' '.join(context.args[1:]) if len(context.args) > 1 else None
        
        target_user = await self.find_user_by_username(chat_id, target_username)
        if not target_user:
            await update.message.reply_text(RUSSIAN_TEXTS['user_not_found'])
            return
        
        # Баним пользователя
        if self.db.ban_user(target_user.id, chat_id, user_id, reason):
            reason_text = f" по причине: {reason}" if reason else ""
            await update.message.reply_text(
                f"🚫 Пользователь @{target_username} заблокирован{reason_text}",
                parse_mode=ParseMode.MARKDOWN
            )
            
            # Исключаем из чата
            try:
                await context.bot.ban_chat_member(chat_id, target_user.id)
            except:
                pass  # Игнорируем ошибки
        else:
            await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
    
    async def unban_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик команды /unban"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        
        if not await self.is_user_admin(chat_id, user_id):
            await update.message.reply_text(RUSSIAN_TEXTS['not_admin'])
            return
        
        if not context.args:
            await update.message.reply_text("❌ Укажите пользователя: /unban @username")
            return
        
        target_username = context.args[0].lstrip('@')
        target_user = await self.find_user_by_username(chat_id, target_username)
        if not target_user:
            await update.message.reply_text(RUSSIAN_TEXTS['user_not_found'])
            return
        
        # Разбаниваем пользователя
        if self.db.unban_user(target_user.id, chat_id, user_id):
            await update.message.reply_text(
                f"✅ Пользователь @{target_username} разблокирован",
                parse_mode=ParseMode.MARKDOWN
            )
            
            # Разбаниваем в чате
            try:
                await context.bot.unban_chat_member(chat_id, target_user.id)
            except:
                pass
        else:
            await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
    
    async def mute_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик команды /mute"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        
        if not await self.is_user_admin(chat_id, user_id):
            await update.message.reply_text(RUSSIAN_TEXTS['not_admin'])
            return
        
        if len(context.args) < 2:
            await update.message.reply_text("❌ Укажите пользователя и время: /mute @username 1h [причина]")
            return
        
        target_username = context.args[0].lstrip('@')
        duration_str = context.args[1]
        reason = ' '.join(context.args[2:]) if len(context.args) > 2 else None
        
        # Парсим время
        duration = self.parse_duration(duration_str)
        if duration is None:
            await update.message.reply_text(RUSSIAN_TEXTS['invalid_time'])
            return
        
        target_user = await self.find_user_by_username(chat_id, target_username)
        if not target_user:
            await update.message.reply_text(RUSSIAN_TEXTS['user_not_found'])
            return
        
        # Мутим пользователя
        if self.db.mute_user(target_user.id, chat_id, user_id, duration, reason):
            duration_text = self.format_duration(duration)
            reason_text = f" по причине: {reason}" if reason else ""
            
            await update.message.reply_text(
                f"🔇 Пользователь @{target_username} замучен на {duration_text}{reason_text}",
                parse_mode=ParseMode.MARKDOWN
            )
            
            # Устанавливаем ограничения в чате
            try:
                until_date = datetime.now() + timedelta(seconds=duration)
                await context.bot.restrict_chat_member(
                    chat_id, target_user.id,
                    until_date=until_date,
                    permissions={'can_send_messages': False}
                )
            except:
                pass
        else:
            await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
    
    async def unmute_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик команды /unmute"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        
        if not await self.is_user_admin(chat_id, user_id):
            await update.message.reply_text(RUSSIAN_TEXTS['not_admin'])
            return
        
        if not context.args:
            await update.message.reply_text("❌ Укажите пользователя: /unmute @username")
            return
        
        target_username = context.args[0].lstrip('@')
        target_user = await self.find_user_by_username(chat_id, target_username)
        if not target_user:
            await update.message.reply_text(RUSSIAN_TEXTS['user_not_found'])
            return
        
        # Размучиваем пользователя
        if self.db.unmute_user(target_user.id, chat_id, user_id):
            await update.message.reply_text(
                f"✅ Пользователь @{target_username} размучен",
                parse_mode=ParseMode.MARKDOWN
            )
            
            # Снимаем ограничения
            try:
                await context.bot.restrict_chat_member(
                    chat_id, target_user.id,
                    permissions={'can_send_messages': True}
                )
            except:
                pass
        else:
            await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
    
    async def handle_message(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик всех сообщений для модерации"""
        if not update.message or not update.message.text:
            return
        
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        message_text = update.message.text
        
        # Логируем сообщение
        self.db.log_message(chat_id, user_id)
        
        # Получаем информацию о пользователе
        user_info = self.db.get_user(user_id, chat_id)
        if not user_info:
            # Добавляем пользователя, если его нет
            self.db.add_user(
                user_id=user_id,
                chat_id=chat_id,
                username=update.effective_user.username,
                first_name=update.effective_user.first_name,
                last_name=update.effective_user.last_name
            )
            user_info = {'role': 'member', 'warnings': 0}
        
        # Проверяем сообщение на нарушения
        check_result = self.moderation.check_message(
            chat_id, user_id, message_text, user_info['role']
        )
        
        if not check_result['clean']:
            # Определяем действие модерации
            moderation_action = self.moderation.get_moderation_action(
                check_result['violations'],
                check_result['severity'],
                user_info['warnings']
            )
            
            # Выполняем автоматическое действие
            if moderation_action['action'] != 'none' and moderation_action['auto_action']:
                await self.execute_moderation_action(
                    update, context, user_id, chat_id, moderation_action
                )
    
    async def handle_new_member(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик новых участников чата"""
        chat = update.effective_chat
        new_members = update.message.new_chat_members
        
        for member in new_members:
            if member.is_bot:
                continue
            
            # Добавляем пользователя в базу
            self.db.add_user(
                user_id=member.id,
                chat_id=chat.id,
                username=member.username,
                first_name=member.first_name,
                last_name=member.last_name
            )
            
            # Проверяем роль
            is_admin = await self.is_user_admin(chat.id, member.id)
            role = 'admin' if is_admin else 'member'
            self.db.update_user_role(member.id, chat.id, role)
            
            # Отправляем приветственное сообщение
            settings = self.db.get_chat_settings(chat.id)
            welcome_msg = settings.get('welcome_message', DEFAULT_CHAT_SETTINGS['welcome_message'])
            
            if welcome_msg:
                await update.message.reply_text(
                    f"👋 {welcome_msg}\n\nДобро пожаловать, {member.first_name}!",
                    parse_mode=ParseMode.MARKDOWN
                )
    
    async def handle_member_left(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик выхода участников из чата"""
        chat = update.effective_chat
        left_member = update.message.left_chat_member
        
        if not left_member.is_bot:
            # Обновляем статус пользователя (можно добавить поле is_left в БД)
            logger.info(f"Пользователь {left_member.id} покинул чат {chat.id}")
    
    async def button_callback(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Обработчик callback кнопок"""
        query = update.callback_query
        await query.answer()
        
        data = query.data
        
        if data == "admin_settings":
            await self.show_settings_menu(query)
        elif data == "admin_users":
            await self.show_users_menu(query)
        elif data == "admin_stats":
            await self.show_stats_menu(query)
        elif data == "admin_moderation":
            await self.show_moderation_menu(query)
        elif data == "admin_logs":
            await self.show_logs_menu(query)
        elif data == "edit_settings":
            await self.show_edit_settings_menu(query)
        elif data == "moderation_settings":
            await self.show_moderation_settings_menu(query)
        elif data == "admin_back":
            await self.show_admin_main_menu(query)
    
    async def show_settings_menu(self, query):
        """Показ меню настроек"""
        keyboard = [
            [InlineKeyboardButton("🔧 Основные настройки", callback_data="basic_settings")],
            [InlineKeyboardButton("🛡️ Модерация", callback_data="moderation_settings")],
            [InlineKeyboardButton("👥 Пользователи", callback_data="user_settings")],
            [InlineKeyboardButton("🔙 Назад", callback_data="admin_back")]
        ]
        
        reply_markup = InlineKeyboardMarkup(keyboard)
        await query.edit_message_text(
            RUSSIAN_TEXTS['settings_menu'],
            reply_markup=reply_markup,
            parse_mode=ParseMode.MARKDOWN
        )
    
    async def show_admin_main_menu(self, query):
        """Показ главного меню администратора"""
        keyboard = [
            [InlineKeyboardButton("🔧 Настройки чата", callback_data="admin_settings")],
            [InlineKeyboardButton("👥 Управление пользователями", callback_data="admin_users")],
            [InlineKeyboardButton("📊 Статистика", callback_data="admin_stats")],
            [InlineKeyboardButton("🛡️ Модерация", callback_data="admin_moderation")],
            [InlineKeyboardButton("📝 Логи действий", callback_data="admin_logs")]
        ]
        
        reply_markup = InlineKeyboardMarkup(keyboard)
        await query.edit_message_text(
            RUSSIAN_TEXTS['admin_panel'],
            reply_markup=reply_markup,
            parse_mode=ParseMode.MARKDOWN
        )
    
    # Вспомогательные методы
    async def is_user_admin(self, chat_id: int, user_id: int) -> bool:
        """Проверка, является ли пользователь администратором"""
        try:
            member = await self.application.bot.get_chat_member(chat_id, user_id)
            return member.status in [ChatMember.ADMINISTRATOR, ChatMember.CREATOR]
        except:
            return False
    
    async def find_user_by_username(self, chat_id: int, username: str):
        """Поиск пользователя по username"""
        try:
            # Пытаемся найти пользователя в чате
            chat_member = await self.application.bot.get_chat_member(chat_id, username)
            return chat_member.user
        except:
            return None
    
    def parse_duration(self, duration_str: str) -> Optional[int]:
        """Парсинг строки времени в секунды"""
        try:
            if duration_str.endswith('s'):
                return int(duration_str[:-1])
            elif duration_str.endswith('m'):
                return int(duration_str[:-1]) * 60
            elif duration_str.endswith('h'):
                return int(duration_str[:-1]) * 3600
            elif duration_str.endswith('d'):
                return int(duration_str[:-1]) * 86400
            else:
                return int(duration_str) * 60  # По умолчанию минуты
        except:
            return None
    
    def format_duration(self, seconds: int) -> str:
        """Форматирование времени в читаемый вид"""
        if seconds < 60:
            return f"{seconds} сек"
        elif seconds < 3600:
            return f"{seconds // 60} мин"
        elif seconds < 86400:
            return f"{seconds // 3600} час"
        else:
            return f"{seconds // 86400} дней"
    
    async def execute_moderation_action(self, update: Update, context: ContextTypes.DEFAULT_TYPE,
                                      user_id: int, chat_id: int, action: Dict):
        """Выполнение автоматического действия модерации"""
        action_type = action['action']
        reason = action['reason']
        
        if action_type == 'warn':
            self.db.add_warning(user_id, chat_id, context.bot.id, reason)
            await update.message.reply_text(
                f"⚠️ Автоматическое предупреждение: {reason}",
                parse_mode=ParseMode.MARKDOWN
            )
        
        elif action_type == 'mute':
            duration = action['duration'] or self.moderation.mute_duration
            self.db.mute_user(user_id, chat_id, context.bot.id, duration, reason)
            
            # Мутим в чате
            try:
                until_date = datetime.now() + timedelta(seconds=duration)
                await context.bot.restrict_chat_member(
                    chat_id, user_id,
                    until_date=until_date,
                    permissions={'can_send_messages': False}
                )
            except:
                pass
            
            duration_text = self.format_duration(duration)
            await update.message.reply_text(
                f"🔇 Автоматический мут на {duration_text}: {reason}",
                parse_mode=ParseMode.MARKDOWN
            )
        
        elif action_type == 'ban':
            self.db.ban_user(user_id, chat_id, context.bot.id, reason)
            
            # Баним в чате
            try:
                await context.bot.ban_chat_member(chat_id, user_id)
            except:
                pass
            
            await update.message.reply_text(
                f"🚫 Автоматический бан: {reason}",
                parse_mode=ParseMode.MARKDOWN
            )
    
    async def periodic_cleanup(self):
        """Периодическая очистка старых данных"""
        while True:
            try:
                await asyncio.sleep(3600)  # Каждый час
                self.moderation.cleanup_old_data()
                logger.info("Периодическая очистка данных завершена")
            except Exception as e:
                logger.error(f"Ошибка при периодической очистке: {e}")
    
    def run(self):
        """Запуск бота"""
        logger.info("Бот запускается...")
        self.application.run_polling()

# Обработчики команд для настройки модерации
async def auto_delete_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Команда для настройки автоудаления"""
    # Реализация будет добавлена позже
    pass

async def anti_spam_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Команда для настройки антиспама"""
    # Реализация будет добавлена позже
    pass

async def welcome_message_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Команда для настройки приветственного сообщения"""
    # Реализация будет добавлена позже
    pass

async def rules_auto_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Команда для настройки автоматических правил"""
    # Реализация будет добавлена позже
    pass

async def backup_command(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Команда для создания резервной копии"""
    # Реализация будет добавлена позже
    pass

if __name__ == "__main__":
    bot = TelegramBot()
    bot.run()