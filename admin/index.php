<?php
require_once '../config.php';
initSession();

// Проверяем права администратора
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: ../index.php');
    exit;
}

$pdo = getDBConnection();
$stats = [];

try {
    // Получение статистики
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
    $stats['users'] = $stmt->fetch()['total_users'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_topics FROM topics");
    $stats['topics'] = $stmt->fetch()['total_topics'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_posts FROM posts");
    $stats['posts'] = $stmt->fetch()['total_posts'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_categories FROM categories");
    $stats['categories'] = $stmt->fetch()['total_categories'];
    
    // Последние зарегистрированные пользователи
    $stmt = $pdo->query("SELECT username, email, join_date FROM users ORDER BY join_date DESC LIMIT 5");
    $recent_users = $stmt->fetchAll();
    
    // Последние темы
    $stmt = $pdo->query("SELECT t.title, u.username, t.created_at FROM topics t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 5");
    $recent_topics = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Ошибка при загрузке статистики';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ панель - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Навигация -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-terminal me-2"></i><?php echo SITE_NAME; ?> - Админ панель
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">
                            <i class="fas fa-home me-1"></i>Главная
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-cog me-1"></i>Панель управления
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo escape($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user-circle me-2"></i>Профиль</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Выйти</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Основной контент -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <!-- Боковое меню -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-bars me-2"></i>Меню управления
                        </h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="index.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-tachometer-alt me-2"></i>Панель управления
                        </a>
                        <a href="users.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-users me-2"></i>Пользователи
                        </a>
                        <a href="topics.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-comments me-2"></i>Темы
                        </a>
                        <a href="categories.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-folder me-2"></i>Категории
                        </a>
                        <a href="settings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-cog me-2"></i>Настройки
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <!-- Статистика -->
                <div class="row mb-4">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon users">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="stat-number"><?php echo number_format($stats['users']); ?></h3>
                            <p class="stat-label">Пользователей</p>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon topics">
                                <i class="fas fa-comments"></i>
                            </div>
                            <h3 class="stat-number"><?php echo number_format($stats['topics']); ?></h3>
                            <p class="stat-label">Тем</p>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon posts">
                                <i class="fas fa-reply"></i>
                            </div>
                            <h3 class="stat-number"><?php echo number_format($stats['posts']); ?></h3>
                            <p class="stat-label">Сообщений</p>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="stat-card text-center">
                            <div class="stat-icon categories">
                                <i class="fas fa-folder"></i>
                            </div>
                            <h3 class="stat-number"><?php echo number_format($stats['categories']); ?></h3>
                            <p class="stat-label">Категорий</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Последние пользователи -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-users me-2"></i>Последние пользователи
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recent_users)): ?>
                                    <div class="p-3 text-center text-muted">
                                        <p>Нет зарегистрированных пользователей</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_users as $user): ?>
                                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                                            <div>
                                                <strong><?php echo escape($user['username']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo escape($user['email']); ?></small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('d.m.Y', strtotime($user['join_date'])); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Последние темы -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-comments me-2"></i>Последние темы
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recent_topics)): ?>
                                    <div class="p-3 text-center text-muted">
                                        <p>Нет созданных тем</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_topics as $topic): ?>
                                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                                            <div>
                                                <strong><?php echo escape($topic['title']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo escape($topic['username']); ?></small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('d.m.Y', strtotime($topic['created_at'])); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Быстрые действия -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>Быстрые действия
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="users.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-users me-2"></i>Управление пользователями
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="topics.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-comments me-2"></i>Управление темами
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="categories.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-folder me-2"></i>Управление категориями
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="settings.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-cog me-2"></i>Настройки форума
                                </a>
                            </div>
                        </div>
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
                    <h5><i class="fas fa-terminal me-2"></i><?php echo SITE_NAME; ?> - Админ панель</h5>
                    <p class="text-muted">Панель управления форумом</p>
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
    <script src="../assets/js/app.js"></script>
</body>
</html>