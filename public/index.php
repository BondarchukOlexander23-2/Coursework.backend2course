
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

// Завантажуємо базовий клас View та компоненти
require_once '../app/Views/BaseView.php';
require_once '../app/Views/Layouts/AppLayout.php';
require_once '../app/Views/Layouts/AdminLayout.php';
require_once '../app/Views/Components/NavigationComponent.php';
require_once '../app/Views/Components/AdminNavigationComponent.php';
require_once '../app/Views/Components/PaginationComponent.php';
require_once '../app/Views/Components/FlashMessageComponent.php';

// Завантажуємо оновлені контролери (з Views)
require_once '../app/Controllers/HomeController.php';
require_once '../app/Controllers/AuthController.php';
require_once '../app/Controllers/Survey/SurveyController.php';
require_once '../app/Controllers/AdminController.php';
require_once '../app/Controllers/Survey/SurveyResponseController.php';
require_once '../app/Controllers/Survey/SurveyResultsController.php';

require_once '../app/Controllers/Survey/SurveyRetakeController.php';
require_once '../app/Services/RetakeService.php';
require_once '../app/Services/RetakeValidator.php';

// Встановлюємо часовий пояс
date_default_timezone_set('Europe/Kyiv');

// Автозавантажувач для Views
spl_autoload_register(function ($className) {
    // Автозавантаження Views
    if (strpos($className, 'View') !== false) {
        $viewPaths = [
            '../app/Views/',
            '../app/Views/Home/',
            '../app/Views/Auth/',
            '../app/Views/Survey/',
            '../app/Views/Admin/',
            '../app/Views/Components/',
            '../app/Views/Layouts/',
            '../app/Views/Survey/Retake/'
        ];

        foreach ($viewPaths as $path) {
            $file = $path . $className . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

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

// === ГОЛОВНА СТОРІНКА (оновлений контролер) ===
$router->get('/', 'HomeController', 'index');


// === УПРАВЛІННЯ ПОВТОРНИМИ СПРОБАМИ ===
$router->get('/surveys/retake-management', 'SurveyRetakeController', 'managementPage');
$router->post('/surveys/retake/grant', 'SurveyRetakeController', 'grantRetake');
$router->post('/surveys/retake/grant-bulk', 'SurveyRetakeController', 'grantBulkRetakes');
$router->post('/surveys/retake/revoke', 'SurveyRetakeController', 'revokeRetake');
$router->get('/surveys/retake/user-attempts', 'SurveyRetakeController', 'userAttempts');

// === API ДЛЯ СТАТИСТИКИ ПОВТОРНИХ СПРОБ ===
$router->get('/api/surveys/retake-stats', 'SurveyRetakeController', 'apiRetakeStats');


// === ОСНОВНІ СТОРІНКИ ОПИТУВАНЬ (оновлені контролери) ===
$router->get('/surveys', 'SurveyController', 'index');
$router->get('/surveys/create', 'SurveyController', 'create');
$router->post('/surveys/store', 'SurveyController', 'store');
$router->get('/surveys/edit', 'SurveyController', 'edit');
$router->get('/surveys/my', 'SurveyController', 'my');

// === РОБОТА З ПИТАННЯМИ ===
$router->post('/surveys/add-question', 'SurveyController', 'addQuestion');
$router->post('/surveys/delete-question', 'SurveyController', 'deleteQuestion'); // Поки старий

// === ЕКСПОРТ ДАНИХ ===
$router->get('/surveys/export-results', 'SurveyController', 'exportResults');

// === ПЕРЕГЛЯД ТА ПРОХОДЖЕННЯ ОПИТУВАНЬ (поки старі контролери) ===
$router->get('/surveys/view', 'SurveyController', 'view');

// === ОБРОБКА ВІДПОВІДЕЙ ===
$router->post('/surveys/submit', 'SurveyResponseController', 'submit');
$router->get('/surveys/response-details', 'SurveyResponseController', 'responseDetails');

// === РЕЗУЛЬТАТИ ТА СТАТИСТИКА ===
$router->get('/surveys/results', 'SurveyResultsController', 'results');


// === АВТОРИЗАЦІЯ ТА РЕЄСТРАЦІЯ (оновлені контролери) ===
$router->get('/login', 'AuthController', 'showLogin');
$router->post('/login', 'AuthController', 'login');
$router->get('/register', 'AuthController', 'showRegister');
$router->post('/register', 'AuthController', 'register');
$router->get('/logout', 'AuthController', 'logout');

// === АДМІН-ПАНЕЛЬ (оновлені контролери) ===
$router->get('/admin', 'AdminController', 'dashboard');
$router->get('/admin/dashboard', 'AdminController', 'dashboard');

// Управління користувачами
$router->get('/admin/users', 'AdminController', 'users');
$router->post('/admin/delete-user', 'AdminController', 'deleteUser'); // Поки старий
$router->post('/admin/change-user-role', 'AdminController', 'changeUserRole'); // Поки старий

// Управління опитуваннями
$router->get('/admin/surveys', 'AdminController', 'surveys');
$router->post('/admin/delete-survey', 'AdminController', 'deleteSurvey');
$router->post('/admin/toggle-survey-status', 'AdminController', 'toggleSurveyStatus');

// Статистика та експорт
$router->get('/admin/survey-stats', 'AdminController', 'surveyStats');
$router->get('/admin/export-stats', 'AdminController', 'exportStats'); // Поки старий

// === API ЕНДПОІНТИ (для демонстрації) ===
$router->get('/api/surveys', 'SurveyController', 'apiIndex');
$router->get('/api/surveys/{id}', 'SurveyController', 'apiShow');
$router->post('/api/surveys', 'SurveyController', 'apiStore');

// === MIDDLEWARE ===
$router->addGlobalMiddleware(function() {
    // Логування запитів
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    error_log("Request: {$method} {$requestUri} from {$ip} - {$userAgent}");
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
            renderMaintenancePage()
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
    </head>
    <body>
        <div class='maintenance-page'>
            <div class='maintenance-content'>
                <div class='maintenance-icon'>🔧</div>
                <h1 class='maintenance-title'>Технічні шоколадки</h1>
                <p class='maintenance-message'>
                    Наразі проводяться планові технічні роботи для покращення сервісу.
                    Спробуйте пізніше. Дякуємо за розуміння!
                </p>
            </div>
        </div>
    </body>
    </html>";
}