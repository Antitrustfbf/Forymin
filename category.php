<?php
require_once 'config.php';
initSession();

$pdo = getDBConnection();
$error = '';
$category = null;
$topics = [];

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: index.php');
    exit;
}

try {
    // Получение информации о категории
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    $category = $stmt->fetch();
    
    if (!$category) {
        $error = 'Категория не найдена';
    } else {
        // Получение тем в категории
        $stmt = $pdo->prepare("
            SELECT t.*, u.username, u.avatar,
                   (SELECT COUNT(p.id) FROM posts p WHERE p.topic_id = t.id) as reply_count,
                   (SELECT COUNT(p.id) FROM posts p WHERE p.topic_id = t.id) + 1 as total_posts
            FROM topics t 
            JOIN users u ON t.user_id = u.id 
            WHERE t.category_id = ? 
            ORDER BY t.is_pinned DESC, t.updated_at DESC
        ");
        $stmt->execute([$category['id']]);
        $topics = $stmt->fetchAll();
        
        // Обновление счетчика просмотров категории
        $stmt = $pdo->prepare("UPDATE categories SET views = COALESCE(views, 0) + 1 WHERE id = ?");
        $stmt->execute([$category['id']]);
    }
} catch (PDOException $e) {
    $error = 'Ошибка при загрузке категории';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $category ? escape($category['name']) : 'Категория'; ?> - <?php echo SITE_NAME; ?></title>
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
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i>Главная
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="categories.php">
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
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo escape($error); ?>
            </div>
        <?php elseif ($category): ?>
            <!-- Заголовок категории -->
            <div class="category-header mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
                                <li class="breadcrumb-item"><a href="categories.php">Категории</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><?php echo escape($category['name']); ?></li>
                            </ol>
                        </nav>
                        
                        <div class="d-flex align-items-center mb-3">
                            <div class="category-icon-large me-3" style="color: <?php echo $category['color']; ?>">
                                <i class="<?php echo $category['icon']; ?> fa-3x"></i>
                            </div>
                            <div>
                                <h1 class="mb-2"><?php echo escape($category['name']); ?></h1>
                                <p class="lead text-muted mb-0"><?php echo escape($category['description']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 text-md-end">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="new-topic.php?category=<?php echo $category['id']; ?>" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus me-2"></i>Новая тема
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Войти для создания темы
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Статистика категории -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon topics" style="background: <?php echo $category['color']; ?>">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3 class="stat-number"><?php echo count($topics); ?></h3>
                        <p class="stat-label">Тем</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon posts" style="background: <?php echo $category['color']; ?>">
                            <i class="fas fa-reply"></i>
                        </div>
                        <h3 class="stat-number">
                            <?php 
                            $total_posts = array_sum(array_column($topics, 'total_posts'));
                            echo number_format($total_posts);
                            ?>
                        </h3>
                        <p class="stat-label">Сообщений</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon users" style="background: <?php echo $category['color']; ?>">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="stat-number">
                            <?php 
                            $unique_users = count(array_unique(array_column($topics, 'user_id')));
                            echo number_format($unique_users);
                            ?>
                        </h3>
                        <p class="stat-label">Участников</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon categories" style="background: <?php echo $category['color']; ?>">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h3 class="stat-number"><?php echo number_format($category['views'] ?? 0); ?></h3>
                        <p class="stat-label">Просмотров</p>
                    </div>
                </div>
            </div>

            <!-- Темы в категории -->
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list me-2 text-primary"></i>Темы в категории
                    </h5>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" id="sortTopics" style="width: auto;">
                            <option value="recent">Сначала новые</option>
                            <option value="popular">По популярности</option>
                            <option value="active">По активности</option>
                        </select>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($topics)): ?>
                        <div class="p-5 text-center text-muted">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <h5>В этой категории пока нет тем</h5>
                            <p>Будьте первым, кто создаст тему!</p>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="new-topic.php?category=<?php echo $category['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Создать тему
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($topics as $topic): ?>
                            <div class="topic-item">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="topic-avatar">
                                                    <?php if ($topic['avatar'] && $topic['avatar'] !== 'default-avatar.png'): ?>
                                                        <img src="uploads/avatars/<?php echo escape($topic['avatar']); ?>" 
                                                             alt="Avatar" class="rounded-circle" width="40" height="40">
                                                    <?php else: ?>
                                                        <i class="fas fa-user"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <a href="topic.php?id=<?php echo $topic['id']; ?>" class="text-decoration-none">
                                                        <?php if ($topic['is_pinned']): ?>
                                                            <i class="fas fa-thumbtack text-warning me-1" title="Закреплено"></i>
                                                        <?php endif; ?>
                                                        <?php if ($topic['is_locked']): ?>
                                                            <i class="fas fa-lock text-danger me-1" title="Закрыто"></i>
                                                        <?php endif; ?>
                                                        <?php echo escape($topic['title']); ?>
                                                    </a>
                                                </h6>
                                                <div class="topic-meta">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        <a href="profile.php?user=<?php echo $topic['user_id']; ?>" class="text-decoration-none">
                                                            <?php echo escape($topic['username']); ?>
                                                        </a>
                                                        <span class="mx-2">•</span>
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo date('d.m.Y H:i', strtotime($topic['created_at'])); ?>
                                                        <span class="mx-2">•</span>
                                                        <i class="fas fa-eye me-1"></i>
                                                        <?php echo number_format($topic['views']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="topic-stats text-end">
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <div class="stat-number-small"><?php echo $topic['reply_count']; ?></div>
                                                    <div class="stat-label-small">Ответов</div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="stat-number-small"><?php echo $topic['total_posts']; ?></div>
                                                    <div class="stat-label-small">Сообщений</div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="stat-number-small"><?php echo $topic['views']; ?></div>
                                                    <div class="stat-label-small">Просмотров</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
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
    <script>
        // Сортировка тем
        document.getElementById('sortTopics').addEventListener('change', function() {
            const sortBy = this.value;
            // Здесь можно добавить AJAX запрос для сортировки
            console.log('Сортировка по:', sortBy);
        });
        
        // Анимация появления элементов
        document.addEventListener('DOMContentLoaded', function() {
            const topicItems = document.querySelectorAll('.topic-item');
            topicItems.forEach((item, index) => {
                setTimeout(() => {
                    item.classList.add('fade-in');
                }, index * 100);
            });
        });
    </script>
    
    <style>
        .category-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 2rem;
            border-radius: var(--border-radius);
            border: 1px solid #dee2e6;
        }
        
        .category-icon-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(0, 123, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .topic-item {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            transition: var(--transition);
        }
        
        .topic-item:last-child {
            border-bottom: none;
        }
        
        .topic-item:hover {
            background-color: #f8f9fa;
        }
        
        .topic-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            overflow: hidden;
        }
        
        .topic-stats .stat-number-small {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        .topic-stats .stat-label-small {
            font-size: 0.75rem;
            color: var(--secondary-color);
            text-transform: uppercase;
            font-weight: 500;
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1rem;
        }
        
        .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: var(--secondary-color);
        }
    </style>
</body>
</html>