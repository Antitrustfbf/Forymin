<?php
require_once 'config.php';
initSession();

$pdo = getDBConnection();
$error = '';
$categories = [];

try {
    // Получение всех категорий с статистикой
    $stmt = $pdo->query("
        SELECT c.*, 
               COUNT(DISTINCT t.id) as topic_count,
               COUNT(DISTINCT p.id) as post_count,
               COUNT(DISTINCT t.user_id) as unique_users,
               MAX(t.updated_at) as last_activity
        FROM categories c 
        LEFT JOIN topics t ON c.id = t.category_id 
        LEFT JOIN posts p ON t.id = p.topic_id 
        GROUP BY c.id 
        ORDER BY c.sort_order, c.name
    ");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Ошибка при загрузке категорий';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Категории - <?php echo SITE_NAME; ?></title>
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
        <!-- Заголовок -->
        <div class="categories-header mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-3">
                        <i class="fas fa-list me-2 text-primary"></i>Категории форума
                    </h1>
                    <p class="lead text-muted">Выберите интересующую вас категорию для просмотра тем</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="new-topic.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-plus me-2"></i>Создать тему
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>Войти для создания темы
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo escape($error); ?>
            </div>
        <?php else: ?>
            <!-- Список категорий -->
            <div class="categories-grid">
                <?php foreach ($categories as $category): ?>
                    <div class="category-card">
                        <div class="category-header">
                            <div class="category-icon" style="color: <?php echo $category['color']; ?>">
                                <i class="<?php echo $category['icon']; ?> fa-3x"></i>
                            </div>
                            <div class="category-info">
                                <h3 class="category-title">
                                    <a href="category.php?slug=<?php echo $category['slug']; ?>" class="text-decoration-none">
                                        <?php echo escape($category['name']); ?>
                                    </a>
                                </h3>
                                <p class="category-description"><?php echo escape($category['description']); ?></p>
                            </div>
                        </div>
                        
                        <div class="category-stats">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo number_format($category['topic_count']); ?></div>
                                        <div class="stat-label">Тем</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo number_format($category['post_count']); ?></div>
                                        <div class="stat-label">Сообщений</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo number_format($category['unique_users']); ?></div>
                                        <div class="stat-label">Участников</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($category['last_activity']): ?>
                            <div class="category-activity">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Последняя активность: <?php echo date('d.m.Y H:i', strtotime($category['last_activity'])); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="category-actions">
                            <a href="category.php?slug=<?php echo $category['slug']; ?>" class="btn btn-primary">
                                <i class="fas fa-eye me-2"></i>Просмотреть
                            </a>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="new-topic.php?category=<?php echo $category['id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-plus me-2"></i>Новая тема
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Статистика форума -->
            <div class="forum-stats mt-5">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2 text-primary"></i>Общая статистика форума
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="stat-summary text-center">
                                    <div class="stat-icon-large topics">
                                        <i class="fas fa-comments"></i>
                                    </div>
                                    <h4 class="stat-number-large">
                                        <?php echo number_format(array_sum(array_column($categories, 'topic_count'))); ?>
                                    </h4>
                                    <p class="stat-label-large">Всего тем</p>
                                </div>
                            </div>
                            
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="stat-summary text-center">
                                    <div class="stat-icon-large posts">
                                        <i class="fas fa-reply"></i>
                                    </div>
                                    <h4 class="stat-number-large">
                                        <?php echo number_format(array_sum(array_column($categories, 'post_count'))); ?>
                                    </h4>
                                    <p class="stat-label-large">Всего сообщений</p>
                                </div>
                            </div>
                            
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="stat-summary text-center">
                                    <div class="stat-icon-large users">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <h4 class="stat-number-large">
                                        <?php 
                                        $total_users = array_sum(array_column($categories, 'unique_users'));
                                        echo number_format($total_users);
                                        ?>
                                    </h4>
                                    <p class="stat-label-large">Активных участников</p>
                                </div>
                            </div>
                            
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="stat-summary text-center">
                                    <div class="stat-icon-large categories">
                                        <i class="fas fa-folder"></i>
                                    </div>
                                    <h4 class="stat-number-large"><?php echo count($categories); ?></h4>
                                    <p class="stat-label-large">Категорий</p>
                                </div>
                            </div>
                        </div>
                    </div>
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
        // Анимация появления элементов
        document.addEventListener('DOMContentLoaded', function() {
            const categoryCards = document.querySelectorAll('.category-card');
            categoryCards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('fade-in');
                }, index * 100);
            });
        });
        
        // Подсветка активной категории при наведении
        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 0.5rem 1rem rgba(0, 0, 0, 0.15)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 0.125rem 0.25rem rgba(0, 0, 0, 0.075)';
            });
        });
    </script>
    
    <style>
        .categories-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 2rem;
            border-radius: var(--border-radius);
            border: 1px solid #dee2e6;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .category-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            transition: var(--transition);
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .category-card:hover {
            border-color: var(--primary-color);
        }
        
        .category-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .category-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(0, 123, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .category-title {
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
        }
        
        .category-title a {
            color: var(--dark-color);
        }
        
        .category-title a:hover {
            color: var(--primary-color);
        }
        
        .category-description {
            color: var(--secondary-color);
            margin-bottom: 0;
            line-height: 1.4;
        }
        
        .category-stats {
            margin-bottom: 1.5rem;
            padding: 1rem 0;
            border-top: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
        }
        
        .category-stats .stat-item {
            text-align: center;
        }
        
        .category-stats .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }
        
        .category-stats .stat-label {
            font-size: 0.75rem;
            color: var(--secondary-color);
            text-transform: uppercase;
            font-weight: 500;
        }
        
        .category-activity {
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .category-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        
        .stat-summary {
            padding: 1rem;
        }
        
        .stat-icon-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
        }
        
        .stat-number-large {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label-large {
            color: var(--secondary-color);
            font-weight: 500;
            margin: 0;
            text-transform: uppercase;
            font-size: 0.875rem;
        }
        
        @media (max-width: 768px) {
            .categories-header {
                padding: 1rem;
                text-align: center;
            }
            
            .categories-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .category-card {
                padding: 1rem;
            }
            
            .category-header {
                flex-direction: column;
                text-align: center;
            }
            
            .category-icon {
                margin-right: 0;
                margin-bottom: 1rem;
            }
            
            .category-actions {
                flex-direction: column;
            }
            
            .stat-summary {
                padding: 0.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .categories-header h1 {
                font-size: 1.8rem;
            }
            
            .categories-header .lead {
                font-size: 1rem;
            }
            
            .btn-lg {
                padding: 0.5rem 1rem;
                font-size: 1rem;
            }
        }
    </style>
</body>
</html>