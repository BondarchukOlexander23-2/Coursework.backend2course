<?php

require_once '../app/Router.php';
require_once '../app/Controllers/HomeController.php';
require_once '../app/Controllers/SurveyController.php';
require_once '../app/Controllers/AuthController.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Авторизація та реєстрація
$router->get('/login', 'AuthController', 'showLogin');
$router->post('/login', 'AuthController', 'login');
$router->get('/register', 'AuthController', 'showRegister');
$router->post('/register', 'AuthController', 'register');

try {
    $router->dispatch();
} catch (Exception $e) {
    http_response_code(500);
    echo "Server Error: " . $e->getMessage();

    error_log("Router Error: " . $e->getMessage());
}