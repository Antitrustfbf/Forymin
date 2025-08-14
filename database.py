import sqlite3
import asyncio
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Tuple
import logging

logger = logging.getLogger(__name__)

class ChatStatisticsDB:
    def __init__(self, db_path: str):
        self.db_path = db_path
        self.init_database()
    
    def init_database(self):
        """Initialize the database with required tables"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                
                # Create chats table
                cursor.execute('''
                    CREATE TABLE IF NOT EXISTS chats (
                        chat_id INTEGER PRIMARY KEY,
                        chat_type TEXT NOT NULL,
                        title TEXT,
                        username TEXT,
                        first_name TEXT,
                        last_name TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ''')
                
                # Create messages table
                cursor.execute('''
                    CREATE TABLE IF NOT EXISTS messages (
                        message_id INTEGER,
                        chat_id INTEGER,
                        user_id INTEGER,
                        username TEXT,
                        first_name TEXT,
                        last_name TEXT,
                        message_type TEXT,
                        message_text TEXT,
                        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (message_id, chat_id),
                        FOREIGN KEY (chat_id) REFERENCES chats (chat_id)
                    )
                ''')
                
                # Create user_stats table
                cursor.execute('''
                    CREATE TABLE IF NOT EXISTS user_stats (
                        user_id INTEGER,
                        chat_id INTEGER,
                        message_count INTEGER DEFAULT 0,
                        first_message TIMESTAMP,
                        last_message TIMESTAMP,
                        PRIMARY KEY (user_id, chat_id),
                        FOREIGN KEY (chat_id) REFERENCES chats (chat_id)
                    )
                ''')
                
                # Create chat_stats table
                cursor.execute('''
                    CREATE TABLE IF NOT EXISTS chat_stats (
                        chat_id INTEGER PRIMARY KEY,
                        total_messages INTEGER DEFAULT 0,
                        total_users INTEGER DEFAULT 0,
                        active_users INTEGER DEFAULT 0,
                        last_activity TIMESTAMP,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (chat_id) REFERENCES chats (chat_id)
                    )
                ''')
                
                conn.commit()
                logger.info("Database initialized successfully")
                
        except Exception as e:
            logger.error(f"Error initializing database: {e}")
            raise
    
    async def add_chat(self, chat_id: int, chat_type: str, title: str = None, 
                       username: str = None, first_name: str = None, last_name: str = None):
        """Add or update chat information"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                cursor.execute('''
                    INSERT OR REPLACE INTO chats 
                    (chat_id, chat_type, title, username, first_name, last_name, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ''', (chat_id, chat_type, title, username, first_name, last_name))
                conn.commit()
                logger.info(f"Chat {chat_id} added/updated successfully")
        except Exception as e:
            logger.error(f"Error adding chat {chat_id}: {e}")
    
    async def add_message(self, message_id: int, chat_id: int, user_id: int,
                         username: str = None, first_name: str = None, last_name: str = None,
                         message_type: str = "text", message_text: str = None):
        """Add a new message to the database"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                
                # Add message
                cursor.execute('''
                    INSERT OR REPLACE INTO messages 
                    (message_id, chat_id, user_id, username, first_name, last_name, 
                     message_type, message_text, timestamp)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ''', (message_id, chat_id, user_id, username, first_name, last_name,
                      message_type, message_text))
                
                # Update user stats
                cursor.execute('''
                    INSERT OR REPLACE INTO user_stats 
                    (user_id, chat_id, message_count, first_message, last_message)
                    VALUES (
                        ?, ?, 
                        COALESCE((SELECT message_count + 1 FROM user_stats WHERE user_id = ? AND chat_id = ?), 1),
                        COALESCE((SELECT first_message FROM user_stats WHERE user_id = ? AND chat_id = ?), CURRENT_TIMESTAMP),
                        CURRENT_TIMESTAMP
                    )
                ''', (user_id, chat_id, user_id, chat_id, user_id, chat_id))
                
                # Update chat stats
                cursor.execute('''
                    INSERT OR REPLACE INTO chat_stats 
                    (chat_id, total_messages, total_users, active_users, last_activity, updated_at)
                    VALUES (
                        ?, 
                        COALESCE((SELECT total_messages + 1 FROM chat_stats WHERE chat_id = ?), 1),
                        (SELECT COUNT(DISTINCT user_id) FROM user_stats WHERE chat_id = ?),
                        (SELECT COUNT(DISTINCT user_id) FROM messages 
                         WHERE chat_id = ? AND timestamp > datetime('now', '-7 days')),
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )
                ''', (chat_id, chat_id, chat_id, chat_id))
                
                conn.commit()
                logger.info(f"Message {message_id} added successfully")
                
        except Exception as e:
            logger.error(f"Error adding message {message_id}: {e}")
    
    async def get_chat_statistics(self, chat_id: int) -> Dict:
        """Get comprehensive statistics for a specific chat"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                
                # Get chat info
                cursor.execute('SELECT * FROM chats WHERE chat_id = ?', (chat_id,))
                chat_info = cursor.fetchone()
                
                if not chat_info:
                    return None
                
                # Get chat stats
                cursor.execute('SELECT * FROM chat_stats WHERE chat_id = ?', (chat_id,))
                chat_stats = cursor.fetchone()
                
                # Get top users
                cursor.execute('''
                    SELECT us.user_id, us.message_count, us.first_message, us.last_message,
                           m.username, m.first_name, m.last_name
                    FROM user_stats us
                    LEFT JOIN messages m ON us.user_id = m.user_id AND us.chat_id = m.chat_id
                    WHERE us.chat_id = ?
                    ORDER BY us.message_count DESC
                    LIMIT 10
                ''', (chat_id,))
                top_users = cursor.fetchall()
                
                # Get message activity by day (last 7 days)
                cursor.execute('''
                    SELECT DATE(timestamp) as date, COUNT(*) as count
                    FROM messages 
                    WHERE chat_id = ? AND timestamp > datetime('now', '-7 days')
                    GROUP BY DATE(timestamp)
                    ORDER BY date
                ''', (chat_id,))
                daily_activity = cursor.fetchall()
                
                # Get message types distribution
                cursor.execute('''
                    SELECT message_type, COUNT(*) as count
                    FROM messages 
                    WHERE chat_id = ?
                    GROUP BY message_type
                ''', (chat_id,))
                message_types = cursor.fetchall()
                
                return {
                    'chat_info': {
                        'chat_id': chat_info[0],
                        'chat_type': chat_info[1],
                        'title': chat_info[2],
                        'username': chat_info[3],
                        'first_name': chat_info[4],
                        'last_name': chat_info[5],
                        'created_at': chat_info[6],
                        'updated_at': chat_info[7]
                    },
                    'chat_stats': {
                        'total_messages': chat_stats[1] if chat_stats else 0,
                        'total_users': chat_stats[2] if chat_stats else 0,
                        'active_users': chat_stats[3] if chat_stats else 0,
                        'last_activity': chat_stats[4] if chat_stats else None,
                        'created_at': chat_stats[5] if chat_stats else None,
                        'updated_at': chat_stats[6] if chat_stats else None
                    },
                    'top_users': [
                        {
                            'user_id': user[0],
                            'message_count': user[1],
                            'first_message': user[2],
                            'last_message': user[3],
                            'username': user[4],
                            'first_name': user[5],
                            'last_name': user[6]
                        } for user in top_users
                    ],
                    'daily_activity': [
                        {'date': day[0], 'count': day[1]} for day in daily_activity
                    ],
                    'message_types': [
                        {'type': msg_type[0], 'count': msg_type[1]} for msg_type in message_types
                    ]
                }
                
        except Exception as e:
            logger.error(f"Error getting chat statistics for {chat_id}: {e}")
            return None
    
    async def get_user_statistics(self, user_id: int, chat_id: int = None) -> Dict:
        """Get statistics for a specific user"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                
                if chat_id:
                    # Get user stats for specific chat
                    cursor.execute('''
                        SELECT * FROM user_stats WHERE user_id = ? AND chat_id = ?
                    ''', (user_id, chat_id))
                    user_stats = cursor.fetchone()
                    
                    if not user_stats:
                        return None
                    
                    return {
                        'user_id': user_stats[0],
                        'chat_id': user_stats[1],
                        'message_count': user_stats[2],
                        'first_message': user_stats[3],
                        'last_message': user_stats[4]
                    }
                else:
                    # Get user stats across all chats
                    cursor.execute('''
                        SELECT chat_id, message_count, first_message, last_message
                        FROM user_stats WHERE user_id = ?
                        ORDER BY message_count DESC
                    ''', (user_id,))
                    all_chat_stats = cursor.fetchall()
                    
                    total_messages = sum(stat[1] for stat in all_chat_stats)
                    
                    return {
                        'user_id': user_id,
                        'total_messages': total_messages,
                        'chats_participated': len(all_chat_stats),
                        'chat_stats': [
                            {
                                'chat_id': stat[0],
                                'message_count': stat[1],
                                'first_message': stat[2],
                                'last_message': stat[3]
                            } for stat in all_chat_stats
                        ]
                    }
                    
        except Exception as e:
            logger.error(f"Error getting user statistics for {user_id}: {e}")
            return None
    
    async def cleanup_old_messages(self, max_messages_per_chat: int = 10000):
        """Clean up old messages to prevent database bloat"""
        try:
            with sqlite3.connect(self.db_path) as conn:
                cursor = conn.cursor()
                
                # Get chats with too many messages
                cursor.execute('''
                    SELECT chat_id, COUNT(*) as message_count
                    FROM messages 
                    GROUP BY chat_id 
                    HAVING message_count > ?
                ''', (max_messages_per_chat,))
                
                chats_to_clean = cursor.fetchall()
                
                for chat_id, message_count in chats_to_clean:
                    # Delete oldest messages beyond the limit
                    cursor.execute('''
                        DELETE FROM messages 
                        WHERE chat_id = ? AND message_id IN (
                            SELECT message_id FROM messages 
                            WHERE chat_id = ? 
                            ORDER BY timestamp ASC 
                            LIMIT ?
                        )
                    ''', (chat_id, chat_id, message_count - max_messages_per_chat))
                
                conn.commit()
                logger.info(f"Cleaned up old messages for {len(chats_to_clean)} chats")
                
        except Exception as e:
            logger.error(f"Error cleaning up old messages: {e}")