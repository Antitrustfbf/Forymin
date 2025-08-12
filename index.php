<?php
require_once 'config.php';
initSession();

$pdo = getDBConnection();

// Получение категорий с количеством тем
$stmt = $pdo->query("
    SELECT c.*, COUNT(t.id) as topic_count, 
           (SELECT COUNT(p.id) FROM posts p 
            JOIN topics t2 ON p.topic_id = t2.id 
            WHERE t2.category_id = c.id) as post_count
    FROM categories c 
    LEFT JOIN topics t ON c.id = t.category_id 
    GROUP BY c.id 
    ORDER BY c.sort_order, c.name
");
$categories = $stmt->fetchAll();

// Получение последних тем
$stmt = $pdo->query("
    SELECT t.*, u.username, c.name as category_name, c.slug as category_slug,
           (SELECT COUNT(p.id) FROM posts p WHERE p.topic_id = t.id) as reply_count
    FROM topics t 
    JOIN users u ON t.user_id = u.id 
    JOIN categories c ON t.category_id = c.id 
    ORDER BY t.is_pinned DESC, t.updated_at DESC 
    LIMIT 10
");
$recent_topics = $stmt->fetchAll();

// Получение статистики
$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
$total_users = $stmt->fetch()['total_users'];

$stmt = $pdo->query("SELECT COUNT(*) as total_topics FROM topics");
$total_topics = $stmt->fetch()['total_topics'];

$stmt = $pdo->query("SELECT COUNT(*) as total_posts FROM posts");
$total_posts = $stmt->fetch()['total_posts'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Главная</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Навигация -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-terminal me-2"></i><?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home me-1"></i>Главная
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">
                            <i class="fas fa-list me-1"></i>Категории
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="search.php">
                            <i class="fas fa-search me-1"></i>Поиск
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo escape($_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i>Профиль</a></li>
                                <li><a class="dropdown-item" href="new-topic.php"><i class="fas fa-plus me-2"></i>Новая тема</a></li>
                                <?php if ($_SESSION['is_admin']): ?>
                                    <li><a class="dropdown-item" href="admin/"><i class="fas fa-cog me-2"></i>Админ панель</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Выйти</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Войти
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">
                                <i class="fas fa-user-plus me-1"></i>Регистрация
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Основной контент -->
    <div class="container mt-4">
        <!-- Приветствие -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="welcome-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="display-5 fw-bold text-primary mb-3">
                                Добро пожаловать в <?php echo SITE_NAME; ?>!
                            </h1>
                            <p class="lead mb-3">
                                Сообщество энтузиастов Termux. Задавайте вопросы, делитесь опытом и находите решения для ваших задач.
                            </p>
                            <?php if (!isset($_SESSION['user_id'])): ?>
                                <a href="register.php" class="btn btn-primary btn-lg me-3">
                                    <i class="fas fa-user-plus me-2"></i>Присоединиться
                                </a>
                                <a href="login.php" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Войти
                                </a>
                            <?php else: ?>
                                <a href="new-topic.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-plus me-2"></i>Создать тему
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="terminal-icon">
                                <i class="fas fa-terminal"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Статистика -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card text-center">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="stat-number"><?php echo number_format($total_users); ?></h3>
                    <p class="stat-label">Пользователей</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card text-center">
                    <div class="stat-icon topics">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3 class="stat-number"><?php echo number_format($total_topics); ?></h3>
                    <p class="stat-label">Тем</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card text-center">
                    <div class="stat-icon posts">
                        <i class="fas fa-reply"></i>
                    </div>
                    <h3 class="stat-number"><?php echo number_format($total_posts); ?></h3>
                    <p class="stat-label">Сообщений</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card text-center">
                    <div class="stat-icon categories">
                        <i class="fas fa-folder"></i>
                    </div>
                    <h3 class="stat-number"><?php echo count($categories); ?></h3>
                    <p class="stat-label">Категорий</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Категории -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-folder me-2 text-primary"></i>Категории
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-item">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-center">
                                            <div class="category-icon me-3" style="color: <?php echo $category['color']; ?>">
                                                <i class="<?php echo $category['icon']; ?> fa-2x"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">
                                                    <a href="category.php?slug=<?php echo $category['slug']; ?>" class="text-decoration-none">
                                                        <?php echo escape($category['name']); ?>
                                                    </a>
                                                </h6>
                                                <p class="text-muted small mb-0"><?php echo escape($category['description']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="category-stats">
                                            <span class="badge bg-light text-dark me-2">
                                                <i class="fas fa-comments me-1"></i><?php echo $category['topic_count']; ?>
                                            </span>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-reply me-1"></i><?php echo $category['post_count']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Последние темы -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-clock me-2 text-primary"></i>Последние темы
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_topics)): ?>
                            <div class="p-3 text-center text-muted">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p>Пока нет тем</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_topics as $topic): ?>
                                <div class="recent-topic-item">
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0 me-3">
                                            <div class="topic-avatar">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <a href="topic.php?id=<?php echo $topic['id']; ?>" class="text-decoration-none">
                                                    <?php if ($topic['is_pinned']): ?>
                                                        <i class="fas fa-thumbtack text-warning me-1"></i>
                                                    <?php endif; ?>
                                                    <?php echo escape($topic['title']); ?>
                                                </a>
                                            </h6>
                                            <div class="topic-meta">
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i><?php echo escape($topic['username']); ?>
                                                    <span class="mx-2">•</span>
                                                    <i class="fas fa-folder me-1"></i>
                                                    <a href="category.php?slug=<?php echo $topic['category_slug']; ?>" class="text-decoration-none">
                                                        <?php echo escape($topic['category_name']); ?>
                                                    </a>
                                                    <span class="mx-2">•</span>
                                                    <i class="fas fa-reply me-1"></i><?php echo $topic['reply_count']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Подвал -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-terminal me-2"></i><?php echo SITE_NAME; ?></h5>
                    <p class="text-muted">Сообщество энтузиастов Termux</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">
                        &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Все права защищены.
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>