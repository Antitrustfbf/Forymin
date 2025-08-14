import os
from dotenv import load_dotenv

load_dotenv()

# Основные настройки бота
BOT_TOKEN = os.getenv('BOT_TOKEN')
BOT_USERNAME = os.getenv('BOT_USERNAME', 'your_bot_username')

# Настройки базы данных
DATABASE_PATH = 'bot_database.db'

# Настройки логирования
LOG_LEVEL = 'INFO'
LOG_FORMAT = '%(asctime)s - %(name)s - %(levelname)s - %(message)s'

# Русские тексты для бота
RUSSIAN_TEXTS = {
    'welcome': '👋 Добро пожаловать! Я умный бот для управления чатами.',
    'help': '''🤖 **Доступные команды:**

**Для всех пользователей:**
/start - Начать работу с ботом
/help - Показать справку
/profile - Ваш профиль
/rules - Правила чата

**Для администраторов:**
/admin - Панель администратора
/settings - Настройки чата
/warn @username - Предупреждение пользователю
/ban @username - Заблокировать пользователя
/unban @username - Разблокировать пользователя
/mute @username 1h - Замутить пользователя
/unmute @username - Размутить пользователя
/statistics - Статистика чата
/backup - Резервная копия настроек

**Модерация:**
/auto_delete - Автоудаление сообщений
/anti_spam - Антиспам настройки
/welcome_message - Настройка приветствия
/rules_auto - Автоматические правила''',
    
    'admin_panel': '''⚙️ **Панель администратора**

Выберите действие:
🔧 Настройки чата
👥 Управление пользователями
📊 Статистика
🛡️ Модерация
📝 Логи действий''',
    
    'not_admin': '❌ У вас нет прав администратора для выполнения этой команды.',
    'user_not_found': '❌ Пользователь не найден в этом чате.',
    'operation_success': '✅ Операция выполнена успешно!',
    'operation_failed': '❌ Ошибка при выполнении операции.',
    'invalid_time': '❌ Неверный формат времени. Пример: 1h, 30m, 2d',
    'welcome_message_set': '✅ Приветственное сообщение установлено!',
    'rules_set': '✅ Правила чата обновлены!',
    'auto_delete_enabled': '✅ Автоудаление сообщений включено',
    'anti_spam_enabled': '✅ Антиспам защита активирована',
    
    'settings_menu': '''⚙️ **Настройки чата**

🔧 Основные настройки
🛡️ Модерация
👥 Пользователи
📊 Статистика
🔙 Назад''',
    
    'moderation_menu': '''🛡️ **Настройки модерации**

🚫 Автоудаление сообщений
🔄 Антиспам
👋 Приветственные сообщения
📋 Автоматические правила
🔙 Назад''',
    
    'user_management': '''👥 **Управление пользователями**

⚠️ Предупреждения
🔇 Муты
🚫 Банны
📊 Активность
🔙 Назад'''
}

# Настройки по умолчанию для чатов
DEFAULT_CHAT_SETTINGS = {
    'auto_delete': False,
    'auto_delete_delay': 300,  # 5 минут
    'anti_spam': True,
    'max_warnings': 3,
    'welcome_message': 'Добро пожаловать в чат!',
    'rules': 'Будьте вежливы и соблюдайте правила общения.',
    'log_actions': True,
    'auto_moderation': True
}

# Настройки модерации
MODERATION_SETTINGS = {
    'spam_threshold': 5,  # сообщений в минуту
    'flood_threshold': 3,  # одинаковых сообщений подряд
    'link_whitelist': [],  # разрешенные ссылки
    'forbidden_words': ['спам', 'реклама', 'scam'],  # запрещенные слова
    'auto_warn': True,
    'auto_mute': True,
    'mute_duration': 3600  # 1 час по умолчанию
}