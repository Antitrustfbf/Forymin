# Termux Форум (PHP + MySQL)

Простой, современный и адаптивный форум на PHP, CSS и JS. Работает в Termux (Android) и на любом Linux-сервере.

## Установка в Termux

1. Обновить пакеты и установить зависимости:
```bash
pkg update -y && pkg upgrade -y
pkg install -y php php-mysql mariadb
```

2. Запустить и настроить MariaDB (первый запуск):
```bash
mysqld_safe --datadir=$PREFIX/var/lib/mysql &
# Подождите 5-10 секунд, затем установите пароль root (необязательно)
mysql -u root -e "SELECT VERSION();"
```

3. Настроить подключение к БД в `config.php` (или задайте переменные окружения `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`).

4. Создать БД и таблицы (способ 1 — скриптом):
```bash
php /workspace/init_db.php
```

Или (способ 2 — вручную):
```bash
mysql -u root < /workspace/database.sql
```

5. Запустить встроенный PHP-сервер:
```bash
php -S 0.0.0.0:8000 -t /workspace/public
```

6. Открыть в браузере: `http://127.0.0.1:8000`

## Структура
- `config.php` — конфигурация БД
- `includes/` — подключаемые файлы (`bootstrap.php`, `db.php`, `functions.php`)
- `public/` — публичные страницы (`index.php`, `new_thread.php`, `thread.php`) и ассеты
- `init_db.php` — инициализация БД
- `database.sql` — SQL-схема

## Безопасность
- Подготовленные запросы (PDO)
- CSRF-токены в формах
- Экранирование HTML

## Лицензия
MIT