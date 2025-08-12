<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

$serverDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['db_host'], $config['db_port']);
$dbName = $config['db_name'];

try {
    $serverPdo = new PDO($serverDsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $serverPdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', str_replace('`', '``', $dbName)));
    echo "База данных проверена/создана: {$dbName}\n";

    $dbDsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_port'], $dbName);
    $pdo = new PDO($dbDsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec('CREATE TABLE IF NOT EXISTS threads (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        author_name VARCHAR(60) NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        INDEX (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    echo "Таблица threads готова.\n";

    $pdo->exec('CREATE TABLE IF NOT EXISTS posts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        thread_id INT UNSIGNED NOT NULL,
        author_name VARCHAR(60) NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        CONSTRAINT fk_posts_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
        INDEX (thread_id),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    echo "Таблица posts готова.\n";

    echo "Готово!\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Ошибка инициализации БД: ' . $e->getMessage() . "\n");
    exit(1);
}