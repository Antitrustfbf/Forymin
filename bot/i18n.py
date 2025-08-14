from __future__ import annotations

from typing import Mapping


TRANSLATIONS: Mapping[str, Mapping[str, str]] = {
	"ru": {
		"start_greeting": "Привет! Я бот-помощник для чатов. Используйте /help, чтобы узнать больше.",
		"help_text": (
			"Я умею:\n"
			"• Приветствовать новых участников\n"
			"• Ограничивать спам\n"
			"• Давать админам панель настроек: /settings\n"
			"Команды: /start, /help, /settings, /setwelcome <текст>"
		),
		"settings_title": "Настройки чата",
		"toggle_welcome": "Приветствие: {state}",
		"toggle_antispam": "Анти-спам: {state}",
		"state_on": "вкл",
		"state_off": "выкл",
		"language_title": "Язык",
		"language_set": "Язык чата изменён на: {lang}",
		"welcome_updated": "Текст приветствия обновлён.",
		"not_admin": "Только администраторы могут это делать.",
		"provide_text": "Укажите текст после команды.",
		"settings_updated": "Настройки обновлены.",
	},
	"en": {
		"start_greeting": "Hi! I'm a chat helper bot. Use /help to learn more.",
		"help_text": (
			"I can:\n"
			"• Welcome new members\n"
			"• Rate-limit spam\n"
			"• Provide admin settings panel: /settings\n"
			"Commands: /start, /help, /settings, /setwelcome <text>"
		),
		"settings_title": "Chat settings",
		"toggle_welcome": "Welcome: {state}",
		"toggle_antispam": "Anti-spam: {state}",
		"state_on": "on",
		"state_off": "off",
		"language_title": "Language",
		"language_set": "Chat language changed to: {lang}",
		"welcome_updated": "Welcome text updated.",
		"not_admin": "Only administrators can do this.",
		"provide_text": "Provide text after the command.",
		"settings_updated": "Settings updated.",
	},
}


def t(lang: str, key: str, **kwargs: object) -> str:
	lang_code = (lang or "ru").split("-")[0].lower()
	bundle = TRANSLATIONS.get(lang_code) or TRANSLATIONS.get("ru", {})
	text = bundle.get(key) or TRANSLATIONS["ru"].get(key) or key
	try:
		return text.format(**kwargs)
	except Exception:
		return text