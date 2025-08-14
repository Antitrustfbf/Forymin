import logging
from typing import Dict, Optional
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import ContextTypes
from telegram.constants import ParseMode

from config import RUSSIAN_TEXTS, DEFAULT_CHAT_SETTINGS
from database import DatabaseManager
from moderation import ModerationManager
from utils import parse_duration, format_duration, sanitize_text

logger = logging.getLogger(__name__)

class ModerationCommands:
    def __init__(self, db: DatabaseManager, moderation: ModerationManager):
        self.db = db
        self.moderation = moderation
    
    async def auto_delete_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Команда для настройки автоудаления сообщений"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        
        # Проверяем права администратора
        if not await self._is_user_admin(update, context):
            return
        
        if not context.args:
            # Показываем текущие настройки автоудаления
            await self._show_auto_delete_settings(update, context)
            return
        
        action = context.args[0].lower()
        
        if action == "on":
            delay = 300  # 5 минут по умолчанию
            if len(context.args) > 1:
                delay = parse_duration(context.args[1]) or 300
            
            await self._enable_auto_delete(update, context, delay)
        
        elif action == "off":
            await self._disable_auto_delete(update, context)
        
        elif action == "set":
            if len(context.args) < 2:
                await update.message.reply_text(
                    "❌ Укажите время: /auto_delete set 5m"
                )
                return
            
            delay = parse_duration(context.args[1])
            if delay is None:
                await update.message.reply_text(RUSSIAN_TEXTS['invalid_time'])
                return
            
            await self._set_auto_delete_delay(update, context, delay)
        
        else:
            await update.message.reply_text(
                "❌ Неверный параметр. Используйте: on, off, set <время>"
            )
    
    async def anti_spam_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Команда для настройки антиспама"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        
        if not await self._is_user_admin(update, context):
            return
        
        if not context.args:
            await self._show_anti_spam_settings(update, context)
            return
        
        action = context.args[0].lower()
        
        if action == "on":
            await self._enable_anti_spam(update, context)
        
        elif action == "off":
            await self._disable_anti_spam(update, context)
        
        elif action == "threshold":
            if len(context.args) < 2:
                await update.message.reply_text(
                    "❌ Укажите порог: /anti_spam threshold 5"
                )
                return
            
            try:
                threshold = int(context.args[1])
                if threshold < 1 or threshold > 20:
                    await update.message.reply_text(
                        "❌ Порог должен быть от 1 до 20 сообщений в минуту"
                    )
                    return
                
                await self._set_spam_threshold(update, context, threshold)
            except ValueError:
                await update.message.reply_text("❌ Порог должен быть числом")
        
        elif action == "words":
            if len(context.args) < 2:
                await update.message.reply_text(
                    "❌ Укажите действие: add/remove <слово>"
                )
                return
            
            sub_action = context.args[1].lower()
            if sub_action == "add" and len(context.args) > 2:
                word = context.args[2].lower()
                await self._add_forbidden_word(update, context, word)
            elif sub_action == "remove" and len(context.args) > 2:
                word = context.args[2].lower()
                await self._remove_forbidden_word(update, context, word)
            elif sub_action == "list":
                await self._show_forbidden_words(update, context)
            else:
                await update.message.reply_text(
                    "❌ Используйте: add <слово>, remove <слово>, list"
                )
        
        else:
            await update.message.reply_text(
                "❌ Неверный параметр. Используйте: on, off, threshold <число>, words <действие>"
            )
    
    async def welcome_message_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Команда для настройки приветственного сообщения"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        
        if not await self._is_user_admin(update, context):
            return
        
        if not context.args:
            await self._show_welcome_message(update, context)
            return
        
        action = context.args[0].lower()
        
        if action == "set":
            if len(context.args) < 2:
                await update.message.reply_text(
                    "❌ Укажите текст: /welcome_message set Добро пожаловать!"
                )
                return
            
            message = ' '.join(context.args[1:])
            await self._set_welcome_message(update, context, message)
        
        elif action == "off":
            await self._disable_welcome_message(update, context)
        
        elif action == "preview":
            await self._preview_welcome_message(update, context)
        
        else:
            await update.message.reply_text(
                "❌ Используйте: set <текст>, off, preview"
            )
    
    async def rules_auto_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Команда для настройки автоматических правил"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        
        if not await self._is_user_admin(update, context):
            return
        
        if not context.args:
            await self._show_rules_settings(update, context)
            return
        
        action = context.args[0].lower()
        
        if action == "set":
            if len(context.args) < 2:
                await update.message.reply_text(
                    "❌ Укажите правила: /rules_auto set Будьте вежливы"
                )
                return
            
            rules = ' '.join(context.args[1:])
            await self._set_auto_rules(update, context, rules)
        
        elif action == "off":
            await self._disable_auto_rules(update, context)
        
        elif action == "preview":
            await self._preview_rules(update, context)
        
        else:
            await update.message.reply_text(
                "❌ Используйте: set <правила>, off, preview"
            )
    
    async def backup_command(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Команда для создания резервной копии настроек"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        
        if not await self._is_user_admin(update, context):
            return
        
        if not context.args:
            await self._create_backup(update, context)
            return
        
        action = context.args[0].lower()
        
        if action == "restore":
            if not context.args[1:]:
                await update.message.reply_text(
                    "❌ Укажите данные для восстановления"
                )
                return
            
            backup_data = ' '.join(context.args[1:])
            await self._restore_backup(update, context, backup_data)
        
        elif action == "list":
            await self._list_backups(update, context)
        
        else:
            await update.message.reply_text(
                "❌ Используйте: restore <данные>, list"
            )
    
    # Вспомогательные методы
    async def _is_user_admin(self, update: Update, context: ContextTypes.DEFAULT_TYPE) -> bool:
        """Проверка прав администратора"""
        try:
            member = await context.bot.get_chat_member(
                update.effective_chat.id, 
                update.effective_user.id
            )
            is_admin = member.status in ['administrator', 'creator']
            
            if not is_admin:
                await update.message.reply_text(RUSSIAN_TEXTS['not_admin'])
            
            return is_admin
        except Exception as e:
            logger.error(f"Ошибка проверки прав администратора: {e}")
            await update.message.reply_text("❌ Ошибка проверки прав")
            return False
    
    async def _show_auto_delete_settings(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Показ настроек автоудаления"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        auto_delete = settings.get('auto_delete', False)
        delay = settings.get('auto_delete_delay', 300)
        
        status = "✅ Включено" if auto_delete else "❌ Отключено"
        delay_text = format_duration(delay) if auto_delete else "не применимо"
        
        text = f"""🚫 **Настройки автоудаления:**

Статус: {status}
Задержка: {delay_text}

**Команды:**
`/auto_delete on [время]` - Включить
`/auto_delete off` - Отключить  
`/auto_delete set <время>` - Установить время
`/auto_delete` - Показать настройки"""
        
        keyboard = [
            [InlineKeyboardButton("✅ Включить", callback_data="auto_delete_on")],
            [InlineKeyboardButton("❌ Отключить", callback_data="auto_delete_off")],
            [InlineKeyboardButton("⚙️ Настроить", callback_data="auto_delete_config")]
        ]
        
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(text, reply_markup=reply_markup, parse_mode=ParseMode.MARKDOWN)
    
    async def _enable_auto_delete(self, update: Update, context: ContextTypes.DEFAULT_TYPE, delay: int):
        """Включение автоудаления"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        settings['auto_delete'] = True
        settings['auto_delete_delay'] = delay
        
        if self.db.update_chat_settings(chat_id, settings):
            delay_text = format_duration(delay)
            await update.message.reply_text(
                f"✅ Автоудаление включено с задержкой {delay_text}",
                parse_mode=ParseMode.MARKDOWN
            )
        else:
            await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
    
    async def _disable_auto_delete(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Отключение автоудаления"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        settings['auto_delete'] = False
        
        if self.db.update_chat_settings(chat_id, settings):
            await update.message.reply_text(
                "❌ Автоудаление отключено",
                parse_mode=ParseMode.MARKDOWN
            )
        else:
            await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
    
    async def _set_auto_delete_delay(self, update: Update, context: ContextTypes.DEFAULT_TYPE, delay: int):
        """Установка задержки автоудаления"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        settings['auto_delete_delay'] = delay
        
        if self.db.update_chat_settings(chat_id, settings):
            delay_text = format_duration(delay)
            await update.message.reply_text(
                f"✅ Задержка автоудаления установлена: {delay_text}",
                parse_mode=ParseMode.MARKDOWN
            )
        else:
            await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
    
    async def _show_anti_spam_settings(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Показ настроек антиспама"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        anti_spam = settings.get('anti_spam', True)
        mod_stats = self.moderation.get_moderation_stats(chat_id)
        
        status = "✅ Включен" if anti_spam else "❌ Отключен"
        threshold = mod_stats['settings']['spam_threshold']
        
        text = f"""🔄 **Настройки антиспама:**

Статус: {status}
Порог спама: {threshold} сообщ./мин
Порог флуда: {mod_stats['settings']['flood_threshold']} повт.

**Команды:**
`/anti_spam on/off` - Включить/отключить
`/anti_spam threshold <число>` - Установить порог
`/anti_spam words add/remove <слово>` - Управление словами
`/anti_spam words list` - Список слов"""
        
        keyboard = [
            [InlineKeyboardButton("✅ Включить", callback_data="anti_spam_on")],
            [InlineKeyboardButton("❌ Отключить", callback_data="anti_spam_off")],
            [InlineKeyboardButton("⚙️ Настроить", callback_data="anti_spam_config")]
        ]
        
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(text, reply_markup=reply_markup, parse_mode=ParseMode.MARKDOWN)
    
    async def _enable_anti_spam(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Включение антиспама"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        settings['anti_spam'] = True
        
        if self.db.update_chat_settings(chat_id, settings):
            await update.message.reply_text(
                "✅ Антиспам защита включена",
                parse_mode=ParseMode.MARKDOWN
            )
        else:
            await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
    
    async def _disable_anti_spam(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Отключение антиспама"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        settings['anti_spam'] = False
        
        if self.db.update_chat_settings(chat_id, settings):
            await update.message.reply_text(
                "❌ Антиспам защита отключена",
                parse_mode=ParseMode.MARKDOWN
            )
        else:
            await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
    
    async def _set_spam_threshold(self, update: Update, context: ContextTypes.DEFAULT_TYPE, threshold: int):
        """Установка порога спама"""
        self.moderation.spam_threshold = threshold
        
        await update.message.reply_text(
            f"✅ Порог спама установлен: {threshold} сообщений в минуту",
            parse_mode=ParseMode.MARKDOWN
        )
    
    async def _add_forbidden_word(self, update: Update, context: ContextTypes.DEFAULT_TYPE, word: str):
        """Добавление запрещенного слова"""
        if word in self.moderation.forbidden_words:
            await update.message.reply_text(f"❌ Слово '{word}' уже в списке")
            return
        
        self.moderation.forbidden_words.append(word)
        
        await update.message.reply_text(
            f"✅ Слово '{word}' добавлено в список запрещенных",
            parse_mode=ParseMode.MARKDOWN
        )
    
    async def _remove_forbidden_word(self, update: Update, context: ContextTypes.DEFAULT_TYPE, word: str):
        """Удаление запрещенного слова"""
        if word not in self.moderation.forbidden_words:
            await update.message.reply_text(f"❌ Слово '{word}' не найдено в списке")
            return
        
        self.moderation.forbidden_words.remove(word)
        
        await update.message.reply_text(
            f"✅ Слово '{word}' удалено из списка запрещенных",
            parse_mode=ParseMode.MARKDOWN
        )
    
    async def _show_forbidden_words(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Показ списка запрещенных слов"""
        words = self.moderation.forbidden_words
        
        if not words:
            await update.message.reply_text("📝 Список запрещенных слов пуст")
            return
        
        text = "📝 **Запрещенные слова:**\n\n"
        for i, word in enumerate(words, 1):
            text += f"{i}. `{word}`\n"
        
        await update.message.reply_text(text, parse_mode=ParseMode.MARKDOWN)
    
    async def _show_welcome_message(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Показ текущего приветственного сообщения"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        welcome_msg = settings.get('welcome_message', DEFAULT_CHAT_SETTINGS['welcome_message'])
        
        text = f"""👋 **Приветственное сообщение:**

{welcome_msg}

**Команды:**
`/welcome_message set <текст>` - Установить
`/welcome_message off` - Отключить
`/welcome_message preview` - Предварительный просмотр"""
        
        keyboard = [
            [InlineKeyboardButton("✏️ Изменить", callback_data="welcome_edit")],
            [InlineKeyboardButton("❌ Отключить", callback_data="welcome_off")],
            [InlineKeyboardButton("👁️ Предпросмотр", callback_data="welcome_preview")]
        ]
        
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(text, reply_markup=reply_markup, parse_mode=ParseMode.MARKDOWN)
    
    async def _set_welcome_message(self, update: Update, context: ContextTypes.DEFAULT_TYPE, message: str):
        """Установка приветственного сообщения"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        settings['welcome_message'] = sanitize_text(message, 1000)
        
        if self.db.update_chat_settings(chat_id, settings):
            await update.message.reply_text(
                "✅ Приветственное сообщение установлено",
                parse_mode=ParseMode.MARKDOWN
            )
        else:
            await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
    
    async def _disable_welcome_message(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Отключение приветственного сообщения"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        settings['welcome_message'] = ""
        
        if self.db.update_chat_settings(chat_id, settings):
            await update.message.reply_text(
                "❌ Приветственное сообщение отключено",
                parse_mode=ParseMode.MARKDOWN
            )
        else:
            await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
    
    async def _preview_welcome_message(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Предварительный просмотр приветственного сообщения"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        welcome_msg = settings.get('welcome_message', DEFAULT_CHAT_SETTINGS['welcome_message'])
        
        if not welcome_msg:
            await update.message.reply_text("❌ Приветственное сообщение не настроено")
            return
        
        preview_text = f"👋 **Предварительный просмотр:**\n\n{welcome_msg}\n\nДобро пожаловать, @username!"
        
        await update.message.reply_text(preview_text, parse_mode=ParseMode.MARKDOWN)
    
    async def _show_rules_settings(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Показ настроек правил"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        rules = settings.get('rules', DEFAULT_CHAT_SETTINGS['rules'])
        
        text = f"""📋 **Правила чата:**

{rules}

**Команды:**
`/rules_auto set <правила>` - Установить
`/rules_auto off` - Отключить
`/rules_auto preview` - Предварительный просмотр"""
        
        keyboard = [
            [InlineKeyboardButton("✏️ Изменить", callback_data="rules_edit")],
            [InlineKeyboardButton("❌ Отключить", callback_data="rules_off")],
            [InlineKeyboardButton("👁️ Предпросмотр", callback_data="rules_preview")]
        ]
        
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(text, reply_markup=reply_markup, parse_mode=ParseMode.MARKDOWN)
    
    async def _set_auto_rules(self, update: Update, context: ContextTypes.DEFAULT_TYPE, rules: str):
        """Установка автоматических правил"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        settings['rules'] = sanitize_text(rules, 2000)
        
        if self.db.update_chat_settings(chat_id, settings):
            await update.message.reply_text(
                "✅ Правила чата обновлены",
                parse_mode=ParseMode.MARKDOWN
            )
        else:
            await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
    
    async def _disable_auto_rules(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Отключение автоматических правил"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        settings['rules'] = ""
        
        if self.db.update_chat_settings(chat_id, settings):
            await update.message.reply_text(
                "❌ Автоматические правила отключены",
                parse_mode=ParseMode.MARKDOWN
            )
        else:
            await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
    
    async def _preview_rules(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Предварительный просмотр правил"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        rules = settings.get('rules', DEFAULT_CHAT_SETTINGS['rules'])
        
        if not rules:
            await update.message.reply_text("❌ Правила не настроены")
            return
        
        preview_text = f"📋 **Предварительный просмотр правил:**\n\n{rules}"
        
        await update.message.reply_text(preview_text, parse_mode=ParseMode.MARKDOWN)
    
    async def _create_backup(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Создание резервной копии"""
        chat_id = update.effective_chat.id
        settings = self.db.get_chat_settings(chat_id)
        
        import json
        backup_data = json.dumps(settings, ensure_ascii=False, indent=2)
        
        text = f"""💾 **Резервная копия настроек создана**

**Команда для восстановления:**
`/backup restore {backup_data}`

⚠️ Сохраните эту команду для восстановления настроек"""
        
        await update.message.reply_text(text, parse_mode=ParseMode.MARKDOWN)
    
    async def _restore_backup(self, update: Update, context: ContextTypes.DEFAULT_TYPE, backup_data: str):
        """Восстановление из резервной копии"""
        chat_id = update.effective_chat.id
        
        try:
            import json
            settings = json.loads(backup_data)
            
            if self.db.update_chat_settings(chat_id, settings):
                await update.message.reply_text(
                    "✅ Настройки восстановлены из резервной копии",
                    parse_mode=ParseMode.MARKDOWN
                )
            else:
                await update.message.reply_text(RUSSIAN_TEXTS['operation_failed'])
        
        except json.JSONDecodeError:
            await update.message.reply_text("❌ Неверный формат резервной копии")
        except Exception as e:
            logger.error(f"Ошибка восстановления: {e}")
            await update.message.reply_text("❌ Ошибка восстановления настроек")
    
    async def _list_backups(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Список доступных резервных копий"""
        # В простой версии просто показываем инструкцию
        text = """📋 **Резервные копии**

Для создания резервной копии используйте:
`/backup`

Для восстановления используйте:
`/backup restore <данные>`

💡 Резервные копии создаются в формате JSON"""
        
        await update.message.reply_text(text, parse_mode=ParseMode.MARKDOWN)