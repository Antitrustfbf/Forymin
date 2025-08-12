<?php
require_once 'config.php';
initSession();

// Если пользователь уже авторизован, перенаправляем на главную
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $agree_terms = isset($_POST['agree_terms']);
    
    // Валидация
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Пожалуйста, заполните все поля';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = 'Имя пользователя должно содержать от 3 до 20 символов';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Имя пользователя может содержать только буквы, цифры и знак подчеркивания';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email адрес';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Пароль должен содержать минимум ' . PASSWORD_MIN_LENGTH . ' символов';
    } elseif ($password !== $confirm_password) {
        $error = 'Пароли не совпадают';
    } elseif (!$agree_terms) {
        $error = 'Необходимо согласиться с условиями использования';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Проверяем, не занято ли имя пользователя или email
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $error = 'Пользователь с таким именем или email уже существует';
            } else {
                // Создаем нового пользователя
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, join_date) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$username, $email, $password_hash]);
                
                $user_id = $pdo->lastInsertId();
                
                // Автоматически авторизуем пользователя
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['is_admin'] = false;
                
                $success = 'Регистрация успешна! Добро пожаловать в сообщество! Перенаправление...';
                header('Refresh: 3; URL=index.php');
            }
        } catch (PDOException $e) {
            $error = 'Ошибка при регистрации. Попробуйте позже.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Регистрация</title>
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
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Войти
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="register.php">
                            <i class="fas fa-user-plus me-1"></i>Регистрация
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Основной контент -->
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg">
                    <div class="card-header bg-success text-white text-center py-3">
                        <h4 class="mb-0">
                            <i class="fas fa-user-plus me-2"></i>Создание аккаунта
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
                        
                        <form method="POST" action="register.php" id="registerForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">
                                            <i class="fas fa-user me-2"></i>Имя пользователя
                                        </label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo escape($_POST['username'] ?? ''); ?>" 
                                               required autofocus>
                                        <div class="form-text">От 3 до 20 символов, только буквы, цифры и _</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">
                                            <i class="fas fa-envelope me-2"></i>Email адрес
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo escape($_POST['email'] ?? ''); ?>" 
                                               required>
                                        <div class="form-text">Будет использоваться для восстановления пароля</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Пароль
                                        </label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Минимум <?php echo PASSWORD_MIN_LENGTH; ?> символов</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Подтверждение пароля
                                        </label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="confirm_password" 
                                                   name="confirm_password" required>
                                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Повторите пароль</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="agree_terms" name="agree_terms" required>
                                <label class="form-check-label" for="agree_terms">
                                    Я согласен с <a href="terms.php" target="_blank">условиями использования</a> и 
                                    <a href="privacy.php" target="_blank">политикой конфиденциальности</a>
                                </label>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Создать аккаунт
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="text-muted mb-2">Уже есть аккаунт?</p>
                            <a href="login.php" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Войти в систему
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Преимущества регистрации -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="card-title text-center mb-3">
                            <i class="fas fa-star me-2 text-warning"></i>Преимущества регистрации
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="feature-item mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="feature-icon me-3">
                                            <i class="fas fa-comments text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Создание тем</h6>
                                            <p class="small text-muted mb-0">Задавайте вопросы и делитесь опытом</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="feature-item mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="feature-icon me-3">
                                            <i class="fas fa-reply text-success"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Ответы на вопросы</h6>
                                            <p class="small text-muted mb-0">Помогайте другим участникам</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="feature-item mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="feature-icon me-3">
                                            <i class="fas fa-bell text-warning"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Уведомления</h6>
                                            <p class="small text-muted mb-0">Получайте уведомления об ответах</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="feature-item mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="feature-icon me-3">
                                            <i class="fas fa-trophy text-danger"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Репутация</h6>
                                            <p class="small text-muted mb-0">Зарабатывайте репутацию в сообществе</p>
                                        </div>
                                    </div>
                                </div>
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
        // Переключение видимости паролей
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                password.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPassword = document.getElementById('confirm_password');
            const icon = this.querySelector('i');
            
            if (confirmPassword.type === 'password') {
                confirmPassword.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                confirmPassword.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
        
        // Валидация формы
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const agreeTerms = document.getElementById('agree_terms').checked;
            
            // Сброс предыдущих ошибок
            clearValidationErrors();
            
            let hasErrors = false;
            
            // Проверка имени пользователя
            if (username.length < 3 || username.length > 20) {
                showFieldError('username', 'Имя пользователя должно содержать от 3 до 20 символов');
                hasErrors = true;
            } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                showFieldError('username', 'Имя пользователя может содержать только буквы, цифры и знак подчеркивания');
                hasErrors = true;
            }
            
            // Проверка email
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showFieldError('email', 'Введите корректный email адрес');
                hasErrors = true;
            }
            
            // Проверка пароля
            if (password.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                showFieldError('password', 'Пароль должен содержать минимум <?php echo PASSWORD_MIN_LENGTH; ?> символов');
                hasErrors = true;
            }
            
            // Проверка подтверждения пароля
            if (password !== confirmPassword) {
                showFieldError('confirm_password', 'Пароли не совпадают');
                hasErrors = true;
            }
            
            // Проверка согласия с условиями
            if (!agreeTerms) {
                showFieldError('agree_terms', 'Необходимо согласиться с условиями использования');
                hasErrors = true;
            }
            
            if (hasErrors) {
                e.preventDefault();
                return false;
            }
        });
        
        // Показать ошибку для поля
        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback d-block';
            errorDiv.textContent = message;
            
            field.classList.add('is-invalid');
            field.parentNode.appendChild(errorDiv);
        }
        
        // Очистить ошибки валидации
        function clearValidationErrors() {
            document.querySelectorAll('.is-invalid').forEach(field => {
                field.classList.remove('is-invalid');
            });
            
            document.querySelectorAll('.invalid-feedback').forEach(error => {
                error.remove();
            });
        }
        
        // Анимация появления формы
        document.addEventListener('DOMContentLoaded', function() {
            const card = document.querySelector('.card');
            card.classList.add('fade-in');
        });
        
        // Проверка силы пароля
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.getElementById('passwordStrength') || createPasswordStrengthIndicator();
            
            let strength = 0;
            let feedback = '';
            
            if (password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            switch (strength) {
                case 0:
                case 1:
                    feedback = 'Очень слабый';
                    strengthIndicator.className = 'password-strength very-weak';
                    break;
                case 2:
                    feedback = 'Слабый';
                    strengthIndicator.className = 'password-strength weak';
                    break;
                case 3:
                    feedback = 'Средний';
                    strengthIndicator.className = 'password-strength medium';
                    break;
                case 4:
                    feedback = 'Хороший';
                    strengthIndicator.className = 'password-strength good';
                    break;
                case 5:
                    feedback = 'Отличный';
                    strengthIndicator.className = 'password-strength excellent';
                    break;
            }
            
            strengthIndicator.textContent = feedback;
        });
        
        // Создать индикатор силы пароля
        function createPasswordStrengthIndicator() {
            const indicator = document.createElement('div');
            indicator.id = 'passwordStrength';
            indicator.className = 'password-strength';
            
            const passwordField = document.getElementById('password');
            passwordField.parentNode.appendChild(indicator);
            
            return indicator;
        }
    </script>
    
    <style>
        .feature-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(0, 123, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .password-strength {
            margin-top: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .password-strength.very-weak { color: #dc3545; }
        .password-strength.weak { color: #fd7e14; }
        .password-strength.medium { color: #ffc107; }
        .password-strength.good { color: #28a745; }
        .password-strength.excellent { color: #20c997; }
    </style>
</body>
</html>