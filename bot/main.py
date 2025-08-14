from __future__ import annotations

import asyncio
import time
from typing import Optional

from telegram import (
	BotCommand,
	ChatPermissions,
	InlineKeyboardButton,
	InlineKeyboardMarkup,
	Message,
	Update,
)
from telegram.constants import ChatType
from telegram.ext import (
	AIORateLimiter,
	Application,
	ApplicationBuilder,
	CallbackQueryHandler,
	CommandHandler,
	ContextTypes,
	MessageHandler,
	filters,
)

from .config import Settings
from .i18n import t
from .storage import Storage, ChatSettings


RATE_WINDOW_SECONDS = 8
RATE_MAX_MESSAGES = 6


async def ensure_admin(update: Update, context: ContextTypes.DEFAULT_TYPE, settings: Settings) -> bool:
	user_id = update.effective_user.id if update.effective_user else 0
	if user_id in settings.admin_user_ids:
		return True
	chat = update.effective_chat
	if not chat or chat.type == ChatType.PRIVATE:
		return False
	member = await context.bot.get_chat_member(chat.id, user_id)
	status_name = getattr(member.status, "name", str(member.status)).lower()
	return status_name in ("administrator", "creator")


async def cmd_start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
	settings = context.bot_data["settings"]
	store: Storage = context.bot_data["store"]
	chat = update.effective_chat
	if not chat:
		return
	chat_settings = await store.get_or_create_chat(chat.id, settings.default_language)
	await update.effective_message.reply_text(
		t(chat_settings.language, "start_greeting")
	)


async def cmd_help(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
	settings = context.bot_data["settings"]
	store: Storage = context.bot_data["store"]
	chat = update.effective_chat
	if not chat:
		return
	chat_settings = await store.get_or_create_chat(chat.id, settings.default_language)
	await update.effective_message.reply_text(
		t(chat_settings.language, "help_text")
	)


def build_settings_keyboard(chat_settings: ChatSettings) -> InlineKeyboardMarkup:
	lang_code = chat_settings.language
	on = t(lang_code, "state_on")
	off = t(lang_code, "state_off")
	buttons = [
		[
			InlineKeyboardButton(
				text=t(lang_code, "toggle_welcome", state=on if chat_settings.welcome_enabled else off),
				callback_data=f"toggle:welcome:{int(not chat_settings.welcome_enabled)}",
			),
		],
		[
			InlineKeyboardButton(
				text=t(lang_code, "toggle_antispam", state=on if chat_settings.antispam_enabled else off),
				callback_data=f"toggle:antispam:{int(not chat_settings.antispam_enabled)}",
			),
		],
		[
			InlineKeyboardButton(text="RU", callback_data="lang:ru"),
			InlineKeyboardButton(text="EN", callback_data="lang:en"),
		],
	]
	return InlineKeyboardMarkup(buttons)


async def cmd_settings(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
	settings = context.bot_data["settings"]
	store: Storage = context.bot_data["store"]
	chat = update.effective_chat
	if not chat:
		return
	if not await ensure_admin(update, context, settings):
		await update.effective_message.reply_text(t(settings.default_language, "not_admin"))
		return
	chat_settings = await store.get_or_create_chat(chat.id, settings.default_language)
	keyboard = build_settings_keyboard(chat_settings)
	await update.effective_message.reply_text(
		t(chat_settings.language, "settings_title"),
		reply_markup=keyboard,
	)


async def on_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
	settings = context.bot_data["settings"]
	store: Storage = context.bot_data["store"]
	query = update.callback_query
	if not query:
		return
	chat = update.effective_chat
	if not chat:
		return
	chat_settings = await store.get_or_create_chat(chat.id, settings.default_language)
	if not await ensure_admin(update, context, settings):
		await query.answer(t(chat_settings.language, "not_admin"), show_alert=True)
		return
	data = query.data or ""
	if data.startswith("toggle:welcome:"):
		new_state = data.split(":")[-1] == "1"
		chat_settings.welcome_enabled = new_state
		await store.update_chat(chat_settings)
	elif data.startswith("toggle:antispam:"):
		new_state = data.split(":")[-1] == "1"
		chat_settings.antispam_enabled = new_state
		await store.update_chat(chat_settings)
	elif data.startswith("lang:"):
		new_lang = data.split(":")[-1]
		chat_settings.language = new_lang
		await store.update_chat(chat_settings)
		await query.answer(t(new_lang, "language_set", lang=new_lang), show_alert=True)
		await query.edit_message_text(
			t(new_lang, "settings_title"),
			reply_markup=build_settings_keyboard(chat_settings),
		)
		return
	await query.answer(t(chat_settings.language, "settings_updated"), show_alert=False)
	await query.edit_message_reply_markup(reply_markup=build_settings_keyboard(chat_settings))


async def cmd_setwelcome(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
	settings = context.bot_data["settings"]
	store: Storage = context.bot_data["store"]
	chat = update.effective_chat
	if not chat:
		return
	chat_settings = await store.get_or_create_chat(chat.id, settings.default_language)
	if not await ensure_admin(update, context, settings):
		await update.effective_message.reply_text(t(chat_settings.language, "not_admin"))
		return
	args_text = (update.effective_message.text or "").split(maxsplit=1)
	if len(args_text) < 2:
		await update.effective_message.reply_text(t(chat_settings.language, "provide_text"))
		return
	chat_settings.welcome_text = args_text[1].strip()
	await store.update_chat(chat_settings)
	await update.effective_message.reply_text(t(chat_settings.language, "welcome_updated"))


async def on_new_member(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
	settings = context.bot_data["settings"]
	store: Storage = context.bot_data["store"]
	chat = update.effective_chat
	msg: Optional[Message] = update.effective_message
	if not chat or not msg or not msg.new_chat_members:
		return
	chat_settings = await store.get_or_create_chat(chat.id, settings.default_language)
	if not chat_settings.welcome_enabled:
		return
	names = ", ".join([m.mention_html() for m in msg.new_chat_members])
	text = f"{chat_settings.welcome_text} {names}"
	await msg.reply_html(text)


async def antispam_middleware(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
	if update.effective_chat is None or update.effective_user is None:
		return
	settings = context.bot_data["settings"]
	store: Storage = context.bot_data["store"]
	chat_settings = await store.get_or_create_chat(update.effective_chat.id, settings.default_language)
	if not chat_settings.antispam_enabled:
		return
	now = int(time.time())
	key = (update.effective_chat.id, update.effective_user.id)
	record = await store.get_rate_limit(*key)
	if record is None:
		await store.set_rate_limit(*key, last_ts=now, msg_count=1)
		return
	last_ts, count = record
	if now - last_ts <= RATE_WINDOW_SECONDS:
		count += 1
		await store.set_rate_limit(*key, last_ts=last_ts, msg_count=count)
		if count > RATE_MAX_MESSAGES:
			try:
				await context.bot.restrict_chat_member(
					chat_id=update.effective_chat.id,
					user_id=update.effective_user.id,
					permissions=ChatPermissions(can_send_messages=False, can_send_audios=False, can_send_documents=False, can_send_photos=False, can_send_videos=False, can_send_video_notes=False, can_send_voice_notes=False, can_send_polls=False, can_send_other_messages=False, can_add_web_page_previews=False),
					until_date=now + 60,
				)
			except Exception:
				pass
			return
	else:
		await store.set_rate_limit(*key, last_ts=now, msg_count=1)


async def main() -> None:
	settings = Settings.load()
	if not settings.bot_token:
		raise RuntimeError("BOT_TOKEN is not set. Provide it via environment or .env file.")

	store = Storage(settings.database_path)
	await store.initialize()

	application: Application = (
		ApplicationBuilder()
			.token(settings.bot_token)
			.rate_limiter(AIORateLimiter())
			.build()
	)
	application.bot_data["settings"] = settings
	application.bot_data["store"] = store

	application.add_handler(CommandHandler("start", cmd_start))
	application.add_handler(CommandHandler("help", cmd_help))
	application.add_handler(CommandHandler("settings", cmd_settings))
	application.add_handler(CommandHandler("setwelcome", cmd_setwelcome))
	application.add_handler(CallbackQueryHandler(on_callback))
	application.add_handler(MessageHandler(filters.StatusUpdate.NEW_CHAT_MEMBERS, on_new_member))

	application.add_handler(MessageHandler(~filters.StatusUpdate.ALL & ~filters.COMMAND, antispam_middleware), group=1)

	await application.bot.set_my_commands(
		[
			BotCommand("start", "Запуск / Start"),
			BotCommand("help", "Помощь / Help"),
			BotCommand("settings", "Настройки чата"),
			BotCommand("setwelcome", "Изменить приветствие"),
		]
	)

	print("Bot started. Press Ctrl+C to stop.")
	await application.run_polling(close_loop=False)


if __name__ == "__main__":
	asyncio.run(main())