<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Некорректный идентификатор темы';
    exit;
}

// Load thread
$stmt = $pdo->prepare('SELECT * FROM threads WHERE id = :id');
$stmt->execute([':id' => $id]);
$thread = $stmt->fetch();

if (!$thread) {
    http_response_code(404);
    echo 'Тема не найдена';
    exit;
}

$errors = [];
$replyAuthor = '';
$replyContent = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Недействительный CSRF токен, обновите страницу.';
    }

    $replyAuthor = read_post_string('author_name');
    $replyContent = read_post_string('content');

    if ($replyAuthor === '' || mb_strlen($replyAuthor) < 2) {
        $errors[] = 'Имя автора должно содержать не менее 2 символов.';
    } elseif (mb_strlen($replyAuthor) > 60) {
        $errors[] = 'Имя автора слишком длинное (максимум 60 символов).';
    }

    if ($replyContent === '' || mb_strlen($replyContent) < 2) {
        $errors[] = 'Текст ответа должен содержать не менее 2 символов.';
    } elseif (mb_strlen($replyContent) > 10000) {
        $errors[] = 'Текст ответа слишком длинный (максимум 10000 символов).';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO posts (thread_id, author_name, content, created_at) VALUES (:thread_id, :author_name, :content, NOW())');
            $stmt->execute([
                ':thread_id' => $id,
                ':author_name' => $replyAuthor,
                ':content' => $replyContent,
            ]);

            $pdo->prepare('UPDATE threads SET updated_at = NOW() WHERE id = :id')->execute([':id' => $id]);
            $pdo->commit();
            redirect_to('/thread.php?id=' . $id);
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Не удалось сохранить ответ: ' . $e->getMessage();
        }
    }
}

// Load posts
$stmt = $pdo->prepare('SELECT * FROM posts WHERE thread_id = :id ORDER BY created_at ASC');
$stmt->execute([':id' => $id]);
$posts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= escape_html($thread['title']) ?> — Termux Форум</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <div class="brand"><a href="/">Termux Форум</a></div>
        <nav class="nav">
            <a class="btn" href="/">Назад</a>
        </nav>
    </div>
</header>
<main class="container">
    <article class="card">
        <h1><?= escape_html($thread['title']) ?></h1>
        <div class="thread-meta">
            <span>Автор: <?= escape_html($thread['author_name']) ?></span>
            <span>Создано: <?= escape_html($thread['created_at']) ?></span>
            <span>Обновлено: <?= escape_html($thread['updated_at']) ?></span>
        </div>
        <div class="post-content">
            <?= nl2br(escape_html($thread['content'])) ?>
        </div>
    </article>

    <section class="stack">
        <?php if ($posts): ?>
            <?php foreach ($posts as $post): ?>
                <article class="card reply">
                    <div class="reply-meta">
                        <span><?= escape_html($post['author_name']) ?></span>
                        <span><?= escape_html($post['created_at']) ?></span>
                    </div>
                    <div class="post-content">
                        <?= nl2br(escape_html($post['content'])) ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty">Ответов пока нет. Будьте первым!</div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Оставить ответ</h2>
        <?php if ($errors): ?>
            <div class="alert error">
                <?php foreach ($errors as $error): ?>
                    <div><?= escape_html($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" class="form" action="/thread.php?id=<?= (int)$id ?>">
            <input type="hidden" name="csrf_token" value="<?= escape_html(get_csrf_token()) ?>" />
            <label>
                <span>Имя</span>
                <input type="text" name="author_name" value="<?= escape_html($replyAuthor) ?>" required maxlength="60" />
            </label>
            <label>
                <span>Сообщение</span>
                <textarea name="content" rows="6" required maxlength="10000" class="auto-resize"><?= escape_html($replyContent) ?></textarea>
            </label>
            <div class="form-actions">
                <button class="btn primary" type="submit">Отправить</button>
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