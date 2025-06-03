<?php

// Підключення всіх необхідних файлів
require_once '../app/Database/Database.php';
require_once '../app/Models/User.php';
require_once '../app/Models/Survey.php';
require_once '../app/Models/Question.php';
require_once '../app/Models/QuestionOption.php';
require_once '../app/Models/SurveyResponse.php';
require_once '../app/Models/QuestionAnswer.php';
require_once '../app/Helpers/Session.php';

// Завантажуємо нові класи для обробки відповідей
require_once '../app/Services/ResponseManager.php';
require_once '../app/Exceptions/CustomExceptions.php';
require_once '../app/Controllers/BaseController.php';
require_once '../app/Router.php';

// Завантажуємо сервіси
require_once '../app/Services/SurveyValidator.php';
require_once '../app/Services/QuestionService.php';
require_once '../app/Services/AdminValidator.php';
require_once '../app/Services/AdminService.php';

// Завантажуємо контролери (тепер успадковані від BaseController)
require_once '../app/Controllers/HomeController.php';
require_once '../app/Controllers/AuthController.php';
require_once '../app/Controllers/AdminController.php';
require_once '../app/Controllers/Survey/SurveyController.php';
require_once '../app/Controllers/Survey/SurveyResponseController.php';
require_once '../app/Controllers/Survey/SurveyResultsController.php';

// Налаштування для розробки (в продакшені відключити)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Встановлюємо часовий пояс
date_default_timezone_set('Europe/Kyiv');

// Перевірка підключення до бази даних
try {
    if (!Database::testConnection()) {
        throw new DatabaseException('Не вдалося підключитися до бази даних');
    }
} catch (Exception $e) {
    ResponseManager::serverError('Сервіс тимчасово недоступний. Спробуйте пізніше.');
    exit;
}

// Створюємо екземпляр роутера
$router = new Router();

// Встановлюємо CORS заголовки для API
$router->handleCors();

// === ГОЛОВНА СТОРІНКА ===
$router->get('/', 'HomeController', 'index');

// === ОСНОВНІ СТОРІНКИ ОПИТУВАНЬ ===
$router->get('/surveys', 'SurveyController', 'index');
$router->get('/surveys/create', 'SurveyController', 'create');
$router->post('/surveys/store', 'SurveyController', 'store');
$router->get('/surveys/edit', 'SurveyController', 'edit');
$router->get('/surveys/view', 'SurveyController', 'view');
$router->get('/surveys/my', 'SurveyController', 'my');

// === РОБОТА З ПИТАННЯМИ ===
$router->post('/surveys/add-question', 'SurveyController', 'addQuestion');
$router->post('/surveys/delete-question', 'SurveyController', 'deleteQuestion');

// === ЕКСПОРТ ДАНИХ ===
$router->get('/surveys/export-results', 'SurveyController', 'exportResults');

// === ОБРОБКА ВІДПОВІДЕЙ ===
$router->post('/surveys/submit', 'SurveyResponseController', 'submit');
$router->get('/surveys/response-details', 'SurveyResponseController', 'responseDetails');

// === РЕЗУЛЬТАТИ ТА СТАТИСТИКА ===
$router->get('/surveys/results', 'SurveyResultsController', 'results');

// === АВТОРИЗАЦІЯ ТА РЕЄСТРАЦІЯ ===
$router->get('/login', 'AuthController', 'showLogin');
$router->post('/login', 'AuthController', 'login');
$router->get('/register', 'AuthController', 'showRegister');
$router->post('/register', 'AuthController', 'register');
$router->get('/logout', 'AuthController', 'logout');

// === АДМІН-ПАНЕЛЬ ===
$router->get('/admin', 'AdminController', 'dashboard');
$router->get('/admin/dashboard', 'AdminController', 'dashboard');

// Управління користувачами
$router->get('/admin/users', 'AdminController', 'users');
$router->post('/admin/delete-user', 'AdminController', 'deleteUser');
$router->post('/admin/change-user-role', 'AdminController', 'changeUserRole');

// Управління опитуваннями
$router->get('/admin/surveys', 'AdminController', 'surveys');
$router->post('/admin/delete-survey', 'AdminController', 'deleteSurvey');
$router->post('/admin/toggle-survey-status', 'AdminController', 'toggleSurveyStatus');

// Статистика та експорт
$router->get('/admin/survey-stats', 'AdminController', 'surveyStats');
$router->get('/admin/export-stats', 'AdminController', 'exportStats');

// === API ЕНДПОІНТИ (для демонстрації) ===
$router->get('/api/surveys', 'SurveyController', 'apiIndex');
$router->get('/api/surveys/{id}', 'SurveyController', 'apiShow');
$router->post('/api/surveys', 'SurveyController', 'apiStore');

// === ТЕСТОВІ МАРШРУТИ ДЛЯ ДЕМОНСТРАЦІЇ ПОМИЛОК ===
$router->get('/test/404', 'SurveyController', 'handleError');
$router->get('/test/403', 'SurveyController', 'handleError');
$router->get('/test/500', 'SurveyController', 'handleError');
$router->get('/test/validation', 'SurveyController', 'handleError');

// === MIDDLEWARE ===
$router->addGlobalMiddleware(function() {
    // Логування запитів
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    error_log("Request: {$method} {$requestUri} from {$ip} - {$userAgent}");

    // Перевірка rate limiting (базова реалізація)
    $maxRequestsPerMinute = 60;
    $cacheKey = "rate_limit:" . $ip;

    // В реальному проєкті використовувати Redis або Memcached
    // Тут просто демонстрація концепції
});

// === ОБРОБКА ЗАПИТІВ ===
try {
    // Встановлюємо обробник помилок PHP
    set_error_handler(function($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    // Встановлюємо обробник винятків
    set_exception_handler(function($e) {
        error_log("Uncaught exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

        $responseManager = ResponseManager::getInstance();

        if ($e instanceof AppException) {
            $statusCode = $e->getHttpStatusCode();
            $userMessage = $e->getUserMessage();
        } else {
            $statusCode = 500;
            $userMessage = 'Виникла непередбачена помилка';
        }

        if ($statusCode >= 500) {
            $responseManager->sendServerError($statusCode, $responseManager->renderErrorPage($statusCode, $userMessage));
        } else {
            $responseManager->sendClientError($statusCode, $responseManager->renderErrorPage($statusCode, $userMessage));
        }
    });

    // Запускаємо роутер
    $router->dispatch();

} catch (AppException $e) {
    // Спеціальні винятки додатку
    $responseManager = ResponseManager::getInstance();
    $statusCode = $e->getHttpStatusCode();
    $userMessage = $e->getUserMessage();

    // Логуємо помилку
    error_log("App Exception [{$statusCode}]: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

    // Відправляємо відповідь з правильним статус кодом
    if ($statusCode >= 500) {
        $responseManager->sendServerError($statusCode, $responseManager->renderErrorPage($statusCode, $userMessage));
    } else {
        $responseManager->sendClientError($statusCode, $responseManager->renderErrorPage($statusCode, $userMessage));
    }

} catch (Throwable $e) {
    // Критичні помилки
    error_log("Critical Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());

    // Відправляємо загальну помилку сервера
    ResponseManager::serverError('Виникла критична помилка. Адміністратора повідомлено.');

} finally {
    // Очищення ресурсів
    if (ob_get_level()) {
        ob_end_flush();
    }

    // Логування завершення запиту
    $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    $memoryUsage = memory_get_peak_usage(true);

    error_log("Request completed in " . round($executionTime * 1000, 2) . "ms, memory: " .
        round($memoryUsage / 1024 / 1024, 2) . "MB");
}

// === ФУНКЦІЇ ДОПОМОГИ ===

/**
 * Логування дій користувача
 */
function logUserAction(string $action, array $data = []): void
{
    $userId = Session::getUserId() ?? 'anonymous';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');

    $logData = [
        'timestamp' => $timestamp,
        'user_id' => $userId,
        'ip' => $ip,
        'action' => $action,
        'data' => $data
    ];

    error_log("User Action: " . json_encode($logData, JSON_UNESCAPED_UNICODE));
}

/**
 * Перевірка обслуговування
 */
function checkMaintenance(): void
{
    $maintenanceFile = '../maintenance.txt';

    if (file_exists($maintenanceFile)) {
        $responseManager = ResponseManager::getInstance();
        $responseManager->sendServerError(
            ResponseManager::STATUS_SERVICE_UNAVAILABLE,
            $responseManager->renderMaintenancePage()
        );
        exit;
    }
}

/**
 * Рендер сторінки обслуговування
 */
function renderMaintenancePage(): string
{
    return "
    <!DOCTYPE html>
    <html lang='uk'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Технічні роботи</title>
        <link rel='stylesheet' href='./assets/css/style.css'>
        <style>
            .maintenance-page {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 20px;
            }
            .maintenance-content {
                background: white;
                border-radius: 15px;
                padding: 3rem;
                text-align: center;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                max-width: 500px;
                width: 100%;
            }
            .maintenance-icon {
                font-size: 4rem;
                color: #f39c12;
                margin-bottom: 1rem;
            }
            .maintenance-title {
                font-size: 2rem;
                color: #2c3e50;
                margin-bottom: 1rem;
            }
            .maintenance-message {
                color: #7f8c8d;
                margin-bottom: 2rem;
                line-height: 1.6;
            }
        </style>
    </head>
    <body>
        <div class='maintenance-page'>
            <div class='maintenance-content'>
                <div class='maintenance-icon'>🔧</div>
                <h1 class='maintenance-title'>Технічні роботи</h1>
                <p class='maintenance-message'>
                    Наразі проводяться планові технічні роботи для покращення сервісу.
                    Спробуйте пізніше. Дякуємо за розуміння!
                </p>
            </div>
        </div>
    </body>
    </html>";
}