#!/usr/bin/env python3
"""
Simple test script to verify bot setup and database connection
"""

import asyncio
import os
import sys
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

async def test_database():
    """Test database connection and operations"""
    try:
        from database import ChatStatisticsDB
        from config import DATABASE_PATH
        
        print("🔍 Testing database connection...")
        
        # Initialize database
        db = ChatStatisticsDB(DATABASE_PATH)
        print("✅ Database initialized successfully")
        
        # Test adding a chat
        await db.add_chat(
            chat_id=12345,
            chat_type="test",
            title="Test Chat"
        )
        print("✅ Test chat added successfully")
        
        # Test adding a message
        await db.add_message(
            message_id=1,
            chat_id=12345,
            user_id=67890,
            username="testuser",
            first_name="Test",
            last_name="User",
            message_type="text",
            message_text="Hello, world!"
        )
        print("✅ Test message added successfully")
        
        # Test getting statistics
        stats = await db.get_chat_statistics(12345)
        if stats:
            print("✅ Statistics retrieved successfully")
            print(f"   Chat: {stats['chat_info']['title']}")
            print(f"   Messages: {stats['chat_stats']['total_messages']}")
            print(f"   Users: {stats['chat_stats']['total_users']}")
        else:
            print("❌ Failed to retrieve statistics")
        
        # Clean up test data
        import sqlite3
        with sqlite3.connect(DATABASE_PATH) as conn:
            cursor = conn.cursor()
            cursor.execute("DELETE FROM messages WHERE chat_id = ?", (12345,))
            cursor.execute("DELETE FROM user_stats WHERE chat_id = ?", (12345,))
            cursor.execute("DELETE FROM chat_stats WHERE chat_id = ?", (12345,))
            cursor.execute("DELETE FROM chats WHERE chat_id = ?", (12345,))
            conn.commit()
        print("✅ Test data cleaned up")
        
        return True
        
    except Exception as e:
        print(f"❌ Database test failed: {e}")
        return False

async def test_config():
    """Test configuration loading"""
    try:
        from config import BOT_TOKEN, DATABASE_PATH
        
        print("🔍 Testing configuration...")
        
        if BOT_TOKEN and BOT_TOKEN != "your_bot_token_here":
            print("✅ Bot token loaded successfully")
        else:
            print("❌ Bot token not configured properly")
            print("   Please set BOT_TOKEN in your .env file")
            return False
        
        print(f"✅ Database path: {DATABASE_PATH}")
        return True
        
    except Exception as e:
        print(f"❌ Configuration test failed: {e}")
        return False

async def test_dependencies():
    """Test if all required packages are installed"""
    try:
        print("🔍 Testing dependencies...")
        
        import aiogram
        print(f"✅ aiogram {aiogram.__version__} installed")
        
        import aiohttp
        print(f"✅ aiohttp {aiohttp.__version__} installed")
        
        import dotenv
        print(f"✅ python-dotenv installed")
        
        return True
        
    except ImportError as e:
        print(f"❌ Missing dependency: {e}")
        print("   Please run: pip install -r requirements.txt")
        return False

async def main():
    """Run all tests"""
    print("🚀 Telegram Chat Statistics Bot - Setup Test\n")
    
    tests = [
        ("Dependencies", test_dependencies),
        ("Configuration", test_config),
        ("Database", test_database),
    ]
    
    results = []
    
    for test_name, test_func in tests:
        print(f"\n{'='*50}")
        print(f"Running {test_name} test...")
        print('='*50)
        
        try:
            result = await test_func()
            results.append((test_name, result))
        except Exception as e:
            print(f"❌ {test_name} test crashed: {e}")
            results.append((test_name, False))
    
    # Summary
    print(f"\n{'='*50}")
    print("TEST SUMMARY")
    print('='*50)
    
    passed = 0
    total = len(results)
    
    for test_name, result in results:
        status = "✅ PASS" if result else "❌ FAIL"
        print(f"{test_name}: {status}")
        if result:
            passed += 1
    
    print(f"\nResults: {passed}/{total} tests passed")
    
    if passed == total:
        print("\n🎉 All tests passed! Your bot is ready to run.")
        print("   Run 'python main.py' to start the bot.")
    else:
        print(f"\n⚠️  {total - passed} test(s) failed. Please fix the issues above.")
        return 1
    
    return 0

if __name__ == "__main__":
    try:
        exit_code = asyncio.run(main())
        sys.exit(exit_code)
    except KeyboardInterrupt:
        print("\n\n⏹️  Test interrupted by user")
        sys.exit(1)
    except Exception as e:
        print(f"\n❌ Unexpected error: {e}")
        sys.exit(1)