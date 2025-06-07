<?php


require_once '../app/Database/Database.php';
require_once '../app/Models/User.php';
require_once '../app/Models/Survey.php';
require_once '../app/Models/Question.php';
require_once '../app/Models/QuestionOption.php';
require_once '../app/Models/SurveyResponse.php';
require_once '../app/Models/QuestionAnswer.php';
require_once '../app/Helpers/Session.php';



require_once '../app/Services/ResponseManager.php';
require_once '../app/Exceptions/CustomExceptions.php';
require_once '../app/Controllers/BaseController.php';
require_once '../app/Router.php';



require_once '../app/Services/SurveyValidator.php';
require_once '../app/Services/QuestionService.php';
require_once '../app/Services/AdminValidator.php';
require_once '../app/Services/AdminService.php';



require_once '../app/Views/BaseView.php';
require_once '../app/Views/Layouts/AppLayout.php';
require_once '../app/Views/Layouts/AdminLayout.php';
require_once '../app/Views/Components/NavigationComponent.php';
require_once '../app/Views/Components/AdminNavigationComponent.php';
require_once '../app/Views/Components/PaginationComponent.php';
require_once '../app/Views/Components/FlashMessageComponent.php';



require_once '../app/Controllers/HomeController.php';
require_once '../app/Controllers/AuthController.php';
require_once '../app/Controllers/Survey/SurveyController.php';

require_once '../app/Controllers/Admin/AdminDashboardController.php';
require_once '../app/Controllers/Admin/AdminUserController.php';
require_once '../app/Controllers/Admin/AdminSurveyController.php';

require_once '../app/Controllers/Survey/SurveyResponseController.php';
require_once '../app/Controllers/Survey/SurveyResultsController.php';


require_once '../app/Controllers/Survey/SurveyRetakeController.php';
require_once '../app/Services/RetakeService.php';
require_once '../app/Services/RetakeValidator.php';

require_once '../app/Models/Category.php';
require_once '../app/Controllers/Admin/AdminCategoriesController.php';

date_default_timezone_set('Europe/Kyiv');



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



try {
    if (!Database::testConnection()) {
        throw new DatabaseException('Не вдалося підключитися до бази даних');
    }
} catch (Exception $e) {
    ResponseManager::serverError('Сервіс тимчасово недоступний. Спробуйте пізніше.');
    exit;
}


$router = new Router();


$router->handleCors();


$router->get('/', 'HomeController', 'index');


$router->get('/surveys/retake-management', 'SurveyRetakeController', 'managementPage');
$router->post('/surveys/retake/grant', 'SurveyRetakeController', 'grantRetake');
$router->post('/surveys/retake/grant-bulk', 'SurveyRetakeController', 'grantBulkRetakes');
$router->post('/surveys/retake/revoke', 'SurveyRetakeController', 'revokeRetake');
$router->get('/surveys/retake/user-attempts', 'SurveyRetakeController', 'userAttempts');


$router->get('/api/surveys/retake-stats', 'SurveyRetakeController', 'apiRetakeStats');


$router->get('/surveys', 'SurveyController', 'index');
$router->get('/surveys/create', 'SurveyController', 'create');
$router->post('/surveys/store', 'SurveyController', 'store');
$router->get('/surveys/edit', 'SurveyController', 'edit');
$router->get('/surveys/my', 'SurveyController', 'my');


$router->post('/surveys/add-question', 'SurveyController', 'addQuestion');
$router->post('/surveys/delete-question', 'SurveyController', 'deleteQuestion');


$router->get('/surveys/export-results', 'SurveyController', 'exportResults');


$router->get('/surveys/view', 'SurveyController', 'view');


$router->post('/surveys/submit', 'SurveyResponseController', 'submit');
$router->get('/surveys/response-details', 'SurveyResponseController', 'responseDetails');


$router->get('/surveys/results', 'SurveyResultsController', 'results');



$router->get('/login', 'AuthController', 'showLogin');
$router->post('/login', 'AuthController', 'login');
$router->get('/register', 'AuthController', 'showRegister');
$router->post('/register', 'AuthController', 'register');
$router->get('/logout', 'AuthController', 'logout');

// Дашборд адмін-панелі
$router->get('/admin', 'AdminDashboardController', 'dashboard');
$router->get('/admin/dashboard', 'AdminDashboardController', 'dashboard');

// Управління користувачами
$router->get('/admin/users', 'AdminUserController', 'users');
$router->post('/admin/delete-user', 'AdminUserController', 'deleteUser');
$router->post('/admin/change-user-role', 'AdminUserController', 'changeUserRole');

// Управління опитуваннями
$router->get('/admin/surveys', 'AdminSurveyController', 'surveys');
$router->get('/admin/edit-survey', 'AdminSurveyController', 'editSurvey');
$router->post('/admin/update-survey', 'AdminSurveyController', 'updateSurvey');
$router->post('/admin/delete-survey', 'AdminSurveyController', 'deleteSurvey');
$router->post('/admin/toggle-survey-status', 'AdminSurveyController', 'toggleSurveyStatus');

// Статистика і експорт
$router->get('/admin/survey-stats', 'AdminSurveyController', 'surveyStats');
$router->get('/admin/export-stats', 'AdminSurveyController', 'exportStats');


$router->get('/api/surveys', 'SurveyController', 'apiIndex');
$router->get('/api/surveys/{id}', 'SurveyController', 'apiShow');
$router->post('/api/surveys', 'SurveyController', 'apiStore');

// Управління категоріями (тільки адміни)
$router->get('/admin/categories', 'AdminCategoriesController', 'categories');
$router->post('/admin/create-category', 'AdminCategoriesController', 'createCategory');
$router->post('/admin/update-category', 'AdminCategoriesController', 'updateCategory');
$router->post('/admin/toggle-category-status', 'AdminCategoriesController', 'toggleCategoryStatus');
$router->post('/admin/delete-category', 'AdminCategoriesController', 'deleteCategory');

// Оновлення категорії опитування
$router->post('/surveys/update-category', 'SurveyController', 'updateCategory');

// API для категорій
$router->get('/api/categories', 'AdminCategoriesController', 'apiCategories');

$router->addGlobalMiddleware(function() {
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    error_log("Request: {$method} {$requestUri} from {$ip} - {$userAgent}");
});


try {
    set_error_handler(function($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });

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

    $router->dispatch();

} catch (AppException $e) {
    $responseManager = ResponseManager::getInstance();
    $statusCode = $e->getHttpStatusCode();
    $userMessage = $e->getUserMessage();

    error_log("App Exception [{$statusCode}]: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());


    if ($statusCode >= 500) {
        $responseManager->sendServerError($statusCode, $responseManager->renderErrorPage($statusCode, $userMessage));
    } else {
        $responseManager->sendClientError($statusCode, $responseManager->renderErrorPage($statusCode, $userMessage));
    }

} catch (Throwable $e) {
    error_log("Critical Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());

    ResponseManager::serverError('Виникла критична помилка. Адміністратора повідомлено.');

} finally {
    if (ob_get_level()) {
        ob_end_flush();
    }

    $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    $memoryUsage = memory_get_peak_usage(true);

    error_log("Request completed in " . round($executionTime * 1000, 2) . "ms, memory: " .
        round($memoryUsage / 1024 / 1024, 2) . "MB");
}


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