from __future__ import annotations

import os
from pathlib import Path
from typing import Set

from dotenv import load_dotenv
from pydantic import BaseModel, Field


class Settings(BaseModel):
	bot_token: str | None = Field(default=None)
	database_path: str = Field(default="data/bot.db")
	default_language: str = Field(default="ru")
	admin_user_ids: Set[int] = Field(default_factory=set)

	@classmethod
	def load(cls) -> "Settings":
		load_dotenv()
		bot_token = os.getenv("BOT_TOKEN")
		database_path = os.getenv("DATABASE_PATH", "data/bot.db")
		default_language = os.getenv("DEFAULT_LANGUAGE", "ru")
		admin_ids_raw = os.getenv("ADMIN_USER_IDS", "").strip()

		admin_ids: Set[int] = set()
		if admin_ids_raw:
			for part in admin_ids_raw.replace(",", " ").split():
				try:
					admin_ids.add(int(part))
				except ValueError:
					continue

		# Ensure data directory exists
		data_dir = Path(database_path).parent
		data_dir.mkdir(parents=True, exist_ok=True)

		return cls(
			bot_token=bot_token,
			database_path=database_path,
			default_language=default_language,
			admin_user_ids=admin_ids,
		)