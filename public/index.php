<?php

require_once '../app/Database/Database.php';
require_once '../app/Models/User.php';
require_once '../app/Models/Survey.php';
require_once '../app/Models/Question.php';
require_once '../app/Models/QuestionOption.php';
require_once '../app/Models/SurveyResponse.php';
require_once '../app/Models/QuestionAnswer.php';
require_once '../app/Helpers/Session.php';
require_once '../app/Router.php';

// Завантажуємо тільки необхідні сервіси
require_once '../app/Services/SurveyValidator.php';
require_once '../app/Services/QuestionService.php';

// Завантажуємо контролери (з вашої папки Survey)
require_once '../app/Controllers/HomeController.php';
require_once '../app/Controllers/AuthController.php';
require_once '../app/Controllers/Survey/SurveyController.php';
require_once '../app/Controllers/Survey/SurveyResponseController.php';
require_once '../app/Controllers/Survey/SurveyResultsController.php';

// Включення виведення помилок для розробки
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Перевірка підключення до бази даних
if (!Database::testConnection()) {
    die('Не вдалося підключитися до бази даних. Перевірте налаштування.');
}

$router = new Router();

// Головна сторінка
$router->get('/', 'HomeController', 'index');

// === ОСНОВНІ СТОРІНКИ ОПИТУВАНЬ ===
$router->get('/surveys', 'SurveyController', 'index');
$router->get('/surveys/create', 'SurveyController', 'create');
$router->post('/surveys/store', 'SurveyController', 'store');
$router->get('/surveys/edit', 'SurveyController', 'edit');
$router->get('/surveys/view', 'SurveyController', 'view');
$router->get('/surveys/my', 'SurveyController', 'my');

// === РОБОТА З ПИТАННЯМИ (залишаємо в SurveyController) ===
$router->post('/surveys/add-question', 'SurveyController', 'addQuestion');
$router->post('/surveys/delete-question', 'SurveyController', 'deleteQuestion');

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

try {
    $router->dispatch();
} catch (Exception $e) {
    // Логування помилки
    error_log("Router Error: " . $e->getMessage());

    // Показуємо користувачеві зрозумілу помилку
    http_response_code(500);
    echo "
    <!DOCTYPE html>
    <html lang='uk'>
    <head>
        <meta charset='UTF-8'>
        <title>Помилка сервера</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; }
            .details { background: #f8f9fa; padding: 15px; margin-top: 20px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='error'>
            <h1>Помилка сервера</h1>
            <p>Виникла непередбачена помилка. Спробуйте пізніше або зверніться до адміністратора.</p>
        </div>
        <div class='details'>
            <strong>Деталі помилки:</strong> " . htmlspecialchars($e->getMessage()) . "
        </div>
        <p><a href='/'>← Повернутися на головну</a></p>
    </body>
    </html>";
}