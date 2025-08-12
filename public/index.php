<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$query = read_get_string('q');

if ($query !== '') {
    $sql = "SELECT t.*, (SELECT COUNT(*) FROM posts p WHERE p.thread_id = t.id) AS reply_count
            FROM threads t
            WHERE t.title LIKE :q OR t.content LIKE :q
            ORDER BY t.updated_at DESC
            LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $like = '%' . $query . '%';
    $stmt->bindParam(':q', $like, PDO::PARAM_STR);
    $stmt->execute();
    $threads = $stmt->fetchAll();
} else {
    $sql = "SELECT t.*, (SELECT COUNT(*) FROM posts p WHERE p.thread_id = t.id) AS reply_count
            FROM threads t
            ORDER BY t.updated_at DESC
            LIMIT 50";
    $threads = $pdo->query($sql)->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Termux Форум</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <div class="brand">Termux Форум</div>
        <nav class="nav">
            <a class="btn primary" href="/new_thread.php">Новая тема</a>
        </nav>
    </div>
</header>
<main class="container">
    <section class="card">
        <form class="search" method="get" action="/index.php">
            <input type="text" name="q" placeholder="Поиск по темам" value="<?= escape_html($query) ?>" />
            <button class="btn" type="submit">Найти</button>
        </form>
    </section>

    <?php if (empty($threads)): ?>
        <section class="empty">Темы не найдены</section>
    <?php else: ?>
        <section class="grid">
            <?php foreach ($threads as $thread): ?>
                <article class="card thread">
                    <h2 class="thread-title">
                        <a href="/thread.php?id=<?= (int)$thread['id'] ?>"><?= escape_html($thread['title']) ?></a>
                    </h2>
                    <p class="thread-excerpt"><?= nl2br(escape_html(truncate_text($thread['content'], 220))) ?></p>
                    <div class="thread-meta">
                        <span>Автор: <?= escape_html($thread['author_name']) ?></span>
                        <span>Ответов: <?= (int)$thread['reply_count'] ?></span>
                        <span>Обновлено: <?= escape_html($thread['updated_at']) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
<footer class="site-footer">
    <div class="container">© <?= date('Y') ?> Termux Форум</div>
</footer>
<script src="/assets/script.js" defer></script>
</body>
</html>