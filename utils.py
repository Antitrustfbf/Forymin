import re
import json
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Union

def format_time_ago(timestamp: Union[datetime, str]) -> str:
    """
    Форматирует время в читаемый вид "X времени назад"
    """
    if isinstance(timestamp, str):
        try:
            timestamp = datetime.fromisoformat(timestamp.replace('Z', '+00:00'))
        except:
            return "неизвестно"
    
    now = datetime.now()
    diff = now - timestamp
    
    if diff.days > 0:
        if diff.days == 1:
            return "вчера"
        elif diff.days < 7:
            return f"{diff.days} дней назад"
        elif diff.days < 30:
            weeks = diff.days // 7
            if weeks == 1:
                return "неделю назад"
            else:
                return f"{weeks} недель назад"
        else:
            months = diff.days // 30
            if months == 1:
                return "месяц назад"
            else:
                return f"{months} месяцев назад"
    elif diff.seconds > 3600:
        hours = diff.seconds // 3600
        if hours == 1:
            return "час назад"
        else:
            return f"{hours} часов назад"
    elif diff.seconds > 60:
        minutes = diff.seconds // 60
        if minutes == 1:
            return "минуту назад"
        else:
            return f"{minutes} минут назад"
    else:
        return "только что"

def format_duration(seconds: int) -> str:
    """
    Форматирует длительность в секундах в читаемый вид
    """
    if seconds < 60:
        return f"{seconds} сек"
    elif seconds < 3600:
        minutes = seconds // 60
        seconds_remain = seconds % 60
        if seconds_remain == 0:
            return f"{minutes} мин"
        else:
            return f"{minutes} мин {seconds_remain} сек"
    elif seconds < 86400:
        hours = seconds // 3600
        minutes = (seconds % 3600) // 60
        if minutes == 0:
            return f"{hours} час"
        else:
            return f"{hours} час {minutes} мин"
    else:
        days = seconds // 86400
        hours = (seconds % 86400) // 3600
        if hours == 0:
            return f"{days} дней"
        else:
            return f"{days} дней {hours} час"

def parse_duration(duration_str: str) -> Optional[int]:
    """
    Парсит строку времени в секунды
    Поддерживает форматы: 30s, 5m, 2h, 1d
    """
    if not duration_str:
        return None
    
    duration_str = duration_str.strip().lower()
    
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
            # По умолчанию считаем минутами
            return int(duration_str) * 60
    except ValueError:
        return None

def validate_username(username: str) -> bool:
    """
    Проверяет корректность username
    """
    if not username:
        return False
    
    # Username должен содержать только буквы, цифры и подчеркивания
    # Длина от 5 до 32 символов
    pattern = r'^[a-zA-Z0-9_]{5,32}$'
    return bool(re.match(pattern, username))

def sanitize_text(text: str, max_length: int = 4096) -> str:
    """
    Очищает текст от потенциально опасных символов и обрезает по длине
    """
    if not text:
        return ""
    
    # Убираем HTML теги
    text = re.sub(r'<[^>]+>', '', text)
    
    # Экранируем специальные символы для Markdown
    text = text.replace('*', '\\*').replace('_', '\\_').replace('`', '\\`')
    text = text.replace('[', '\\[').replace(']', '\\]').replace('(', '\\(').replace(')', '\\)')
    
    # Обрезаем по длине
    if len(text) > max_length:
        text = text[:max_length-3] + "..."
    
    return text

def create_progress_bar(current: int, total: int, width: int = 20) -> str:
    """
    Создает текстовую полосу прогресса
    """
    if total == 0:
        return "█" * width
    
    progress = current / total
    filled = int(width * progress)
    empty = width - filled
    
    bar = "█" * filled + "░" * empty
    percentage = int(progress * 100)
    
    return f"{bar} {percentage}%"

def format_file_size(bytes_size: int) -> str:
    """
    Форматирует размер файла в читаемый вид
    """
    if bytes_size < 1024:
        return f"{bytes_size} Б"
    elif bytes_size < 1024 * 1024:
        return f"{bytes_size / 1024:.1f} КБ"
    elif bytes_size < 1024 * 1024 * 1024:
        return f"{bytes_size / (1024 * 1024):.1f} МБ"
    else:
        return f"{bytes_size / (1024 * 1024 * 1024):.1f} ГБ"

def extract_links(text: str) -> List[str]:
    """
    Извлекает все ссылки из текста
    """
    url_pattern = r'https?://[^\s]+'
    return re.findall(url_pattern, text)

def is_suspicious_link(url: str, whitelist: List[str] = None) -> bool:
    """
    Проверяет, является ли ссылка подозрительной
    """
    if not whitelist:
        whitelist = []
    
    # Проверяем, есть ли ссылка в белом списке
    for whitelist_domain in whitelist:
        if whitelist_domain.lower() in url.lower():
            return False
    
    # Проверяем на подозрительные паттерны
    suspicious_patterns = [
        r'bit\.ly',
        r'tinyurl\.com',
        r'goo\.gl',
        r'is\.gd',
        r'v\.gd',
        r'rb\.gy',
        r'ow\.ly',
        r'j\.mp',
        r'cli\.gs',
        r'u\.nu',
        r'url\.ie',
        r'x\.co',
        r'1url\.com',
        r't\.co',
        r'lnkd\.in',
        r'db\.tt',
        r'qr\.ae',
        r'adf\.ly',
        r'go2cloud\.org',
        r'ow\.ly',
        r'bitly\.com',
        r'cur\.lv',
        r'tinyurl\.com',
        r'is\.gd',
        r'cli\.gs',
        r'u\.nu',
        r'url\.ie',
        r'x\.co',
        r'1url\.com',
        r't\.co',
        r'lnkd\.in',
        r'db\.tt',
        r'qr\.ae',
        r'adf\.ly',
        r'go2cloud\.org',
        r'ow\.ly',
        r'bitly\.com',
        r'cur\.lv'
    ]
    
    for pattern in suspicious_patterns:
        if re.search(pattern, url, re.IGNORECASE):
            return True
    
    return False

def count_words(text: str) -> int:
    """
    Подсчитывает количество слов в тексте
    """
    if not text:
        return 0
    
    # Разбиваем на слова и фильтруем пустые
    words = [word for word in text.split() if word.strip()]
    return len(words)

def count_characters(text: str, include_spaces: bool = True) -> int:
    """
    Подсчитывает количество символов в тексте
    """
    if not text:
        return 0
    
    if include_spaces:
        return len(text)
    else:
        return len(text.replace(' ', ''))

def get_text_statistics(text: str) -> Dict:
    """
    Получает статистику текста
    """
    if not text:
        return {
            'characters': 0,
            'characters_no_spaces': 0,
            'words': 0,
            'lines': 0,
            'sentences': 0
        }
    
    lines = text.split('\n')
    sentences = len(re.split(r'[.!?]+', text))
    
    return {
        'characters': count_characters(text, True),
        'characters_no_spaces': count_characters(text, False),
        'words': count_words(text),
        'lines': len(lines),
        'sentences': sentences
    }

def create_keyboard(buttons: List[List[str]], callback_data_prefix: str = "") -> List[List]:
    """
    Создает клавиатуру для inline кнопок
    """
    keyboard = []
    
    for row in buttons:
        keyboard_row = []
        for button_text in row:
            if callback_data_prefix:
                callback_data = f"{callback_data_prefix}_{button_text.lower().replace(' ', '_')}"
            else:
                callback_data = button_text.lower().replace(' ', '_')
            
            keyboard_row.append({
                'text': button_text,
                'callback_data': callback_data
            })
        keyboard.append(keyboard_row)
    
    return keyboard

def merge_settings(default_settings: Dict, user_settings: Dict) -> Dict:
    """
    Объединяет настройки по умолчанию с пользовательскими
    """
    merged = default_settings.copy()
    
    for key, value in user_settings.items():
        if key in merged:
            if isinstance(value, dict) and isinstance(merged[key], dict):
                merged[key] = merge_settings(merged[key], value)
            else:
                merged[key] = value
    
    return merged

def validate_settings(settings: Dict, schema: Dict) -> Dict:
    """
    Валидирует настройки по схеме
    """
    validated = {}
    errors = []
    
    for key, expected_type in schema.items():
        if key in settings:
            value = settings[key]
            
            # Проверяем тип
            if isinstance(expected_type, type):
                if not isinstance(value, expected_type):
                    errors.append(f"Поле '{key}' должно быть типа {expected_type.__name__}")
                    continue
            elif isinstance(expected_type, dict):
                if not isinstance(value, dict):
                    errors.append(f"Поле '{key}' должно быть словарем")
                    continue
                # Рекурсивная валидация для вложенных словарей
                nested_errors = validate_settings(value, expected_type)
                if nested_errors:
                    errors.extend([f"{key}.{error}" for error in nested_errors])
            
            validated[key] = value
        else:
            # Поле отсутствует, добавляем ошибку
            errors.append(f"Отсутствует обязательное поле '{key}'")
    
    if errors:
        raise ValueError(f"Ошибки валидации настроек: {'; '.join(errors)}")
    
    return validated

def export_settings(settings: Dict, format_type: str = 'json') -> str:
    """
    Экспортирует настройки в указанном формате
    """
    if format_type == 'json':
        return json.dumps(settings, indent=2, ensure_ascii=False)
    elif format_type == 'yaml':
        try:
            import yaml
            return yaml.dump(settings, default_flow_style=False, allow_unicode=True)
        except ImportError:
            raise ValueError("Для экспорта в YAML требуется установить PyYAML")
    else:
        raise ValueError(f"Неподдерживаемый формат: {format_type}")

def import_settings(settings_str: str, format_type: str = 'json') -> Dict:
    """
    Импортирует настройки из указанного формата
    """
    if format_type == 'json':
        return json.loads(settings_str)
    elif format_type == 'yaml':
        try:
            import yaml
            return yaml.safe_load(settings_str)
        except ImportError:
            raise ValueError("Для импорта из YAML требуется установить PyYAML")
    else:
        raise ValueError(f"Неподдерживаемый формат: {format_type}")