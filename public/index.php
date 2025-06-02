<?php

require_once '../app/Database/Database.php';
require_once '../app/Models/User.php';
require_once '../app/Models/Survey.php';
require_once '../app/Helpers/Session.php';
require_once '../app/Router.php';
require_once '../app/Controllers/HomeController.php';
require_once '../app/Controllers/SurveyController.php';
require_once '../app/Controllers/AuthController.php';

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

// Сторінки опитувань
$router->get('/surveys', 'SurveyController', 'index');
$router->get('/surveys/create', 'SurveyController', 'create');
$router->post('/surveys/store', 'SurveyController', 'store');
$router->get('/surveys/view', 'SurveyController', 'view');
$router->post('/surveys/submit', 'SurveyController', 'submit');
$router->get('/surveys/results', 'SurveyController', 'results');
$router->get('/surveys/my', 'SurveyController', 'my');

// Авторизація та реєстрація
$router->get('/login', 'AuthController', 'showLogin');
$router->post('/login', 'AuthController', 'login');
$router->get('/register', 'AuthController', 'showRegister');
$router->post('/register', 'AuthController', 'register');
$router->get('/logout', 'AuthController', 'logout');

try {
    $router->dispatch();
} catch (Exception $e) {
    http_response_code(500);
    echo "Server Error: " . $e->getMessage();
    error_log("Router Error: " . $e->getMessage());
}