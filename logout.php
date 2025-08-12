<?php
require_once 'config.php';
initSession();

// Уничтожаем сессию
session_unset();
session_destroy();

// Удаляем куки "запомнить меня"
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Перенаправляем на главную страницу
header('Location: index.php');
exit;
?>