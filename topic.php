<?php
require_once 'config.php';
initSession();

$pdo = getDBConnection();
$error = '';
$topic = null;
$posts = [];
$category = null;

$topic_id = (int)($_GET['id'] ?? 0);

if ($topic_id === 0) {
    header('Location: index.php');
    exit;
}

try {
    // Получение информации о теме
    $stmt = $pdo->prepare("
        SELECT t.*, u.username, u.avatar, u.reputation, c.name as category_name, c.slug as category_slug, c.color as category_color
        FROM topics t 
        JOIN users u ON t.user_id = u.id 
        JOIN categories c ON t.category_id = c.id 
        WHERE t.id = ?
    ");
    $stmt->execute([$topic_id]);
    $topic = $stmt->fetch();
    
    if (!$topic) {
        $error = 'Тема не найдена';
    } else {
        // Получение сообщений в теме
        $stmt = $pdo->prepare("
            SELECT p.*, u.username, u.avatar, u.reputation, u.join_date
            FROM posts p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.topic_id = ? 
            ORDER BY p.created_at ASC
        ");
        $stmt->execute([$topic_id]);
        $posts = $stmt->fetchAll();
        
        // Обновление счетчика просмотров
        $stmt = $pdo->prepare("UPDATE topics SET views = COALESCE(views, 0) + 1 WHERE id = ?");
        $stmt->execute([$topic_id]);
        
        // Получение тегов темы
        $stmt = $pdo->prepare("
            SELECT tag.name, tag.color 
            FROM tags tag 
            JOIN topic_tags tt ON tag.id = tt.tag_id 
            WHERE tt.topic_id = ?
        ");
        $stmt->execute([$topic_id]);
        $tags = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error = 'Ошибка при загрузке темы';
}

// Обработка нового сообщения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $content = trim($_POST['content'] ?? '');
    
    if (empty($content)) {
        $error = 'Сообщение не может быть пустым';
    } elseif ($topic && $topic['is_locked']) {
        $error = 'Тема закрыта для новых сообщений';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO posts (content, user_id, topic_id) VALUES (?, ?, ?)");
            $stmt->execute([$content, $_SESSION['user_id'], $topic_id]);
            
            // Обновляем время последнего обновления темы
            $stmt = $pdo->prepare("UPDATE topics SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$topic_id]);
            
            // Перенаправляем на ту же страницу для обновления
            header("Location: topic.php?id=$topic_id#post-" . $pdo->lastInsertId());
            exit;
        } catch (PDOException $e) {
            $error = 'Ошибка при добавлении сообщения';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $topic ? escape($topic['title']) : 'Тема'; ?> - <?php echo SITE_NAME; ?></title>
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
        <?php elseif ($topic): ?>
            <!-- Навигация по теме -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Главная</a></li>
                    <li class="breadcrumb-item"><a href="categories.php">Категории</a></li>
                    <li class="breadcrumb-item">
                        <a href="category.php?slug=<?php echo $topic['category_slug']; ?>">
                            <?php echo escape($topic['category_name']); ?>
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo escape($topic['title']); ?></li>
                </ol>
            </nav>

            <!-- Заголовок темы -->
            <div class="topic-header mb-4">
                <div class="row align-items-start">
                    <div class="col-md-8">
                        <h1 class="mb-3">
                            <?php if ($topic['is_pinned']): ?>
                                <i class="fas fa-thumbtack text-warning me-2" title="Закреплено"></i>
                            <?php endif; ?>
                            <?php if ($topic['is_locked']): ?>
                                <i class="fas fa-lock text-danger me-2" title="Закрыто"></i>
                            <?php endif; ?>
                            <?php echo escape($topic['title']); ?>
                        </h1>
                        
                        <!-- Теги -->
                        <?php if (!empty($tags)): ?>
                            <div class="topic-tags mb-3">
                                <?php foreach ($tags as $tag): ?>
                                    <span class="badge" style="background-color: <?php echo $tag['color']; ?>; color: white;">
                                        <?php echo escape($tag['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4 text-md-end">
                        <div class="topic-actions">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <?php if (!$topic['is_locked']): ?>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#replyModal">
                                        <i class="fas fa-reply me-2"></i>Ответить
                                    </button>
                                <?php endif; ?>
                                <?php if ($_SESSION['is_admin'] || $_SESSION['user_id'] == $topic['user_id']): ?>
                                    <div class="btn-group ms-2" role="group">
                                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#"><i class="fas fa-edit me-2"></i>Редактировать</a></li>
                                            <?php if ($_SESSION['is_admin']): ?>
                                                <li><a class="dropdown-item" href="#"><i class="fas fa-thumbtack me-2"></i>Закрепить</a></li>
                                                <li><a class="dropdown-item" href="#"><i class="fas fa-lock me-2"></i>Закрыть</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Сообщения -->
            <div class="posts-container">
                <!-- Первое сообщение (тема) -->
                <div class="post-item" id="topic-<?php echo $topic['id']; ?>">
                    <div class="row">
                        <div class="col-md-2 col-sm-3 mb-3">
                            <div class="post-author text-center">
                                <div class="author-avatar mb-2">
                                    <?php if ($topic['avatar'] && $topic['avatar'] !== 'default-avatar.png'): ?>
                                        <img src="uploads/avatars/<?php echo escape($topic['avatar']); ?>" 
                                             alt="Avatar" class="rounded-circle" width="80" height="80">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <i class="fas fa-user fa-2x"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <h6 class="author-name"><?php echo escape($topic['username']); ?></h6>
                                <div class="author-info">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('d.m.Y H:i', strtotime($topic['created_at'])); ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-star me-1"></i>
                                        Репутация: <?php echo number_format($topic['reputation']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-10 col-sm-9">
                            <div class="post-content">
                                <div class="post-text">
                                    <?php echo nl2br(escape($topic['content'])); ?>
                                </div>
                                
                                <div class="post-footer mt-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="post-actions">
                                            <?php if (isset($_SESSION['user_id'])): ?>
                                                <button class="btn btn-sm btn-outline-primary me-2" onclick="likePost(<?php echo $topic['id']; ?>, 'topic')">
                                                    <i class="fas fa-thumbs-up me-1"></i>
                                                    <span class="like-count">0</span>
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary me-2">
                                                    <i class="fas fa-share me-1"></i>Поделиться
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="post-meta text-muted">
                                            <small>
                                                <i class="fas fa-eye me-1"></i><?php echo number_format($topic['views']); ?> просмотров
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ответы -->
                <?php foreach ($posts as $post): ?>
                    <div class="post-item" id="post-<?php echo $post['id']; ?>">
                        <div class="row">
                            <div class="col-md-2 col-sm-3 mb-3">
                                <div class="post-author text-center">
                                    <div class="author-avatar mb-2">
                                        <?php if ($post['avatar'] && $post['avatar'] !== 'default-avatar.png'): ?>
                                            <img src="uploads/avatars/<?php echo escape($post['avatar']); ?>" 
                                                 alt="Avatar" class="rounded-circle" width="80" height="80">
                                        <?php else: ?>
                                            <div class="avatar-placeholder">
                                                <i class="fas fa-user fa-2x"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="author-name"><?php echo escape($post['username']); ?></h6>
                                    <div class="author-info">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('d.m.Y H:i', strtotime($post['created_at'])); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-star me-1"></i>
                                            Репутация: <?php echo number_format($post['reputation']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-10 col-sm-9">
                                <div class="post-content">
                                    <div class="post-text">
                                        <?php echo nl2br(escape($post['content'])); ?>
                                    </div>
                                    
                                    <div class="post-footer mt-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="post-actions">
                                                <?php if (isset($_SESSION['user_id'])): ?>
                                                    <button class="btn btn-sm btn-outline-primary me-2" onclick="likePost(<?php echo $post['id']; ?>, 'post')">
                                                        <i class="fas fa-thumbs-up me-1"></i>
                                                        <span class="like-count">0</span>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary me-2">
                                                        <i class="fas fa-reply me-1"></i>Ответить
                                                    </button>
                                                    <?php if ($_SESSION['is_admin'] || $_SESSION['user_id'] == $post['user_id']): ?>
                                                        <button class="btn btn-sm btn-outline-warning me-2">
                                                            <i class="fas fa-edit me-1"></i>Редактировать
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="post-meta text-muted">
                                                <small>
                                                    #<?php echo $post['id']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Форма ответа -->
            <?php if (isset($_SESSION['user_id']) && !$topic['is_locked']): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-reply me-2"></i>Добавить ответ
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="topic.php?id=<?php echo $topic_id; ?>">
                            <div class="mb-3">
                                <label for="content" class="form-label">Ваш ответ</label>
                                <textarea class="form-control" id="content" name="content" rows="5" 
                                          required placeholder="Напишите ваш ответ..."></textarea>
                                <div class="form-text">Используйте Markdown для форматирования</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Отправить ответ
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Модальное окно для ответа -->
    <div class="modal fade" id="replyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-reply me-2"></i>Ответить в теме
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="topic.php?id=<?php echo $topic_id; ?>">
                        <div class="mb-3">
                            <label for="modalContent" class="form-label">Ваш ответ</label>
                            <textarea class="form-control" id="modalContent" name="content" rows="8" 
                                      required placeholder="Напишите ваш ответ..."></textarea>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-primary">Отправить</button>
                        </div>
                    </form>
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
        // Функция для лайков
        function likePost(postId, type) {
            // Здесь будет AJAX запрос для лайков
            console.log('Лайк для', type, postId);
        }
        
        // Плавная прокрутка к якорям
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Автофокус на форму ответа при открытии модального окна
        document.getElementById('replyModal').addEventListener('shown.bs.modal', function () {
            document.getElementById('modalContent').focus();
        });
    </script>
    
    <style>
        .topic-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 2rem;
            border-radius: var(--border-radius);
            border: 1px solid #dee2e6;
        }
        
        .topic-tags .badge {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
            padding: 0.5rem 0.75rem;
        }
        
        .post-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .post-item:last-child {
            margin-bottom: 0;
        }
        
        .post-author {
            padding: 1rem;
        }
        
        .author-avatar {
            margin-bottom: 1rem;
        }
        
        .avatar-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        
        .author-name {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .author-info small {
            line-height: 1.4;
        }
        
        .post-content {
            padding: 1.5rem;
            border-left: 1px solid #e9ecef;
        }
        
        .post-text {
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .post-footer {
            border-top: 1px solid #e9ecef;
            padding-top: 1rem;
        }
        
        .post-actions .btn {
            font-size: 0.875rem;
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
        
        .topic-actions .btn-group {
            margin-top: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .topic-header {
                padding: 1rem;
            }
            
            .post-content {
                padding: 1rem;
            }
            
            .post-author {
                padding: 0.5rem;
            }
            
            .topic-actions {
                margin-top: 1rem;
                text-align: center;
            }
        }
    </style>
</body>
</html>