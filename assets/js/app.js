// Основной JavaScript файл для Termux Forum

document.addEventListener('DOMContentLoaded', function() {
    // Инициализация всех компонентов
    initTooltips();
    initModals();
    initSearch();
    initThemeToggle();
    initMobileMenu();
    initAnimations();
    initCodeHighlighting();
});

// Инициализация тултипов Bootstrap
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Инициализация модальных окон
function initModals() {
    const modalTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="modal"]'));
    modalTriggerList.map(function (modalTriggerEl) {
        return new bootstrap.Modal(modalTriggerEl);
    });
}

// Инициализация поиска
function initSearch() {
    const searchForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('searchInput');
    
    if (searchForm && searchInput) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const query = searchInput.value.trim();
            if (query.length > 2) {
                performSearch(query);
            } else {
                showAlert('Введите минимум 3 символа для поиска', 'warning');
            }
        });
        
        // Автодополнение поиска
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length > 2) {
                // Здесь можно добавить AJAX запрос для автодополнения
                debounce(() => suggestSearch(query), 300);
            }
        });
    }
}

// Выполнение поиска
function performSearch(query) {
    // Показываем индикатор загрузки
    showLoading();
    
    // Здесь будет AJAX запрос к серверу
    setTimeout(() => {
        hideLoading();
        // Результаты поиска будут загружены через AJAX
        window.location.href = `search.php?q=${encodeURIComponent(query)}`;
    }, 500);
}

// Автодополнение поиска
function suggestSearch(query) {
    // AJAX запрос для получения предложений
    fetch(`api/search-suggestions.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            showSearchSuggestions(data.suggestions);
        })
        .catch(error => {
            console.error('Ошибка автодополнения:', error);
        });
}

// Показать предложения поиска
function showSearchSuggestions(suggestions) {
    const suggestionsContainer = document.getElementById('searchSuggestions');
    if (!suggestionsContainer) return;
    
    if (suggestions.length === 0) {
        suggestionsContainer.style.display = 'none';
        return;
    }
    
    suggestionsContainer.innerHTML = suggestions.map(suggestion => 
        `<div class="suggestion-item" onclick="selectSuggestion('${suggestion}')">${suggestion}</div>`
    ).join('');
    
    suggestionsContainer.style.display = 'block';
}

// Выбор предложения
function selectSuggestion(suggestion) {
    document.getElementById('searchInput').value = suggestion;
    document.getElementById('searchSuggestions').style.display = 'none';
    performSearch(suggestion);
}

// Переключение темы
function initThemeToggle() {
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const currentTheme = document.body.classList.contains('dark-theme') ? 'light' : 'dark';
            setTheme(currentTheme);
        });
    }
    
    // Проверяем сохраненную тему
    const savedTheme = localStorage.getItem('theme') || 'auto';
    if (savedTheme !== 'auto') {
        setTheme(savedTheme);
    }
}

// Установка темы
function setTheme(theme) {
    const body = document.body;
    
    if (theme === 'dark') {
        body.classList.add('dark-theme');
        localStorage.setItem('theme', 'dark');
    } else {
        body.classList.remove('dark-theme');
        localStorage.setItem('theme', 'light');
    }
    
    // Обновляем иконку
    const themeIcon = document.querySelector('#themeToggle i');
    if (themeIcon) {
        themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
}

// Инициализация мобильного меню
function initMobileMenu() {
    const mobileMenuToggle = document.querySelector('.navbar-toggler');
    const mobileMenu = document.querySelector('.navbar-collapse');
    
    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('show');
        });
        
        // Закрытие меню при клике на ссылку
        const mobileMenuLinks = mobileMenu.querySelectorAll('.nav-link');
        mobileMenuLinks.forEach(link => {
            link.addEventListener('click', function() {
                mobileMenu.classList.remove('show');
            });
        });
    }
}

// Инициализация анимаций
function initAnimations() {
    // Анимация появления элементов при скролле
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, observerOptions);
    
    // Наблюдаем за элементами для анимации
    const animatedElements = document.querySelectorAll('.stat-card, .category-item, .recent-topic-item');
    animatedElements.forEach(el => observer.observe(el));
    
    // Анимация счетчиков
    animateCounters();
}

// Анимация счетчиков
function animateCounters() {
    const counters = document.querySelectorAll('.stat-number');
    
    counters.forEach(counter => {
        const target = parseInt(counter.textContent.replace(/,/g, ''));
        const duration = 2000;
        const step = target / (duration / 16);
        let current = 0;
        
        const timer = setInterval(() => {
            current += step;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            counter.textContent = Math.floor(current).toLocaleString();
        }, 16);
    });
}

// Инициализация подсветки кода
function initCodeHighlighting() {
    // Подсветка блоков кода
    const codeBlocks = document.querySelectorAll('pre code, .code-block');
    
    codeBlocks.forEach(block => {
        // Добавляем кнопку копирования
        const copyButton = document.createElement('button');
        copyButton.className = 'btn btn-sm btn-outline-secondary copy-code-btn';
        copyButton.innerHTML = '<i class="fas fa-copy"></i> Копировать';
        copyButton.onclick = () => copyToClipboard(block.textContent);
        
        const buttonContainer = document.createElement('div');
        buttonContainer.className = 'code-header';
        buttonContainer.appendChild(copyButton);
        
        block.parentNode.insertBefore(buttonContainer, block);
        
        // Определяем язык программирования
        const language = detectLanguage(block.textContent);
        if (language) {
            block.classList.add(`language-${language}`);
        }
    });
}

// Определение языка программирования
function detectLanguage(code) {
    const patterns = {
        'bash': /^(#!\/bin\/bash|sudo|apt|pkg|termux|cd |ls |cat |echo )/m,
        'python': /^(import |from |def |class |print\(|if __name__)/m,
        'php': /^(<\?php|function |class |echo |\$[a-zA-Z_])/m,
        'javascript': /^(function |const |let |var |console\.|document\.)/m,
        'html': /^(<!DOCTYPE|<html|<head|<body|<div |<span )/m,
        'css': /^(\.[a-zA-Z]|#[a-fA-F0-9]{3,6}|@media|@import)/m
    };
    
    for (const [lang, pattern] of Object.entries(patterns)) {
        if (pattern.test(code)) {
            return lang;
        }
    }
    
    return null;
}

// Копирование в буфер обмена
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showAlert('Код скопирован в буфер обмена!', 'success');
        });
    } else {
        // Fallback для старых браузеров
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showAlert('Код скопирован в буфер обмена!', 'success');
    }
}

// Показать уведомление
function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alertContainer') || createAlertContainer();
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alert);
    
    // Автоматическое скрытие через 5 секунд
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

// Создать контейнер для уведомлений
function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alertContainer';
    container.className = 'position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

// Показать индикатор загрузки
function showLoading() {
    const loading = document.getElementById('loading') || createLoadingElement();
    loading.style.display = 'block';
}

// Скрыть индикатор загрузки
function hideLoading() {
    const loading = document.getElementById('loading');
    if (loading) {
        loading.style.display = 'none';
    }
}

// Создать элемент загрузки
function createLoadingElement() {
    const loading = document.createElement('div');
    loading.id = 'loading';
    loading.className = 'loading-overlay';
    loading.innerHTML = `
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    `;
    document.body.appendChild(loading);
    return loading;
}

// Функция debounce для оптимизации
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Обработка ошибок
window.addEventListener('error', function(e) {
    console.error('JavaScript ошибка:', e.error);
    showAlert('Произошла ошибка. Попробуйте обновить страницу.', 'danger');
});

// Обработка необработанных промисов
window.addEventListener('unhandledrejection', function(e) {
    console.error('Необработанная ошибка промиса:', e.reason);
    showAlert('Произошла ошибка при загрузке данных.', 'danger');
});

// Экспорт функций для использования в других скриптах
window.TermuxForum = {
    showAlert,
    performSearch,
    setTheme,
    copyToClipboard
};