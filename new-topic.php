<?php
require_once 'config.php';
initSession();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$error = '';
$success = '';

// Получение категорий
$stmt = $pdo->query("SELECT id, name, slug FROM categories ORDER BY sort_order, name");
$categories = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $tags = trim($_POST['tags'] ?? '');
    
    if (empty($title) || empty($content) || $category_id === 0) {
        $error = 'Пожалуйста, заполните все обязательные поля';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Создание темы
            $stmt = $pdo->prepare("INSERT INTO topics (title, content, user_id, category_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $content, $_SESSION['user_id'], $category_id]);
            $topic_id = $pdo->lastInsertId();
            
            // Обработка тегов
            if (!empty($tags)) {
                $tag_array = array_map('trim', explode(',', $tags));
                foreach ($tag_array as $tag_name) {
                    if (!empty($tag_name)) {
                        // Проверяем существование тега
                        $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                        $stmt->execute([$tag_name]);
                        $tag = $stmt->fetch();
                        
                        if (!$tag) {
                            // Создаем новый тег
                            $stmt = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
                            $stmt->execute([$tag_name]);
                            $tag_id = $pdo->lastInsertId();
                        } else {
                            $tag_id = $tag['id'];
                        }
                        
                        // Связываем тег с темой
                        $stmt = $pdo->prepare("INSERT INTO topic_tags (topic_id, tag_id) VALUES (?, ?)");
                        $stmt->execute([$topic_id, $tag_id]);
                    }
                }
            }
            
            $pdo->commit();
            $success = 'Тема успешно создана!';
            header("Refresh: 2; URL=topic.php?id=$topic_id");
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Ошибка при создании темы. Попробуйте позже.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Новая тема</title>
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
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo escape($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i>Профиль</a></li>
                            <li><a class="dropdown-item active" href="new-topic.php"><i class="fas fa-plus me-2"></i>Новая тема</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Выйти</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Основной контент -->
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-plus me-2"></i>Создать новую тему
                        </h4>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo escape($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo escape($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="new-topic.php" id="newTopicForm">
                            <div class="mb-3">
                                <label for="title" class="form-label">
                                    <i class="fas fa-heading me-2"></i>Заголовок темы
                                </label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo escape($_POST['title'] ?? ''); ?>" 
                                       required maxlength="255" placeholder="Введите заголовок темы">
                                <div class="form-text">Кратко опишите суть вопроса или темы</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="category_id" class="form-label">
                                    <i class="fas fa-folder me-2"></i>Категория
                                </label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Выберите категорию</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo escape($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="content" class="form-label">
                                    <i class="fas fa-edit me-2"></i>Содержание
                                </label>
                                <textarea class="form-control" id="content" name="content" rows="10" 
                                          required placeholder="Подробно опишите ваш вопрос или тему..."><?php echo escape($_POST['content'] ?? ''); ?></textarea>
                                <div class="form-text">Используйте Markdown для форматирования</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="tags" class="form-label">
                                    <i class="fas fa-tags me-2"></i>Теги
                                </label>
                                <input type="text" class="form-control" id="tags" name="tags" 
                                       value="<?php echo escape($_POST['tags'] ?? ''); ?>" 
                                       placeholder="termux, linux, bash (через запятую)">
                                <div class="form-text">Добавьте теги для лучшей категоризации</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Создать тему
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Отмена
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Подсказки по созданию тем -->
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-lightbulb me-2 text-warning"></i>Советы по созданию хорошей темы
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Используйте четкий и понятный заголовок
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Опишите проблему подробно, но без лишней информации
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Добавьте код в блоки кода для лучшей читаемости
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                Укажите версию Termux и используемые пакеты
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check text-success me-2"></i>
                                Добавьте соответствующие теги для категоризации
                            </li>
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
        // Валидация формы
        document.getElementById('newTopicForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const content = document.getElementById('content').value.trim();
            const category = document.getElementById('category_id').value;
            
            if (!title || !content || !category) {
                e.preventDefault();
                TermuxForum.showAlert('Пожалуйста, заполните все обязательные поля', 'warning');
                return false;
            }
            
            if (title.length < 10) {
                e.preventDefault();
                TermuxForum.showAlert('Заголовок должен содержать минимум 10 символов', 'warning');
                return false;
            }
            
            if (content.length < 50) {
                e.preventDefault();
                TermuxForum.showAlert('Содержание должно содержать минимум 50 символов', 'warning');
                return false;
            }
        });
        
        // Подсчет символов
        document.getElementById('title').addEventListener('input', function() {
            const length = this.value.length;
            const maxLength = 255;
            const remaining = maxLength - length;
            
            let feedback = this.parentNode.querySelector('.form-text');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'form-text';
                this.parentNode.appendChild(feedback);
            }
            
            if (remaining < 0) {
                feedback.textContent = `Превышен лимит символов (${maxLength})`;
                feedback.className = 'form-text text-danger';
            } else {
                feedback.textContent = `Осталось символов: ${remaining}`;
                feedback.className = 'form-text';
            }
        });
        
        document.getElementById('content').addEventListener('input', function() {
            const length = this.value.length;
            const minLength = 50;
            
            let feedback = this.parentNode.querySelector('.form-text');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'form-text';
                this.parentNode.appendChild(feedback);
            }
            
            if (length < minLength) {
                feedback.textContent = `Минимум ${minLength} символов. Текущее количество: ${length}`;
                feedback.className = 'form-text text-warning';
            } else {
                feedback.textContent = 'Используйте Markdown для форматирования';
                feedback.className = 'form-text text-success';
            }
        });
    </script>
</body>
</html>