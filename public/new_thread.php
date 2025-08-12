<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$errors = [];
$title = '';
$author = '';
$content = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Недействительный CSRF токен, обновите страницу.';
    }

    $title = read_post_string('title');
    $author = read_post_string('author_name');
    $content = read_post_string('content');

    if ($title === '' || mb_strlen($title) < 3) {
        $errors[] = 'Заголовок должен содержать не менее 3 символов.';
    } elseif (mb_strlen($title) > 200) {
        $errors[] = 'Заголовок слишком длинный (максимум 200 символов).';
    }

    if ($author === '' || mb_strlen($author) < 2) {
        $errors[] = 'Имя автора должно содержать не менее 2 символов.';
    } elseif (mb_strlen($author) > 60) {
        $errors[] = 'Имя автора слишком длинное (максимум 60 символов).';
    }

    if ($content === '' || mb_strlen($content) < 5) {
        $errors[] = 'Текст сообщения должен содержать не менее 5 символов.';
    } elseif (mb_strlen($content) > 10000) {
        $errors[] = 'Текст сообщения слишком длинный (максимум 10000 символов).';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO threads (title, author_name, content, created_at, updated_at) VALUES (:title, :author_name, :content, NOW(), NOW())');
        $stmt->execute([
            ':title' => $title,
            ':author_name' => $author,
            ':content' => $content,
        ]);
        $threadId = (int)$pdo->lastInsertId();
        redirect_to('/thread.php?id=' . $threadId);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Новая тема — Termux Форум</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <div class="brand"><a href="/">Termux Форум</a></div>
    </div>
</header>
<main class="container">
    <section class="card">
        <h1>Новая тема</h1>
        <?php if ($errors): ?>
            <div class="alert error">
                <?php foreach ($errors as $error): ?>
                    <div><?= escape_html($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" class="form" action="/new_thread.php">
            <input type="hidden" name="csrf_token" value="<?= escape_html(get_csrf_token()) ?>" />
            <label>
                <span>Заголовок</span>
                <input type="text" name="title" value="<?= escape_html($title) ?>" required maxlength="200" />
            </label>
            <label>
                <span>Имя</span>
                <input type="text" name="author_name" value="<?= escape_html($author) ?>" required maxlength="60" />
            </label>
            <label>
                <span>Сообщение</span>
                <textarea name="content" rows="8" required maxlength="10000" class="auto-resize"><?= escape_html($content) ?></textarea>
            </label>
            <div class="form-actions">
                <a class="btn" href="/">Отмена</a>
                <button class="btn primary" type="submit">Создать</button>
            </div>
        </form>
    </section>
</main>
<footer class="site-footer">
    <div class="container">© <?= date('Y') ?> Termux Форум</div>
</footer>
<script src="/assets/script.js" defer></script>
</body>
</html>