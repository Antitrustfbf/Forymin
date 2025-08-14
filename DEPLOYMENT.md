# 🚀 Руководство по развертыванию Forymin Bot

Подробные инструкции по установке, настройке и запуску бота в продакшене.

## 📋 Предварительные требования

### Системные требования
- **ОС**: Linux (Ubuntu 18.04+, CentOS 7+), macOS, Windows 10+
- **Python**: 3.8 или выше
- **RAM**: Минимум 512 MB, рекомендуется 1 GB+
- **Диск**: Минимум 100 MB свободного места
- **Сеть**: Стабильное интернет-соединение

### Программное обеспечение
- Python 3.8+
- pip (менеджер пакетов Python)
- git (для клонирования репозитория)
- SQLite3 (обычно входит в Python)

## 🔧 Установка

### 1. Клонирование репозитория
```bash
git clone https://github.com/your-username/forymin.git
cd forymin
```

### 2. Создание виртуального окружения (рекомендуется)
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install python3-venv

# Создание виртуального окружения
python3 -m venv venv

# Активация
source venv/bin/activate  # Linux/macOS
# или
venv\Scripts\activate     # Windows
```

### 3. Установка зависимостей
```bash
pip install --upgrade pip
pip install -r requirements.txt
```

## ⚙️ Настройка

### 1. Создание бота в Telegram
1. Найдите @BotFather в Telegram
2. Отправьте команду `/newbot`
3. Следуйте инструкциям:
   - Введите имя бота (например, "Forymin Moderation Bot")
   - Введите username (например, "forymin_bot")
4. Сохраните полученный токен

### 2. Настройка переменных окружения
```bash
cp .env.example .env
```

Отредактируйте файл `.env`:
```env
# Токен вашего бота (обязательно)
BOT_TOKEN=1234567890:ABCdefGHIjklMNOpqrsTUVwxyz

# Username бота без @ (обязательно)
BOT_USERNAME=forymin_bot

# Путь к базе данных (опционально)
DATABASE_PATH=bot_database.db

# Уровень логирования (опционально)
LOG_LEVEL=INFO
```

### 3. Настройка прав бота
В каждом чате, где будет работать бот, необходимо:

1. **Добавить бота в чат** как обычного участника
2. **Назначить права администратора** с разрешениями:
   - ✅ Удаление сообщений
   - ✅ Блокировка пользователей
   - ✅ Ограничение пользователей
   - ✅ Закрепление сообщений
   - ✅ Управление чатом

## 🚀 Запуск

### Тестовый запуск
```bash
python bot.py
```

При успешном запуске вы увидите:
```
INFO - Бот запускается...
INFO - База данных инициализирована успешно
```

### Проверка работы
1. Отправьте `/start` боту в личные сообщения
2. Добавьте бота в тестовый чат
3. Отправьте `/help` для проверки команд

## 🏗️ Продакшн развертывание

### 1. Использование systemd (Linux)

Создайте файл службы:
```bash
sudo nano /etc/systemd/system/forymin-bot.service
```

Содержимое файла:
```ini
[Unit]
Description=Forymin Telegram Bot
After=network.target

[Service]
Type=simple
User=your-username
WorkingDirectory=/path/to/forymin
Environment=PATH=/path/to/forymin/venv/bin
ExecStart=/path/to/forymin/venv/bin/python bot.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Активация службы:
```bash
sudo systemctl daemon-reload
sudo systemctl enable forymin-bot
sudo systemctl start forymin-bot
sudo systemctl status forymin-bot
```

### 2. Использование Docker

Создайте `Dockerfile`:
```dockerfile
FROM python:3.9-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

CMD ["python", "bot.py"]
```

Создайте `docker-compose.yml`:
```yaml
version: '3.8'

services:
  forymin-bot:
    build: .
    environment:
      - BOT_TOKEN=${BOT_TOKEN}
      - BOT_USERNAME=${BOT_USERNAME}
    volumes:
      - ./data:/app/data
    restart: unless-stopped
```

Запуск:
```bash
docker-compose up -d
```

### 3. Использование screen/tmux

```bash
# Создание новой сессии
screen -S forymin-bot

# Запуск бота
python bot.py

# Отключение от сессии: Ctrl+A, затем D
# Подключение к сессии
screen -r forymin-bot
```

## 📊 Мониторинг и логирование

### Просмотр логов
```bash
# systemd
sudo journalctl -u forymin-bot -f

# Docker
docker-compose logs -f forymin-bot

# Ручной запуск
tail -f bot.log
```

### Проверка статуса
```bash
# systemd
sudo systemctl status forymin-bot

# Docker
docker-compose ps

# Проверка процесса
ps aux | grep bot.py
```

## 🔒 Безопасность

### 1. Защита токена
- Никогда не публикуйте токен в открытом доступе
- Используйте переменные окружения
- Ограничьте доступ к файлу `.env`

### 2. Права доступа
```bash
chmod 600 .env
chown your-username:your-username .env
```

### 3. Файрвол
```bash
# Ubuntu/Debian
sudo ufw allow ssh
sudo ufw enable

# CentOS/RHEL
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --reload
```

## 🚨 Устранение неполадок

### Частые проблемы

#### 1. Бот не отвечает
```bash
# Проверьте статус
sudo systemctl status forymin-bot

# Проверьте логи
sudo journalctl -u forymin-bot -n 50

# Проверьте токен
grep BOT_TOKEN .env
```

#### 2. Ошибки базы данных
```bash
# Проверьте права доступа
ls -la bot_database.db

# Пересоздайте базу
rm bot_database.db
python bot.py
```

#### 3. Проблемы с зависимостями
```bash
# Обновите pip
pip install --upgrade pip

# Переустановите зависимости
pip uninstall -r requirements.txt
pip install -r requirements.txt
```

### Полезные команды
```bash
# Проверка версии Python
python --version

# Проверка установленных пакетов
pip list

# Проверка свободного места
df -h

# Проверка использования памяти
free -h

# Проверка сетевых соединений
netstat -tulpn | grep python
```

## 📈 Масштабирование

### 1. Несколько экземпляров
Для больших чатов можно запустить несколько экземпляров бота:

```bash
# Экземпляр 1
python bot.py --instance 1

# Экземпляр 2  
python bot.py --instance 2
```

### 2. Балансировка нагрузки
Используйте nginx или haproxy для распределения запросов между экземплярами.

### 3. Мониторинг производительности
```bash
# Установка htop
sudo apt install htop

# Мониторинг в реальном времени
htop
```

## 🔄 Обновления

### 1. Автоматические обновления
Создайте скрипт обновления:
```bash
#!/bin/bash
cd /path/to/forymin
git pull origin main
pip install -r requirements.txt
sudo systemctl restart forymin-bot
```

### 2. Ручные обновления
```bash
cd /path/to/forymin
git pull origin main
pip install -r requirements.txt
sudo systemctl restart forymin-bot
```

## 📞 Поддержка

### Полезные ресурсы
- [python-telegram-bot документация](https://python-telegram-bot.readthedocs.io/)
- [Telegram Bot API](https://core.telegram.org/bots/api)
- [Python документация](https://docs.python.org/)

### Получение помощи
1. Проверьте логи на наличие ошибок
2. Убедитесь в корректности настроек
3. Создайте issue в репозитории с описанием проблемы

---

**Успешного развертывания! 🚀**