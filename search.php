<?php
require_once 'config.php';
initSession();

$pdo = getDBConnection();
$error = '';
$results = [];
$total_results = 0;
$query = '';
$category_filter = '';
$search_type = 'all';

// Получение параметров поиска
$query = trim($_GET['q'] ?? '');
$category_filter = $_GET['category'] ?? '';
$search_type = $_GET['type'] ?? 'all';

// Получение категорий для фильтра
$stmt = $pdo->query("SELECT id, name, slug FROM categories ORDER BY sort_order, name");
$categories = $stmt->fetchAll();

// Выполнение поиска
if (!empty($query) && strlen($query) >= 3) {
    try {
        $where_conditions = [];
        $params = [];
        
        // Базовые условия поиска
        if ($search_type === 'topics' || $search_type === 'all') {
            $where_conditions[] = "t.title LIKE ? OR t.content LIKE ?";
            $params[] = "%$query%";
            $params[] = "%$query%";
        }
        
        if ($search_type === 'posts' || $search_type === 'all') {
            if (!empty($where_conditions)) {
                $where_conditions[] = "OR p.content LIKE ?";
            } else {
                $where_conditions[] = "p.content LIKE ?";
            }
            $params[] = "%$query%";
        }
        
        if ($search_type === 'users' || $search_type === 'all') {
            if (!empty($where_conditions)) {
                $where_conditions[] = "OR u.username LIKE ? OR u.bio LIKE ?";
            } else {
                $where_conditions[] = "u.username LIKE ? OR u.bio LIKE ?";
            }
            $params[] = "%$query%";
            $params[] = "%$query%";
        }
        
        // Фильтр по категории
        if (!empty($category_filter)) {
            $where_conditions[] = "c.slug = ?";
            $params[] = $category_filter;
        }
        
        $where_clause = implode(' OR ', $where_conditions);
        
        // SQL запрос для поиска
        $sql = "
            SELECT DISTINCT
                'topic' as type,
                t.id,
                t.title,
                t.content,
                t.created_at,
                t.views,
                t.is_pinned,
                t.is_locked,
                u.username,
                u.avatar,
                c.name as category_name,
                c.slug as category_slug,
                c.color as category_color,
                (SELECT COUNT(p2.id) FROM posts p2 WHERE p2.topic_id = t.id) as reply_count
            FROM topics t
            JOIN users u ON t.user_id = u.id
            JOIN categories c ON t.category_id = c.id
            WHERE ($where_clause)
            
            UNION ALL
            
            SELECT DISTINCT
                'post' as type,
                p.id,
                t.title,
                p.content,
                p.created_at,
                t.views,
                t.is_pinned,
                t.is_locked,
                u.username,
                u.avatar,
                c.name as category_name,
                c.slug as category_slug,
                c.color as category_color,
                (SELECT COUNT(p2.id) FROM posts p2 WHERE p2.topic_id = t.id) as reply_count
            FROM posts p
            JOIN topics t ON p.topic_id = t.id
            JOIN users u ON p.user_id = u.id
            JOIN categories c ON t.category_id = c.id
            WHERE ($where_clause)
            
            ORDER BY created_at DESC
            LIMIT 50
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
        $total_results = count($results);
        
    } catch (PDOException $e) {
        $error = 'Ошибка при выполнении поиска';
    }
} elseif (!empty($query) && strlen($query) < 3) {
    $error = 'Поисковый запрос должен содержать минимум 3 символа';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поиск - <?php echo SITE_NAME; ?></title>
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
                        <a class="nav-link" href="categories.php">
                            <i class="fas fa-list me-1"></i>Категории
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="search.php">
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
        <!-- Заголовок поиска -->
        <div class="search-header mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-3">
                        <i class="fas fa-search me-2 text-primary"></i>Поиск по форуму
                    </h1>
                    <p class="lead text-muted">Найдите интересующие вас темы, сообщения и пользователей</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="search-stats">
                        <?php if ($total_results > 0): ?>
                            <div class="badge bg-success fs-6">
                                <i class="fas fa-check-circle me-1"></i>
                                Найдено: <?php echo number_format($total_results); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Форма поиска -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="search.php" id="searchForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="q" class="form-label">
                                <i class="fas fa-search me-2"></i>Поисковый запрос
                            </label>
                            <input type="text" class="form-control form-control-lg" id="q" name="q" 
                                   value="<?php echo escape($query); ?>" 
                                   placeholder="Введите поисковый запрос..." required>
                            <div class="form-text">Минимум 3 символа</div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="type" class="form-label">
                                <i class="fas fa-filter me-2"></i>Тип поиска
                            </label>
                            <select class="form-select" id="type" name="type">
                                <option value="all" <?php echo $search_type === 'all' ? 'selected' : ''; ?>>Везде</option>
                                <option value="topics" <?php echo $search_type === 'topics' ? 'selected' : ''; ?>>Только темы</option>
                                <option value="posts" <?php echo $search_type === 'posts' ? 'selected' : ''; ?>>Только сообщения</option>
                                <option value="users" <?php echo $search_type === 'users' ? 'selected' : ''; ?>>Только пользователи</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="category" class="form-label">
                                <i class="fas fa-folder me-2"></i>Категория
                            </label>
                            <select class="form-select" id="category" name="category">
                                <option value="">Все категории</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['slug']; ?>" 
                                            <?php echo $category_filter === $category['slug'] ? 'selected' : ''; ?>>
                                        <?php echo escape($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-search me-2"></i>Найти
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-lg" onclick="clearSearch()">
                            <i class="fas fa-times me-2"></i>Очистить
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Результаты поиска -->
        <?php if ($error): ?>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo escape($error); ?>
            </div>
        <?php elseif (!empty($query) && $total_results === 0): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4>По вашему запросу ничего не найдено</h4>
                <p class="text-muted">Попробуйте изменить поисковый запрос или параметры поиска</p>
                <div class="mt-3">
                    <button class="btn btn-outline-primary" onclick="showSearchTips()">
                        <i class="fas fa-lightbulb me-2"></i>Советы по поиску
                    </button>
                </div>
            </div>
        <?php elseif ($total_results > 0): ?>
            <div class="search-results">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Результаты поиска
                    </h5>
                    <div class="search-options">
                        <select class="form-select form-select-sm" id="sortResults" style="width: auto;">
                            <option value="relevance">По релевантности</option>
                            <option value="date">По дате</option>
                            <option value="popularity">По популярности</option>
                        </select>
                    </div>
                </div>
                
                <?php foreach ($results as $result): ?>
                    <div class="search-result-item">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="result-content">
                                    <div class="result-type mb-2">
                                        <?php if ($result['type'] === 'topic'): ?>
                                            <span class="badge bg-primary">
                                                <i class="fas fa-comments me-1"></i>Тема
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-reply me-1"></i>Сообщение
                                            </span>
                                        <?php endif; ?>
                                        
                                        <span class="badge" style="background-color: <?php echo $result['category_color']; ?>;">
                                            <i class="fas fa-folder me-1"></i><?php echo escape($result['category_name']); ?>
                                        </span>
                                        
                                        <?php if ($result['is_pinned']): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-thumbtack me-1"></i>Закреплено
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($result['is_locked']): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-lock me-1"></i>Закрыто
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <h6 class="result-title mb-2">
                                        <a href="topic.php?id=<?php echo $result['id']; ?>" class="text-decoration-none">
                                            <?php echo escape($result['title']); ?>
                                        </a>
                                    </h6>
                                    
                                    <div class="result-excerpt mb-2">
                                        <?php 
                                        $excerpt = strip_tags($result['content']);
                                        $excerpt = substr($excerpt, 0, 200);
                                        if (strlen($result['content']) > 200) {
                                            $excerpt .= '...';
                                        }
                                        echo escape($excerpt);
                                        ?>
                                    </div>
                                    
                                    <div class="result-meta">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <a href="profile.php?user=<?php echo $result['user_id'] ?? ''; ?>" class="text-decoration-none">
                                                <?php echo escape($result['username']); ?>
                                            </a>
                                            <span class="mx-2">•</span>
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('d.m.Y H:i', strtotime($result['created_at'])); ?>
                                            <?php if ($result['type'] === 'topic'): ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-eye me-1"></i>
                                                <?php echo number_format($result['views']); ?> просмотров
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="result-stats text-end">
                                    <?php if ($result['type'] === 'topic'): ?>
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo $result['reply_count']; ?></div>
                                            <div class="stat-label">Ответов</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="result-actions mt-2">
                                        <a href="topic.php?id=<?php echo $result['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye me-1"></i>Просмотреть
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Советы по поиску -->
        <div class="card mt-4" id="searchTips" style="display: none;">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="fas fa-lightbulb me-2 text-warning"></i>Советы по эффективному поиску
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Поисковые операторы:</h6>
                        <ul class="list-unstyled">
                            <li><code>"точная фраза"</code> - поиск по точной фразе</li>
                            <li><code>termux +android</code> - поиск с обязательным словом</li>
                            <li><code>linux -ubuntu</code> - поиск без определенного слова</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Полезные советы:</h6>
                        <ul class="list-unstyled">
                            <li>Используйте ключевые слова</li>
                            <li>Попробуйте разные варианты написания</li>
                            <li>Используйте фильтры по категориям</li>
                            <li>Ограничивайте тип поиска</li>
                        </ul>
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
    <script>
        // Очистка поиска
        function clearSearch() {
            document.getElementById('q').value = '';
            document.getElementById('type').value = 'all';
            document.getElementById('category').value = '';
            document.getElementById('searchForm').submit();
        }
        
        // Показать советы по поиску
        function showSearchTips() {
            const tips = document.getElementById('searchTips');
            tips.style.display = tips.style.display === 'none' ? 'block' : 'none';
        }
        
        // Сортировка результатов
        document.getElementById('sortResults').addEventListener('change', function() {
            const sortBy = this.value;
            // Здесь можно добавить AJAX запрос для сортировки
            console.log('Сортировка по:', sortBy);
        });
        
        // Автопоиск при изменении параметров
        document.getElementById('type').addEventListener('change', function() {
            if (document.getElementById('q').value.trim().length >= 3) {
                document.getElementById('searchForm').submit();
            }
        });
        
        document.getElementById('category').addEventListener('change', function() {
            if (document.getElementById('q').value.trim().length >= 3) {
                document.getElementById('searchForm').submit();
            }
        });
        
        // Подсветка поисковых терминов
        function highlightSearchTerms() {
            const query = '<?php echo addslashes($query); ?>';
            if (query.length >= 3) {
                const terms = query.split(' ').filter(term => term.length >= 3);
                terms.forEach(term => {
                    const regex = new RegExp(`(${term})`, 'gi');
                    document.querySelectorAll('.result-title, .result-excerpt').forEach(element => {
                        element.innerHTML = element.innerHTML.replace(regex, '<mark>$1</mark>');
                    });
                });
            }
        }
        
        // Запуск подсветки после загрузки страницы
        document.addEventListener('DOMContentLoaded', highlightSearchTerms);
    </script>
    
    <style>
        .search-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 2rem;
            border-radius: var(--border-radius);
            border: 1px solid #dee2e6;
        }
        
        .search-result-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }
        
        .search-result-item:hover {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-color: var(--primary-color);
        }
        
        .result-type .badge {
            margin-right: 0.5rem;
            font-size: 0.75rem;
        }
        
        .result-title a {
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .result-title a:hover {
            color: var(--primary-color);
        }
        
        .result-excerpt {
            color: var(--secondary-color);
            line-height: 1.5;
        }
        
        .result-meta a {
            color: var(--primary-color);
        }
        
        .result-stats .stat-item {
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .result-stats .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .result-stats .stat-label {
            font-size: 0.75rem;
            color: var(--secondary-color);
            text-transform: uppercase;
        }
        
        .search-options {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        mark {
            background-color: #fff3cd;
            color: #856404;
            padding: 0.1rem 0.2rem;
            border-radius: 0.2rem;
        }
        
        @media (max-width: 768px) {
            .search-header {
                padding: 1rem;
                text-align: center;
            }
            
            .search-options {
                margin-top: 1rem;
                justify-content: center;
            }
            
            .result-stats {
                text-align: center;
                margin-top: 1rem;
            }
        }
    </style>
</body>
</html>