import re
import logging
import asyncio
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Tuple
from collections import defaultdict, deque
from config import MODERATION_SETTINGS

logger = logging.getLogger(__name__)

class ModerationManager:
    def __init__(self):
        self.spam_trackers = defaultdict(lambda: defaultdict(lambda: deque(maxlen=60)))  # 60 секунд
        self.flood_trackers = defaultdict(lambda: defaultdict(lambda: deque(maxlen=10)))  # 10 сообщений
        self.user_message_count = defaultdict(lambda: defaultdict(int))
        self.last_message_time = defaultdict(lambda: defaultdict(float))
        
        # Загружаем настройки модерации
        self.spam_threshold = MODERATION_SETTINGS['spam_threshold']
        self.flood_threshold = MODERATION_SETTINGS['flood_threshold']
        self.forbidden_words = MODERATION_SETTINGS['forbidden_words']
        self.link_whitelist = MODERATION_SETTINGS['link_whitelist']
        self.auto_warn = MODERATION_SETTINGS['auto_warn']
        self.auto_mute = MODERATION_SETTINGS['auto_mute']
        self.mute_duration = MODERATION_SETTINGS['mute_duration']
    
    def check_message(self, chat_id: int, user_id: int, message_text: str, 
                     user_role: str = 'member') -> Dict:
        """
        Проверка сообщения на нарушения
        Возвращает словарь с результатами проверки
        """
        violations = []
        severity = 'low'
        
        # Пропускаем администраторов
        if user_role in ['admin', 'creator']:
            return {'clean': True, 'violations': [], 'severity': 'none'}
        
        # Проверка на спам
        if self._is_spam(chat_id, user_id):
            violations.append('spam')
            severity = 'high'
        
        # Проверка на флуд
        if self._is_flood(chat_id, user_id, message_text):
            violations.append('flood')
            severity = 'medium'
        
        # Проверка на запрещенные слова
        forbidden_word = self._contains_forbidden_words(message_text)
        if forbidden_word:
            violations.append(f'forbidden_word: {forbidden_word}')
            severity = 'medium'
        
        # Проверка на нежелательные ссылки
        if self._contains_suspicious_links(message_text):
            violations.append('suspicious_link')
            severity = 'high'
        
        # Проверка на капс
        if self._is_excessive_caps(message_text):
            violations.append('excessive_caps')
            severity = 'low'
        
        # Проверка на повторяющиеся символы
        if self._is_repetitive_chars(message_text):
            violations.append('repetitive_chars')
            severity = 'low'
        
        return {
            'clean': len(violations) == 0,
            'violations': violations,
            'severity': severity,
            'action_required': severity in ['medium', 'high']
        }
    
    def _is_spam(self, chat_id: int, user_id: int) -> bool:
        """Проверка на спам (количество сообщений в минуту)"""
        current_time = datetime.now()
        user_tracker = self.spam_trackers[chat_id][user_id]
        
        # Удаляем старые записи (старше 60 секунд)
        while user_tracker and (current_time - user_tracker[0]).total_seconds() > 60:
            user_tracker.popleft()
        
        # Добавляем текущее сообщение
        user_tracker.append(current_time)
        
        # Проверяем количество сообщений
        return len(user_tracker) > self.spam_threshold
    
    def _is_flood(self, chat_id: int, user_id: int, message_text: str) -> bool:
        """Проверка на флуд (одинаковые сообщения подряд)"""
        user_tracker = self.flood_trackers[chat_id][user_id]
        
        # Проверяем, не повторяется ли сообщение
        if user_tracker and user_tracker[-1] == message_text:
            user_tracker.append(message_text)
            return len(user_tracker) > self.flood_threshold
        else:
            user_tracker.clear()
            user_tracker.append(message_text)
            return False
    
    def _contains_forbidden_words(self, text: str) -> Optional[str]:
        """Проверка на запрещенные слова"""
        text_lower = text.lower()
        for word in self.forbidden_words:
            if word.lower() in text_lower:
                return word
        return None
    
    def _contains_suspicious_links(self, text: str) -> bool:
        """Проверка на подозрительные ссылки"""
        # Ищем ссылки в тексте
        url_pattern = r'https?://[^\s]+'
        urls = re.findall(url_pattern, text)
        
        if not urls:
            return False
        
        # Проверяем каждую ссылку
        for url in urls:
            # Если ссылка не в белом списке, считаем подозрительной
            if not any(whitelist_domain in url.lower() for whitelist_domain in self.link_whitelist):
                return True
        
        return False
    
    def _is_excessive_caps(self, text: str) -> bool:
        """Проверка на избыточное использование заглавных букв"""
        if len(text) < 10:
            return False
        
        caps_count = sum(1 for char in text if char.isupper())
        caps_percentage = caps_count / len(text)
        
        return caps_percentage > 0.7  # Более 70% заглавных букв
    
    def _is_repetitive_chars(self, text: str) -> bool:
        """Проверка на повторяющиеся символы"""
        if len(text) < 5:
            return False
        
        # Ищем повторяющиеся символы (3 и более подряд)
        for i in range(len(text) - 2):
            if text[i] == text[i+1] == text[i+2]:
                return True
        
        return False
    
    def get_moderation_action(self, violations: List[str], severity: str, 
                            user_warnings: int = 0) -> Dict:
        """
        Определяет действие модерации на основе нарушений
        """
        if not violations:
            return {'action': 'none', 'reason': 'Нарушений не обнаружено'}
        
        # Определяем действие на основе серьезности
        if severity == 'high':
            if user_warnings >= 2:
                action = 'ban'
                reason = f'Множественные серьезные нарушения: {", ".join(violations)}'
            else:
                action = 'mute'
                reason = f'Серьезное нарушение: {", ".join(violations)}'
                duration = self.mute_duration * 2  # Удвоенное время мута
        elif severity == 'medium':
            if user_warnings >= 1:
                action = 'mute'
                reason = f'Повторное нарушение: {", ".join(violations)}'
                duration = self.mute_duration
            else:
                action = 'warn'
                reason = f'Нарушение: {", ".join(violations)}'
                duration = None
        else:  # low severity
            action = 'warn'
            reason = f'Незначительное нарушение: {", ".join(violations)}'
            duration = None
        
        return {
            'action': action,
            'reason': reason,
            'duration': duration,
            'auto_action': True
        }
    
    def update_settings(self, new_settings: Dict):
        """Обновление настроек модерации"""
        for key, value in new_settings.items():
            if hasattr(self, key):
                setattr(self, key, value)
                logger.info(f"Настройка модерации обновлена: {key} = {value}")
    
    def get_moderation_stats(self, chat_id: int) -> Dict:
        """Получение статистики модерации для чата"""
        return {
            'spam_trackers': len(self.spam_trackers[chat_id]),
            'flood_trackers': len(self.flood_trackers[chat_id]),
            'active_users': len(self.user_message_count[chat_id]),
            'settings': {
                'spam_threshold': self.spam_threshold,
                'flood_threshold': self.flood_threshold,
                'auto_warn': self.auto_warn,
                'auto_mute': self.auto_mute,
                'mute_duration': self.mute_duration
            }
        }
    
    def reset_user_tracking(self, chat_id: int, user_id: int):
        """Сброс отслеживания для конкретного пользователя"""
        if chat_id in self.spam_trackers and user_id in self.spam_trackers[chat_id]:
            self.spam_trackers[chat_id][user_id].clear()
        
        if chat_id in self.flood_trackers and user_id in self.flood_trackers[chat_id]:
            self.flood_trackers[chat_id][user_id].clear()
        
        if chat_id in self.user_message_count and user_id in self.user_message_count[chat_id]:
            self.user_message_count[chat_id][user_id] = 0
        
        logger.info(f"Сброшено отслеживание для пользователя {user_id} в чате {chat_id}")
    
    def cleanup_old_data(self, max_age_hours: int = 24):
        """Очистка старых данных отслеживания"""
        current_time = datetime.now()
        max_age = timedelta(hours=max_age_hours)
        
        for chat_id in list(self.spam_trackers.keys()):
            for user_id in list(self.spam_trackers[chat_id].keys()):
                user_tracker = self.spam_trackers[chat_id][user_id]
                
                # Удаляем старые записи
                while user_tracker and (current_time - user_tracker[0]) > max_age:
                    user_tracker.popleft()
                
                # Если трекер пуст, удаляем его
                if not user_tracker:
                    del self.spam_trackers[chat_id][user_id]
            
            # Если в чате нет активных трекеров, удаляем чат
            if not self.spam_trackers[chat_id]:
                del self.spam_trackers[chat_id]
        
        logger.info("Очистка старых данных модерации завершена")