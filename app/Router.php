<?php

/**
 * Клас Router для обробки маршрутів
 */
class Router
{
    private array $routes = []; //Масив для зберігання зареєстрованих маршрутів

    private string $requestMethod; //Поточний HTTP метод запиту

    private string $requestUri; //Поточний URI запиту

    public function __construct()
    {
        $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->requestUri = $this->parseUri($_SERVER['REQUEST_URI'] ?? '/');
    }

    public function get(string $path, string $controller, string $method): void
    {
        $this->addRoute('GET', $path, $controller, $method);
    }

    public function post(string $path, string $controller, string $method): void
    {
        $this->addRoute('POST', $path, $controller, $method);
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

    public function dispatch(): void
    {
        $routeKey = $this->requestMethod . ':' . $this->requestUri;

        if (!isset($this->routes[$routeKey])) {
            $this->handleNotFound();
            return;
        }

        $route = $this->routes[$routeKey];
        $this->executeController($route['controller'], $route['method']);
    }

    private function executeController(string $controllerName, string $methodName): void
    {
        // перевірка чи існує клас контролера
        if (!class_exists($controllerName)) {
            throw new Exception("Controller {$controllerName} not found");
        }

        $controller = new $controllerName();

        // перевірка чи існує метод у контролері
        if (!method_exists($controller, $methodName)) {
            throw new Exception("Method {$methodName} not found in {$controllerName}");
        }

        $controller->$methodName();
    }


    private function handleNotFound(): void
    {
        http_response_code(404);
        echo "404 - Page Not Found";
    }

    /*
     * Приводить URI до уніфікованої форми
     */
    private function parseUri(string $uri): string
    {
        $uri = strtok($uri, '?');

        if ($uri !== '/' && !str_starts_with($uri, '/')) {
            $uri = '/' . $uri;
        }

        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        return $uri;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function hasRoute(string $httpMethod, string $path): bool
    {
        $routeKey = $httpMethod . ':' . $path;
        return isset($this->routes[$routeKey]);
    }
}