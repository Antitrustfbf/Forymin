<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $config['db_host'],
    $config['db_port'],
    $config['db_name']
);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
} catch (PDOException $exception) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Не удалось подключиться к базе данных. Проверьте файл config.php и доступ к MySQL.\n";
    echo "Ошибка: " . $exception->getMessage();
    exit(1);
}