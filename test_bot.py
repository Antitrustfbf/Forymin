#!/usr/bin/env python3
"""
Тесты для Forymin Bot
Запуск: python test_bot.py
"""

import unittest
import tempfile
import os
import json
from unittest.mock import Mock, patch, MagicMock

# Импортируем модули для тестирования
from database import DatabaseManager
from moderation import ModerationManager
from utils import (
    format_time_ago, format_duration, parse_duration,
    validate_username, sanitize_text, create_progress_bar
)

class TestDatabaseManager(unittest.TestCase):
    """Тесты для DatabaseManager"""
    
    def setUp(self):
        """Настройка тестовой базы данных"""
        self.temp_db = tempfile.NamedTemporaryFile(delete=False, suffix='.db')
        self.db = DatabaseManager(self.temp_db.name)
    
    def tearDown(self):
        """Очистка после тестов"""
        self.temp_db.close()
        os.unlink(self.temp_db.name)
    
    def test_init_database(self):
        """Тест инициализации базы данных"""
        # Проверяем, что таблицы созданы
        with self.db.db_path as conn:
            cursor = conn.cursor()
            cursor.execute("SELECT name FROM sqlite_master WHERE type='table'")
            tables = [row[0] for row in cursor.fetchall()]
            
            expected_tables = ['chats', 'users', 'moderation_logs', 'statistics']
            for table in expected_tables:
                self.assertIn(table, tables)
    
    def test_add_chat(self):
        """Тест добавления чата"""
        result = self.db.add_chat(12345, 'group', 'Test Chat', 'testchat')
        self.assertTrue(result)
        
        # Проверяем, что чат добавлен
        settings = self.db.get_chat_settings(12345)
        self.assertIsInstance(settings, dict)
    
    def test_add_user(self):
        """Тест добавления пользователя"""
        # Сначала добавляем чат
        self.db.add_chat(12345, 'group', 'Test Chat')
        
        # Добавляем пользователя
        result = self.db.add_user(67890, 12345, 'testuser', 'Test', 'User')
        self.assertTrue(result)
        
        # Проверяем пользователя
        user = self.db.get_user(67890, 12345)
        self.assertIsNotNone(user)
        self.assertEqual(user['username'], 'testuser')
        self.assertEqual(user['first_name'], 'Test')
    
    def test_user_role_update(self):
        """Тест обновления роли пользователя"""
        # Настройка
        self.db.add_chat(12345, 'group', 'Test Chat')
        self.db.add_user(67890, 12345, 'testuser', 'Test', 'User')
        
        # Обновляем роль
        result = self.db.update_user_role(67890, 12345, 'admin')
        self.assertTrue(result)
        
        # Проверяем
        user = self.db.get_user(67890, 12345)
        self.assertEqual(user['role'], 'admin')
    
    def test_warning_system(self):
        """Тест системы предупреждений"""
        # Настройка
        self.db.add_chat(12345, 'group', 'Test Chat')
        self.db.add_user(67890, 12345, 'testuser', 'Test', 'User')
        
        # Добавляем предупреждение
        result = self.db.add_warning(67890, 12345, 11111, 'Test warning')
        self.assertTrue(result)
        
        # Проверяем количество предупреждений
        user = self.db.get_user(67890, 12345)
        self.assertEqual(user['warnings'], 1)
    
    def test_ban_unban(self):
        """Тест бана и разбана пользователя"""
        # Настройка
        self.db.add_chat(12345, 'group', 'Test Chat')
        self.db.add_user(67890, 12345, 'testuser', 'Test', 'User')
        
        # Баним пользователя
        result = self.db.ban_user(67890, 12345, 11111, 'Test ban')
        self.assertTrue(result)
        
        # Проверяем статус бана
        user = self.db.get_user(67890, 12345)
        self.assertTrue(user['is_banned'])
        
        # Разбаниваем пользователя
        result = self.db.unban_user(67890, 12345, 11111)
        self.assertTrue(result)
        
        # Проверяем статус
        user = self.db.get_user(67890, 12345)
        self.assertFalse(user['is_banned'])

class TestModerationManager(unittest.TestCase):
    """Тесты для ModerationManager"""
    
    def setUp(self):
        """Настройка менеджера модерации"""
        self.moderation = ModerationManager()
    
    def test_spam_detection(self):
        """Тест обнаружения спама"""
        chat_id = 12345
        user_id = 67890
        
        # Отправляем сообщения быстро (спам)
        for i in range(6):  # Больше порога (5)
            result = self.moderation.check_message(chat_id, user_id, f"Message {i}")
        
        # Последнее сообщение должно быть помечено как спам
        result = self.moderation.check_message(chat_id, user_id, "Spam message")
        self.assertFalse(result['clean'])
        self.assertIn('spam', result['violations'])
    
    def test_flood_detection(self):
        """Тест обнаружения флуда"""
        chat_id = 12345
        user_id = 67890
        
        # Отправляем одинаковые сообщения
        for i in range(4):  # Больше порога (3)
            result = self.moderation.check_message(chat_id, user_id, "Same message")
        
        # Последнее сообщение должно быть помечено как флуд
        result = self.moderation.check_message(chat_id, user_id, "Same message")
        self.assertFalse(result['clean'])
        self.assertIn('flood', result['violations'])
    
    def test_forbidden_words(self):
        """Тест обнаружения запрещенных слов"""
        chat_id = 12345
        user_id = 67890
        
        # Сообщение с запрещенным словом
        result = self.moderation.check_message(chat_id, user_id, "Это спам сообщение")
        self.assertFalse(result['clean'])
        self.assertTrue(any('forbidden_word' in violation for violation in result['violations']))
    
    def test_excessive_caps(self):
        """Тест обнаружения избыточного использования заглавных букв"""
        chat_id = 12345
        user_id = 67890
        
        # Сообщение с избыточными заглавными буквами
        result = self.moderation.check_message(chat_id, user_id, "ЭТО СООБЩЕНИЕ С ОЧЕНЬ МНОГИМИ ЗАГЛАВНЫМИ БУКВАМИ")
        self.assertFalse(result['clean'])
        self.assertIn('excessive_caps', result['violations'])
    
    def test_moderation_action(self):
        """Тест определения действия модерации"""
        # Тест для низкой серьезности
        action = self.moderation.get_moderation_action(['excessive_caps'], 'low', 0)
        self.assertEqual(action['action'], 'warn')
        
        # Тест для высокой серьезности
        action = self.moderation.get_moderation_action(['spam'], 'high', 0)
        self.assertEqual(action['action'], 'mute')
        
        # Тест для множественных нарушений
        action = self.moderation.get_moderation_action(['spam'], 'high', 2)
        self.assertEqual(action['action'], 'ban')

class TestUtils(unittest.TestCase):
    """Тесты для утилит"""
    
    def test_format_duration(self):
        """Тест форматирования длительности"""
        self.assertEqual(format_duration(30), "30 сек")
        self.assertEqual(format_duration(90), "1 мин 30 сек")
        self.assertEqual(format_duration(3600), "1 час")
        self.assertEqual(format_duration(90000), "1 день 1 час")
    
    def test_parse_duration(self):
        """Тест парсинга длительности"""
        self.assertEqual(parse_duration("30s"), 30)
        self.assertEqual(parse_duration("5m"), 300)
        self.assertEqual(parse_duration("2h"), 7200)
        self.assertEqual(parse_duration("1d"), 86400)
        self.assertEqual(parse_duration("10"), 600)  # По умолчанию минуты
    
    def test_validate_username(self):
        """Тест валидации username"""
        self.assertTrue(validate_username("valid_user"))
        self.assertTrue(validate_username("user123"))
        self.assertTrue(validate_username("valid_username_123"))
        
        self.assertFalse(validate_username(""))
        self.assertFalse(validate_username("ab"))  # Слишком короткий
        self.assertFalse(validate_username("a" * 33))  # Слишком длинный
        self.assertFalse(validate_username("user-name"))  # Неверные символы
    
    def test_sanitize_text(self):
        """Тест очистки текста"""
        # Тест HTML тегов
        self.assertEqual(sanitize_text("<b>Bold</b>"), "Bold")
        
        # Тест экранирования Markdown
        text = sanitize_text("**Bold** _Italic_ `Code`")
        self.assertIn("\\*\\*Bold\\*\\*", text)
        self.assertIn("\\_Italic\\_", text)
        self.assertIn("\\`Code\\`", text)
        
        # Тест обрезки по длине
        long_text = "A" * 5000
        sanitized = sanitize_text(long_text, max_length=100)
        self.assertLessEqual(len(sanitized), 100)
        self.assertTrue(sanitized.endswith("..."))
    
    def test_create_progress_bar(self):
        """Тест создания полосы прогресса"""
        # Тест 0%
        bar = create_progress_bar(0, 100)
        self.assertIn("0%", bar)
        
        # Тест 50%
        bar = create_progress_bar(50, 100)
        self.assertIn("50%", bar)
        
        # Тест 100%
        bar = create_progress_bar(100, 100)
        self.assertIn("100%", bar)
        
        # Тест деления на ноль
        bar = create_progress_bar(10, 0)
        self.assertEqual(bar, "█" * 20)  # Полная полоса

class TestIntegration(unittest.TestCase):
    """Интеграционные тесты"""
    
    def setUp(self):
        """Настройка для интеграционных тестов"""
        self.temp_db = tempfile.NamedTemporaryFile(delete=False, suffix='.db')
        self.db = DatabaseManager(self.temp_db.name)
        self.moderation = ModerationManager()
    
    def tearDown(self):
        """Очистка после тестов"""
        self.temp_db.close()
        os.unlink(self.temp_db.name)
    
    def test_full_moderation_flow(self):
        """Тест полного процесса модерации"""
        # 1. Добавляем чат
        self.db.add_chat(12345, 'group', 'Test Chat')
        
        # 2. Добавляем пользователя
        self.db.add_user(67890, 12345, 'testuser', 'Test', 'User')
        
        # 3. Проверяем сообщение на нарушения
        result = self.moderation.check_message(12345, 67890, "Это спам сообщение", 'member')
        
        # 4. Определяем действие модерации
        action = self.moderation.get_moderation_action(
            result['violations'], 
            result['severity'], 
            0
        )
        
        # 5. Выполняем действие (в тесте просто проверяем)
        self.assertIsNotNone(action['action'])
        self.assertIsNotNone(action['reason'])
    
    def test_statistics_collection(self):
        """Тест сбора статистики"""
        # Настройка
        self.db.add_chat(12345, 'group', 'Test Chat')
        self.db.add_user(67890, 12345, 'testuser', 'Test', 'User')
        
        # Логируем несколько сообщений
        for i in range(5):
            self.db.log_message(12345, 67890)
        
        # Получаем статистику
        stats = self.db.get_chat_statistics(12345)
        
        # Проверяем
        self.assertGreater(stats.get('total_users', 0), 0)
        # Статистика может быть пустой для новых чатов

def run_tests():
    """Запуск всех тестов"""
    print("🧪 Запуск тестов Forymin Bot...")
    
    # Создаем тестовый набор
    loader = unittest.TestLoader()
    suite = loader.discover('.', pattern='test_*.py')
    
    # Запускаем тесты
    runner = unittest.TextTestRunner(verbosity=2)
    result = runner.run(suite)
    
    # Выводим результат
    if result.wasSuccessful():
        print("\n✅ Все тесты прошли успешно!")
        return 0
    else:
        print(f"\n❌ Тесты завершились с ошибками: {len(result.failures)} failures, {len(result.errors)} errors")
        return 1

if __name__ == '__main__':
    exit_code = run_tests()
    exit(exit_code)