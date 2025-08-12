<?php
require_once 'config.php';
initSession();

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        
        // Проверяем тип файла
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Разрешены только изображения в форматах JPG, PNG и GIF';
        }
        
        // Проверяем размер файла (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            $error = 'Размер файла не должен превышать 2MB';
        }
        
        if (empty($error)) {
            try {
                $pdo = getDBConnection();
                
                // Создаем папку для аватаров, если её нет
                $upload_dir = 'uploads/avatars/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Генерируем уникальное имя файла
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                // Загружаем файл
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Обновляем базу данных
                    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->execute([$filename, $_SESSION['user_id']]);
                    
                    $success = 'Аватар успешно обновлен!';
                    
                    // Перенаправляем обратно в профиль
                    header('Refresh: 2; URL=profile.php');
                } else {
                    $error = 'Ошибка при загрузке файла';
                }
            } catch (PDOException $e) {
                $error = 'Ошибка при обновлении аватара';
            }
        }
    } else {
        $error = 'Файл не был загружен';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загрузка аватара - <?php echo SITE_NAME; ?></title>
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
        </div>
    </nav>

    <!-- Основной контент -->
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white text-center">
                        <h4 class="mb-0">
                            <i class="fas fa-camera me-2"></i>Загрузка аватара
                        </h4>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo escape($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo escape($success); ?>
                                <p class="mb-0 mt-2">Перенаправление в профиль...</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center">
                            <a href="profile.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>Вернуться в профиль
                            </a>
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
</body>
</html>