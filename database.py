import sqlite3
import json
import logging
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Tuple
from config import DATABASE_PATH

logger = logging.getLogger(__name__)

class DatabaseManager:
    def __init__(self, db_path: str = DATABASE_PATH):
        self.db_path = db_path
        self.init_database()
    
    def init_database(self):
        """Инициализация базы данных и создание таблиц"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                
                # Таблица чатов
                cursor.execute('''
                    CREATE TABLE IF NOT EXISTS chats (
                        chat_id INTEGER PRIMARY KEY,
                        chat_type TEXT,
                        title TEXT,
                        username TEXT,
                        settings TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ''')
                
                # Таблица пользователей
                cursor.execute('''
                    CREATE TABLE IF NOT EXISTS users (
                        user_id INTEGER,
                        chat_id INTEGER,
                        username TEXT,
                        first_name TEXT,
                        last_name TEXT,
                        role TEXT DEFAULT 'member',
                        warnings INTEGER DEFAULT 0,
                        is_banned BOOLEAN DEFAULT FALSE,
                        is_muted BOOLEAN DEFAULT FALSE,
                        mute_until TIMESTAMP,
                        join_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (user_id, chat_id),
                        FOREIGN KEY (chat_id) REFERENCES chats (chat_id)
                    )
                ''')
                
                # Таблица действий модерации
                cursor.execute('''
                    CREATE TABLE IF NOT EXISTS moderation_logs (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        chat_id INTEGER,
                        user_id INTEGER,
                        moderator_id INTEGER,
                        action TEXT,
                        reason TEXT,
                        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (chat_id) REFERENCES chats (chat_id),
                        FOREIGN KEY (user_id) REFERENCES users (user_id)
                    )
                ''')
                
                # Таблица статистики
                cursor.execute('''
                    CREATE TABLE IF NOT EXISTS statistics (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        chat_id INTEGER,
                        date DATE,
                        messages_count INTEGER DEFAULT 0,
                        new_users INTEGER DEFAULT 0,
                        warnings_issued INTEGER DEFAULT 0,
                        bans_issued INTEGER DEFAULT 0,
                        mutes_issued INTEGER DEFAULT 0,
                        FOREIGN KEY (chat_id) REFERENCES chats (chat_id)
                    )
                ''')
                
                conn.commit()
                logger.info("База данных инициализирована успешно")
                
        except Exception as e:
            logger.error(f"Ошибка инициализации базы данных: {e}")
    
    def add_chat(self, chat_id: int, chat_type: str, title: str, username: str = None) -> bool:
        """Добавление нового чата"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                cursor.execute('''
                    INSERT OR REPLACE INTO chats (chat_id, chat_type, title, username, updated_at)
                    VALUES (?, ?, ?, ?, ?)
                ''', (chat_id, chat_type, title, username, datetime.now()))
                conn.commit()
                return True
        except Exception as e:
            logger.error(f"Ошибка добавления чата: {e}")
            return False
    
    def get_chat_settings(self, chat_id: int) -> Dict:
        """Получение настроек чата"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                cursor.execute('SELECT settings FROM chats WHERE chat_id = ?', (chat_id,))
                result = cursor.fetchone()
                
                if result and result[0]:
                    return json.loads(result[0])
                return {}
        except Exception as e:
            logger.error(f"Ошибка получения настроек чата: {e}")
            return {}
    
    def update_chat_settings(self, chat_id: int, settings: Dict) -> bool:
        """Обновление настроек чата"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                cursor.execute('''
                    UPDATE chats SET settings = ?, updated_at = ?
                    WHERE chat_id = ?
                ''', (json.dumps(settings), datetime.now(), chat_id))
                conn.commit()
                return True
        except Exception as e:
            logger.error(f"Ошибка обновления настроек чата: {e}")
            return False
    
    def add_user(self, user_id: int, chat_id: int, username: str, first_name: str, last_name: str = None) -> bool:
        """Добавление пользователя в чат"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                cursor.execute('''
                    INSERT OR REPLACE INTO users 
                    (user_id, chat_id, username, first_name, last_name, join_date, last_activity)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ''', (user_id, chat_id, username, first_name, last_name, datetime.now(), datetime.now()))
                conn.commit()
                return True
        except Exception as e:
            logger.error(f"Ошибка добавления пользователя: {e}")
            return False
    
    def get_user(self, user_id: int, chat_id: int) -> Optional[Dict]:
        """Получение информации о пользователе"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                cursor.execute('''
                    SELECT * FROM users WHERE user_id = ? AND chat_id = ?
                ''', (user_id, chat_id))
                result = cursor.fetchone()
                
                if result:
                    columns = [description[0] for description in cursor.description]
                    return dict(zip(columns, result))
                return None
        except Exception as e:
            logger.error(f"Ошибка получения пользователя: {e}")
            return None
    
    def update_user_role(self, user_id: int, chat_id: int, role: str) -> bool:
        """Обновление роли пользователя"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                cursor.execute('''
                    UPDATE users SET role = ? WHERE user_id = ? AND chat_id = ?
                ''', (role, user_id, chat_id))
                conn.commit()
                return True
        except Exception as e:
            logger.error(f"Ошибка обновления роли пользователя: {e}")
            return False
    
    def add_warning(self, user_id: int, chat_id: int, moderator_id: int, reason: str = None) -> bool:
        """Добавление предупреждения пользователю"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                
                # Увеличиваем количество предупреждений
                cursor.execute('''
                    UPDATE users SET warnings = warnings + 1
                    WHERE user_id = ? AND chat_id = ?
                ''', (user_id, chat_id))
                
                # Логируем действие
                cursor.execute('''
                    INSERT INTO moderation_logs (chat_id, user_id, moderator_id, action, reason)
                    VALUES (?, ?, ?, 'warning', ?)
                ''', (chat_id, user_id, moderator_id, reason))
                
                conn.commit()
                return True
        except Exception as e:
            logger.error(f"Ошибка добавления предупреждения: {e}")
            return False
    
    def ban_user(self, user_id: int, chat_id: int, moderator_id: int, reason: str = None) -> bool:
        """Бан пользователя"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                
                cursor.execute('''
                    UPDATE users SET is_banned = TRUE
                    WHERE user_id = ? AND chat_id = ?
                ''', (user_id, chat_id))
                
                cursor.execute('''
                    INSERT INTO moderation_logs (chat_id, user_id, moderator_id, action, reason)
                    VALUES (?, ?, ?, 'ban', ?)
                ''', (chat_id, user_id, moderator_id, reason))
                
                conn.commit()
                return True
        except Exception as e:
            logger.error(f"Ошибка бана пользователя: {e}")
            return False
    
    def unban_user(self, user_id: int, chat_id: int, moderator_id: int) -> bool:
        """Разбан пользователя"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                
                cursor.execute('''
                    UPDATE users SET is_banned = FALSE
                    WHERE user_id = ? AND chat_id = ?
                ''', (user_id, chat_id))
                
                cursor.execute('''
                    INSERT INTO moderation_logs (chat_id, user_id, moderator_id, action, reason)
                    VALUES (?, ?, ?, 'unban', 'Разбанен администратором')
                ''', (chat_id, user_id, moderator_id))
                
                conn.commit()
                return True
        except Exception as e:
            logger.error(f"Ошибка разбана пользователя: {e}")
            return False
    
    def mute_user(self, user_id: int, chat_id: int, moderator_id: int, duration: int, reason: str = None) -> bool:
        """Мут пользователя"""
        try:
            mute_until = datetime.now() + timedelta(seconds=duration)
            
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                
                cursor.execute('''
                    UPDATE users SET is_muted = TRUE, mute_until = ?
                    WHERE user_id = ? AND chat_id = ?
                ''', (mute_until, user_id, chat_id))
                
                cursor.execute('''
                    INSERT INTO moderation_logs (chat_id, user_id, moderator_id, action, reason)
                    VALUES (?, ?, ?, 'mute', ?)
                ''', (chat_id, user_id, moderator_id, reason))
                
                conn.commit()
                return True
        except Exception as e:
            logger.error(f"Ошибка мута пользователя: {e}")
            return False
    
    def unmute_user(self, user_id: int, chat_id: int, moderator_id: int) -> bool:
        """Размут пользователя"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                
                cursor.execute('''
                    UPDATE users SET is_muted = FALSE, mute_until = NULL
                    WHERE user_id = ? AND chat_id = ?
                ''', (user_id, chat_id))
                
                cursor.execute('''
                    INSERT INTO moderation_logs (chat_id, user_id, moderator_id, action, reason)
                    VALUES (?, ?, ?, 'unmute', 'Размучен администратором')
                ''', (chat_id, user_id, moderator_id))
                
                conn.commit()
                return True
        except Exception as e:
            logger.error(f"Ошибка размута пользователя: {e}")
            return False
    
    def get_chat_statistics(self, chat_id: int, days: int = 7) -> Dict:
        """Получение статистики чата"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                
                # Общее количество пользователей
                cursor.execute('''
                    SELECT COUNT(*) FROM users WHERE chat_id = ?
                ''', (chat_id,))
                total_users = cursor.fetchone()[0]
                
                # Заблокированные пользователи
                cursor.execute('''
                    SELECT COUNT(*) FROM users WHERE chat_id = ? AND is_banned = TRUE
                ''', (chat_id,))
                banned_users = cursor.fetchone()[0]
                
                # Замученные пользователи
                cursor.execute('''
                    SELECT COUNT(*) FROM users WHERE chat_id = ? AND is_muted = TRUE
                ''', (chat_id,))
                muted_users = cursor.fetchone()[0]
                
                # Действия модерации за последние дни
                start_date = datetime.now() - timedelta(days=days)
                cursor.execute('''
                    SELECT action, COUNT(*) FROM moderation_logs 
                    WHERE chat_id = ? AND timestamp > ?
                    GROUP BY action
                ''', (chat_id, start_date))
                moderation_actions = dict(cursor.fetchall())
                
                return {
                    'total_users': total_users,
                    'banned_users': banned_users,
                    'muted_users': muted_users,
                    'moderation_actions': moderation_actions,
                    'period_days': days
                }
        except Exception as e:
            logger.error(f"Ошибка получения статистики: {e}")
            return {}
    
    def log_message(self, chat_id: int, user_id: int) -> bool:
        """Логирование сообщения для статистики"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                
                # Обновляем активность пользователя
                cursor.execute('''
                    UPDATE users SET last_activity = ? 
                    WHERE user_id = ? AND chat_id = ?
                ''', (datetime.now(), user_id, chat_id))
                
                # Обновляем статистику чата
                today = datetime.now().date()
                cursor.execute('''
                    INSERT OR REPLACE INTO statistics (chat_id, date, messages_count)
                    VALUES (?, ?, COALESCE(
                        (SELECT messages_count FROM statistics WHERE chat_id = ? AND date = ?), 0
                    ) + 1)
                ''', (chat_id, today, chat_id, today))
                
                conn.commit()
                return True
        except Exception as e:
            logger.error(f"Ошибка логирования сообщения: {e}")
            return False