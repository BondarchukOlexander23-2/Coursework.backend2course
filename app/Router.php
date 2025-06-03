<?php

/**
 * Оновлений клас Router з підтримкою ResponseManager та буферизації
 * Відповідає принципам SOLID
 */
class Router
{
    private array $routes = [];
    private string $requestMethod;
    private string $requestUri;
    private ResponseManager $responseManager;

    public function __construct()
    {
        $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->requestUri = $this->parseUri($_SERVER['REQUEST_URI'] ?? '/');
        $this->responseManager = ResponseManager::getInstance();

        // Встановлюємо базові заголовки безпеки
        $this->responseManager->setSecurityHeaders();
    }

    public function get(string $path, string $controller, string $method): void
    {
        $this->addRoute('GET', $path, $controller, $method);
    }

    public function post(string $path, string $controller, string $method): void
    {
        $this->addRoute('POST', $path, $controller, $method);
    }

    public function put(string $path, string $controller, string $method): void
    {
        $this->addRoute('PUT', $path, $controller, $method);
    }

    public function delete(string $path, string $controller, string $method): void
    {
        $this->addRoute('DELETE', $path, $controller, $method);
    }

    /**
     * Додає маршрут до колекції маршрутів
     */
    private function addRoute(string $httpMethod, string $path, string $controller, string $method): void
    {
        $routeKey = $httpMethod . ':' . $path;
        $this->routes[$routeKey] = [
            'controller' => $controller,
            'method' => $method,
            'path' => $path,
            'httpMethod' => $httpMethod
        ];
    }

    /**
     * Обробити запит з підтримкою буферизації
     */
    public function dispatch(): void
    {
        try {
            // Перевіряємо метод запиту
            if (!$this->isValidHttpMethod()) {
                $this->handleMethodNotAllowed();
                return;
            }

            $routeKey = $this->requestMethod . ':' . $this->requestUri;

            if (!isset($this->routes[$routeKey])) {
                $this->handleNotFound();
                return;
            }

            $route = $this->routes[$routeKey];

            // Виконуємо контролер з обробкою винятків
            $this->executeControllerSafely($route['controller'], $route['method']);

        } catch (AppException $e) {
            $this->handleAppException($e);
        } catch (Throwable $e) {
            $this->handleUnexpectedException($e);
        }
    }

    /**
     * Безпечне виконання контролера
     */
    private function executeControllerSafely(string $controllerName, string $methodName): void
    {
        // Перевірка чи існує клас контролера
        if (!class_exists($controllerName)) {
            throw new NotFoundException("Controller {$controllerName} not found");
        }

        $controller = new $controllerName();

        // Перевірка чи існує метод у контролері
        if (!method_exists($controller, $methodName)) {
            throw new NotFoundException("Method {$methodName} not found in {$controllerName}");
        }

        // Викликаємо метод контролера
        $controller->$methodName();
    }

    /**
     * Обробка винятків додатку
     */
    private function handleAppException(AppException $e): void
    {
        $statusCode = $e->getHttpStatusCode();
        $userMessage = $e->getUserMessage();

        // Логуємо помилку
        error_log("App Exception [{$statusCode}]: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

        if ($e instanceof ValidationException) {
            $this->handleValidationException($e);
        } else {
            $content = $this->renderErrorPage($statusCode, $userMessage);

            if ($statusCode >= 500) {
                $this->responseManager->sendServerError($statusCode, $content);
            } else {
                $this->responseManager->sendClientError($statusCode, $content);
            }
        }
    }

    /**
     * Обробка винятків валідації
     */
    private function handleValidationException(ValidationException $e): void
    {
        if ($this->isAjaxRequest()) {
            $this->responseManager->sendJson([
                'success' => false,
                'errors' => $e->getErrors(),
                'message' => $e->getUserMessage()
            ], ResponseManager::STATUS_UNPROCESSABLE_ENTITY);
        } else {
            $content = $this->renderValidationErrorPage($e->getErrors());
            $this->responseManager->sendClientError(ResponseManager::STATUS_UNPROCESSABLE_ENTITY, $content);
        }
    }

    /**
     * Обробка непередбачених винятків
     */
    private function handleUnexpectedException(Throwable $e): void
    {
        // Логуємо критичну помилку
        error_log("Critical Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        error_log("Stack trace: " . $e->getTraceAsString());

        if ($this->isAjaxRequest()) {
            $this->responseManager->sendJson([
                'success' => false,
                'message' => 'Виникла непередбачена помилка'
            ], ResponseManager::STATUS_INTERNAL_SERVER_ERROR);
        } else {
            $content = $this->renderErrorPage(500, 'Виникла непередбачена помилка. Спробуйте пізніше.');
            $this->responseManager->sendServerError(ResponseManager::STATUS_INTERNAL_SERVER_ERROR, $content);
        }
    }

    /**
     * Обробка 404 помилки
     */
    private function handleNotFound(): void
    {
        $message = "Сторінка '{$this->requestUri}' не знайдена";

        if ($this->isAjaxRequest()) {
            $this->responseManager->sendJson([
                'success' => false,
                'message' => $message
            ], ResponseManager::STATUS_NOT_FOUND);
        } else {
            ResponseManager::notFound($message);
        }
    }

    /**
     * Обробка неприпустимого HTTP методу
     */
    private function handleMethodNotAllowed(): void
    {
        $allowedMethods = $this->getAllowedMethodsForPath($this->requestUri);

        $this->responseManager
            ->addHeader('Allow', implode(', ', $allowedMethods))
            ->sendClientError(
                ResponseManager::STATUS_METHOD_NOT_ALLOWED,
                $this->renderErrorPage(405, "Метод {$this->requestMethod} не дозволений для цієї сторінки")
            );
    }

    /**
     * Отримати дозволені методи для шляху
     */
    private function getAllowedMethodsForPath(string $path): array
    {
        $methods = [];

        foreach ($this->routes as $routeKey => $route) {
            if ($route['path'] === $path) {
                $methods[] = $route['httpMethod'];
            }
        }

        return array_unique($methods);
    }

    /**
     * Перевірити чи HTTP метод валідний
     */
    private function isValidHttpMethod(): bool
    {
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
        return in_array($this->requestMethod, $validMethods);
    }

    /**
     * Перевірити чи запит є AJAX
     */
    private function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Рендер сторінки помилки
     */
    private function renderErrorPage(int $statusCode, string $message): string
    {
        $statusTexts = [
            400 => 'Поганий запит',
            401 => 'Не авторизовано',
            403 => 'Доступ заборонено',
            404 => 'Не знайдено',
            405 => 'Метод не дозволений',
            422 => 'Помилка валідації',
            500 => 'Помилка сервера',
            503 => 'Сервіс недоступний'
        ];

        $statusText = $statusTexts[$statusCode] ?? 'Помилка';

        return "
        <!DOCTYPE html>
        <html lang='uk'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$statusCode} - {$statusText}</title>
            <link rel='stylesheet' href='/assets/css/style.css'>
            <style>
                .error-page {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    padding: 20px;
                }
                .error-content {
                    background: white;
                    border-radius: 15px;
                    padding: 3rem;
                    text-align: center;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                    max-width: 500px;
                    width: 100%;
                    animation: slideInUp 0.5s ease-out;
                }
                @keyframes slideInUp {
                    from {
                        opacity: 0;
                        transform: translateY(30px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                .error-code {
                    font-size: 6rem;
                    font-weight: bold;
                    color: #e74c3c;
                    margin-bottom: 1rem;
                    line-height: 1;
                }
                .error-title {
                    font-size: 2rem;
                    color: #2c3e50;
                    margin-bottom: 1rem;
                }
                .error-message {
                    color: #7f8c8d;
                    margin-bottom: 2rem;
                    line-height: 1.6;
                }
                .error-actions {
                    display: flex;
                    gap: 1rem;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                .error-details {
                    background: #f8f9fa;
                    padding: 1rem;
                    border-radius: 8px;
                    margin-top: 1rem;
                    font-size: 0.9rem;
                    color: #6c757d;
                }
            </style>
        </head>
        <body>
            <div class='error-page'>
                <div class='error-content'>
                    <div class='error-code'>{$statusCode}</div>
                    <h1 class='error-title'>{$statusText}</h1>
                    <p class='error-message'>" . htmlspecialchars($message) . "</p>
                    <div class='error-actions'>
                        <a href='/' class='btn btn-primary'>На головну</a>
                        <a href='javascript:history.back()' class='btn btn-secondary'>Назад</a>
                    </div>
                    " . ($statusCode >= 500 ? "
                    <div class='error-details'>
                        Якщо проблема повторюється, будь ласка, зверніться до підтримки.
                    </div>" : "") . "
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Рендер сторінки помилок валідації
     */
    private function renderValidationErrorPage(array $errors): string
    {
        $errorList = '';
        foreach ($errors as $error) {
            $errorList .= '<li>' . htmlspecialchars($error) . '</li>';
        }

        return "
        <!DOCTYPE html>
        <html lang='uk'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Помилка валідації</title>
            <link rel='stylesheet' href='/assets/css/style.css'>
        </head>
        <body>
            <div class='container'>
                <div class='validation-errors'>
                    <h1>Помилки валідації</h1>
                    <div class='error-message'>
                        <ul>{$errorList}</ul>
                    </div>
                    <div class='form-actions'>
                        <a href='javascript:history.back()' class='btn btn-secondary'>Назад</a>
                        <a href='/' class='btn btn-primary'>На головну</a>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Приводить URI до уніфікованої форми
     */
    private function parseUri(string $uri): string
    {
        // Видаляємо GET параметри
        $uri = strtok($uri, '?');

        // Додаємо слеш на початок якщо немає
        if ($uri !== '/' && !str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }

        // Видаляємо слеш в кінці (крім кореневого)
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        return $uri;
    }

    /**
     * Отримати всі маршрути
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Перевірити чи існує маршрут
     */
    public function hasRoute(string $httpMethod, string $path): bool
    {
        $routeKey = $httpMethod . ':' . $path;
        return isset($this->routes[$routeKey]);
    }

    /**
     * Додати middleware для всіх маршрутів
     */
    public function addGlobalMiddleware(callable $middleware): void
    {
        // Викликаємо middleware перед обробкою маршруту
        $middleware();
    }

    /**
     * Обробити CORS заголовки
     */
    public function handleCors(): void
    {
        // Додаємо CORS заголовки для API
        if (strpos($this->requestUri, '/api/') === 0) {
            $this->responseManager
                ->addHeader('Access-Control-Allow-Origin', '*')
                ->addHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->addHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

            // Обробляємо preflight OPTIONS запити
            if ($this->requestMethod === 'OPTIONS') {
                $this->responseManager
                    ->setStatusCode(ResponseManager::STATUS_NO_CONTENT)
                    ->sendResponse('');
                exit;
            }
        }
    }
}