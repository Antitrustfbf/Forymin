<?php
require_once 'config.php';
initSession();

$pdo = getDBConnection();
$error = '';
$success = '';
$user = null;
$user_topics = [];
$user_posts = [];

// Получение ID пользователя
$user_id = isset($_GET['user']) ? (int)$_GET['user'] : ($_SESSION['user_id'] ?? 0);

if ($user_id === 0) {
    header('Location: index.php');
    exit;
}

try {
    // Получение информации о пользователе
    $stmt = $pdo->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM topics WHERE user_id = u.id) as topics_count,
               (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as posts_count,
               (SELECT COUNT(*) FROM likes WHERE user_id = u.id) as likes_given,
               (SELECT COUNT(*) FROM likes l JOIN posts p ON l.post_id = p.id WHERE p.user_id = u.id) as likes_received
        FROM users u 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = 'Пользователь не найден';
    } else {
        // Получение тем пользователя
        $stmt = $pdo->prepare("
            SELECT t.*, c.name as category_name, c.slug as category_slug,
                   (SELECT COUNT(p.id) FROM posts p WHERE p.topic_id = t.id) as reply_count
            FROM topics t 
            JOIN categories c ON t.category_id = c.id 
            WHERE t.user_id = ? 
            ORDER BY t.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $user_topics = $stmt->fetchAll();
        
        // Получение последних сообщений пользователя
        $stmt = $pdo->prepare("
            SELECT p.*, t.title as topic_title, t.id as topic_id, c.name as category_name
            FROM posts p 
            JOIN topics t ON p.topic_id = t.id 
            JOIN categories c ON t.category_id = c.id 
            WHERE p.user_id = ? 
            ORDER BY p.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $user_posts = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error = 'Ошибка при загрузке профиля';
}

// Обработка обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
    $bio = trim($_POST['bio'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Обновление основной информации
        if ($bio !== $user['bio'] || $email !== $user['email']) {
            $stmt = $pdo->prepare("UPDATE users SET bio = ?, email = ? WHERE id = ?");
            $stmt->execute([$bio, $email, $user_id]);
        }
        
        // Смена пароля
        if (!empty($current_password) && !empty($new_password)) {
            if (!password_verify($current_password, $user['password_hash'])) {
                $error = 'Текущий пароль указан неверно';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Новые пароли не совпадают';
            } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
                $error = 'Новый пароль должен содержать минимум ' . PASSWORD_MIN_LENGTH . ' символов';
            } else {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$new_password_hash, $user_id]);
                $success = 'Пароль успешно изменен';
            }
        }
        
        if (empty($error)) {
            $pdo->commit();
            $success = 'Профиль успешно обновлен';
            // Обновляем данные пользователя
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } else {
            $pdo->rollBack();
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Ошибка при обновлении профиля';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль <?php echo $user ? escape($user['username']) : 'пользователя'; ?> - <?php echo SITE_NAME; ?></title>
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
                                <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-user-circle me-2"></i>Профиль</a></li>
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
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo escape($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($user): ?>
            <!-- Заголовок профиля -->
            <div class="profile-header mb-4">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <div class="profile-avatar mb-3">
                            <?php if ($user['avatar'] && $user['avatar'] !== 'default-avatar.png'): ?>
                                <img src="uploads/avatars/<?php echo escape($user['avatar']); ?>" 
                                     alt="Avatar" class="rounded-circle" width="150" height="150">
                            <?php else: ?>
                                <div class="avatar-placeholder-large">
                                    <i class="fas fa-user fa-4x"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id): ?>
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#avatarModal">
                                <i class="fas fa-camera me-1"></i>Изменить аватар
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <h1 class="mb-2">
                            <?php echo escape($user['username']); ?>
                            <?php if ($user['is_admin']): ?>
                                <span class="badge bg-danger ms-2">
                                    <i class="fas fa-crown me-1"></i>Администратор
                                </span>
                            <?php endif; ?>
                            <?php if ($user['is_banned']): ?>
                                <span class="badge bg-dark ms-2">
                                    <i class="fas fa-ban me-1"></i>Заблокирован
                                </span>
                            <?php endif; ?>
                        </h1>
                        
                        <p class="text-muted mb-3">
                            <i class="fas fa-calendar me-1"></i>
                            Участник с <?php echo date('d.m.Y', strtotime($user['join_date'])); ?>
                            
                            <?php if ($user['last_login']): ?>
                                <span class="mx-3">
                                    <i class="fas fa-clock me-1"></i>
                                    Последний вход: <?php echo date('d.m.Y H:i', strtotime($user['last_login'])); ?>
                                </span>
                            <?php endif; ?>
                        </p>
                        
                        <?php if ($user['bio']): ?>
                            <div class="user-bio">
                                <p class="mb-0"><?php echo nl2br(escape($user['bio'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="profile-stats text-center">
                            <div class="stat-item mb-3">
                                <div class="stat-number"><?php echo number_format($user['reputation']); ?></div>
                                <div class="stat-label">Репутация</div>
                            </div>
                            
                            <div class="stat-item mb-3">
                                <div class="stat-number"><?php echo number_format($user['topics_count']); ?></div>
                                <div class="stat-label">Тем</div>
                            </div>
                            
                            <div class="stat-item mb-3">
                                <div class="stat-number"><?php echo number_format($user['posts_count']); ?></div>
                                <div class="stat-label">Сообщений</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Статистика активности -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon topics">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3 class="stat-number"><?php echo number_format($user['topics_count']); ?></h3>
                        <p class="stat-label">Созданных тем</p>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon posts">
                            <i class="fas fa-reply"></i>
                        </div>
                        <h3 class="stat-number"><?php echo number_format($user['posts_count']); ?></h3>
                        <p class="stat-label">Сообщений</p>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon likes">
                            <i class="fas fa-thumbs-up"></i>
                        </div>
                        <h3 class="stat-number"><?php echo number_format($user['likes_given']); ?></h3>
                        <p class="stat-label">Лайков поставлено</p>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card text-center">
                        <div class="stat-icon reputation">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3 class="stat-number"><?php echo number_format($user['likes_received']); ?></h3>
                        <p class="stat-label">Лайков получено</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Редактирование профиля -->
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id): ?>
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-edit me-2"></i>Редактировать профиль
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="profile.php">
                                    <div class="mb-3">
                                        <label for="bio" class="form-label">О себе</label>
                                        <textarea class="form-control" id="bio" name="bio" rows="4" 
                                                  placeholder="Расскажите о себе..."><?php echo escape($user['bio'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo escape($user['email']); ?>" required>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Текущий пароль</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password">
                                        <div class="form-text">Оставьте пустым, если не хотите менять пароль</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">Новый пароль</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Подтверждение пароля</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Сохранить изменения
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Темы пользователя -->
                <div class="col-lg-<?php echo (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) ? '8' : '12'; ?>">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-comments me-2"></i>Темы пользователя
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($user_topics)): ?>
                                <div class="p-4 text-center text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p>Пользователь пока не создал тем</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($user_topics as $topic): ?>
                                    <div class="user-topic-item">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <h6 class="mb-1">
                                                    <a href="topic.php?id=<?php echo $topic['id']; ?>" class="text-decoration-none">
                                                        <?php if ($topic['is_pinned']): ?>
                                                            <i class="fas fa-thumbtack text-warning me-1"></i>
                                                        <?php endif; ?>
                                                        <?php if ($topic['is_locked']): ?>
                                                            <i class="fas fa-lock text-danger me-1"></i>
                                                        <?php endif; ?>
                                                        <?php echo escape($topic['title']); ?>
                                                    </a>
                                                </h6>
                                                <div class="topic-meta">
                                                    <small class="text-muted">
                                                        <i class="fas fa-folder me-1"></i>
                                                        <a href="category.php?slug=<?php echo $topic['category_slug']; ?>" class="text-decoration-none">
                                                            <?php echo escape($topic['category_name']); ?>
                                                        </a>
                                                        <span class="mx-2">•</span>
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo date('d.m.Y H:i', strtotime($topic['created_at'])); ?>
                                                        <span class="mx-2">•</span>
                                                        <i class="fas fa-reply me-1"></i><?php echo $topic['reply_count']; ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <div class="topic-stats">
                                                    <span class="badge bg-light text-dark me-2">
                                                        <i class="fas fa-eye me-1"></i><?php echo number_format($topic['views']); ?>
                                                    </span>
                                                    <span class="badge bg-light text-dark">
                                                        <i class="fas fa-reply me-1"></i><?php echo $topic['reply_count']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if ($user['topics_count'] > 10): ?>
                                    <div class="p-3 text-center">
                                        <a href="search.php?type=topics&user=<?php echo $user_id; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-list me-2"></i>Показать все темы
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Последние сообщения -->
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-reply me-2"></i>Последние сообщения
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($user_posts)): ?>
                                <div class="p-4 text-center text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p>Пользователь пока не написал сообщений</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($user_posts as $post): ?>
                                    <div class="user-post-item">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h6 class="mb-1">
                                                    <a href="topic.php?id=<?php echo $post['topic_id']; ?>" class="text-decoration-none">
                                                        <?php echo escape($post['topic_title']); ?>
                                                    </a>
                                                </h6>
                                                <div class="post-excerpt mb-2">
                                                    <?php 
                                                    $excerpt = strip_tags($post['content']);
                                                    $excerpt = substr($excerpt, 0, 150);
                                                    if (strlen($post['content']) > 150) {
                                                        $excerpt .= '...';
                                                    }
                                                    echo escape($excerpt);
                                                    ?>
                                                </div>
                                                <div class="post-meta">
                                                    <small class="text-muted">
                                                        <i class="fas fa-folder me-1"></i>
                                                        <a href="category.php?slug=<?php echo $post['category_slug']; ?>" class="text-decoration-none">
                                                            <?php echo escape($post['category_name']); ?>
                                                        </a>
                                                        <span class="mx-2">•</span>
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo date('d.m.Y H:i', strtotime($post['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <a href="topic.php?id=<?php echo $post['topic_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i>Просмотреть
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if ($user['posts_count'] > 10): ?>
                                    <div class="p-3 text-center">
                                        <a href="search.php?type=posts&user=<?php echo $user_id; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-list me-2"></i>Показать все сообщения
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Модальное окно для смены аватара -->
    <div class="modal fade" id="avatarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-camera me-2"></i>Изменить аватар
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="upload-avatar.php" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="avatar" class="form-label">Выберите изображение</label>
                            <input type="file" class="form-control" id="avatar" name="avatar" 
                                   accept="image/*" required>
                            <div class="form-text">Поддерживаются форматы: JPG, PNG, GIF. Максимальный размер: 2MB</div>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-primary">Загрузить</button>
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
        // Валидация формы смены пароля
        document.addEventListener('DOMContentLoaded', function() {
            const currentPassword = document.getElementById('current_password');
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (currentPassword && newPassword && confirmPassword) {
                function validatePasswords() {
                    if (newPassword.value || confirmPassword.value) {
                        if (!currentPassword.value) {
                            currentPassword.setCustomValidity('Введите текущий пароль для смены');
                        } else {
                            currentPassword.setCustomValidity('');
                        }
                        
                        if (newPassword.value !== confirmPassword.value) {
                            confirmPassword.setCustomValidity('Пароли не совпадают');
                        } else {
                            confirmPassword.setCustomValidity('');
                        }
                    } else {
                        currentPassword.setCustomValidity('');
                        confirmPassword.setCustomValidity('');
                    }
                }
                
                currentPassword.addEventListener('input', validatePasswords);
                newPassword.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }
        });
        
        // Анимация появления элементов
        document.addEventListener('DOMContentLoaded', function() {
            const animatedElements = document.querySelectorAll('.stat-card, .user-topic-item, .user-post-item');
            animatedElements.forEach((el, index) => {
                setTimeout(() => {
                    el.classList.add('fade-in');
                }, index * 100);
            });
        });
    </script>
    
    <style>
        .profile-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 2rem;
            border-radius: var(--border-radius);
            border: 1px solid #dee2e6;
        }
        
        .profile-avatar img {
            border: 4px solid white;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .avatar-placeholder-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            border: 4px solid white;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .user-bio {
            background: rgba(255, 255, 255, 0.7);
            padding: 1rem;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
        }
        
        .profile-stats .stat-item {
            text-align: center;
        }
        
        .profile-stats .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .profile-stats .stat-label {
            font-size: 0.875rem;
            color: var(--secondary-color);
            text-transform: uppercase;
        }
        
        .user-topic-item,
        .user-post-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            transition: var(--transition);
        }
        
        .user-topic-item:last-child,
        .user-post-item:last-child {
            border-bottom: none;
        }
        
        .user-topic-item:hover,
        .user-post-item:hover {
            background-color: #f8f9fa;
        }
        
        .post-excerpt {
            color: var(--secondary-color);
            line-height: 1.5;
        }
        
        .stat-icon.likes { background: linear-gradient(135deg, #e83e8c 0%, #fd7e14 100%); }
        .stat-icon.reputation { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
        
        @media (max-width: 768px) {
            .profile-header {
                padding: 1rem;
                text-align: center;
            }
            
            .profile-stats {
                margin-top: 1rem;
            }
            
            .user-topic-item,
            .user-post-item {
                padding: 1rem;
            }
        }
    </style>
</body>
</html>