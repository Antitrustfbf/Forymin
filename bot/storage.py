from __future__ import annotations

import asyncio
from dataclasses import dataclass
from typing import Optional, Tuple

import aiosqlite


@dataclass
class ChatSettings:
	chat_id: int
	language: str
	welcome_enabled: bool
	antispam_enabled: bool
	welcome_text: str


class Storage:
	def __init__(self, db_path: str) -> None:
		self._db_path = db_path
		self._init_lock = asyncio.Lock()

	async def initialize(self) -> None:
		async with self._init_lock:
			async with aiosqlite.connect(self._db_path) as db:
				await db.execute(
					"""
					CREATE TABLE IF NOT EXISTS chat_settings (
						chat_id INTEGER PRIMARY KEY,
						language TEXT NOT NULL,
						welcome_enabled INTEGER NOT NULL,
						antispam_enabled INTEGER NOT NULL,
						welcome_text TEXT NOT NULL
					);
					"""
				)
				await db.execute(
					"""
					CREATE TABLE IF NOT EXISTS rate_limits (
						chat_id INTEGER NOT NULL,
						user_id INTEGER NOT NULL,
						last_ts INTEGER NOT NULL,
						msg_count INTEGER NOT NULL,
						PRIMARY KEY (chat_id, user_id)
					);
					"""
				)
				await db.commit()

	async def get_or_create_chat(self, chat_id: int, default_language: str) -> ChatSettings:
		async with aiosqlite.connect(self._db_path) as db:
			cur = await db.execute(
				"SELECT chat_id, language, welcome_enabled, antispam_enabled, welcome_text FROM chat_settings WHERE chat_id=?",
				(chat_id,),
			)
			row = await cur.fetchone()
			await cur.close()
			if row:
				return ChatSettings(
					chat_id=row[0],
					language=row[1],
					welcome_enabled=bool(row[2]),
					antispam_enabled=bool(row[3]),
					welcome_text=row[4],
				)
			default_welcome = "Добро пожаловать!" if default_language.startswith("ru") else "Welcome!"
			await db.execute(
				"INSERT OR REPLACE INTO chat_settings(chat_id, language, welcome_enabled, antispam_enabled, welcome_text) VALUES(?,?,?,?,?)",
				(chat_id, default_language, 1, 1, default_welcome),
			)
			await db.commit()
			return ChatSettings(chat_id, default_language, True, True, default_welcome)

	async def update_chat(self, settings: ChatSettings) -> None:
		async with aiosqlite.connect(self._db_path) as db:
			await db.execute(
				"UPDATE chat_settings SET language=?, welcome_enabled=?, antispam_enabled=?, welcome_text=? WHERE chat_id=?",
				(
					settings.language,
					1 if settings.welcome_enabled else 0,
					1 if settings.antispam_enabled else 0,
					settings.welcome_text,
					settings.chat_id,
				),
			)
			await db.commit()

	async def get_rate_limit(self, chat_id: int, user_id: int) -> Optional[Tuple[int, int]]:
		async with aiosqlite.connect(self._db_path) as db:
			cur = await db.execute(
				"SELECT last_ts, msg_count FROM rate_limits WHERE chat_id=? AND user_id=?",
				(chat_id, user_id),
			)
			row = await cur.fetchone()
			await cur.close()
			return (row[0], row[1]) if row else None

	async def set_rate_limit(self, chat_id: int, user_id: int, last_ts: int, msg_count: int) -> None:
		async with aiosqlite.connect(self._db_path) as db:
			await db.execute(
				"INSERT OR REPLACE INTO rate_limits(chat_id, user_id, last_ts, msg_count) VALUES(?,?,?,?)",
				(chat_id, user_id, last_ts, msg_count),
			)
			await db.commit()