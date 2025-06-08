<?php

/**
 * Базовий контролер з підтримкою ResponseManager
 */
abstract class BaseController
{
    protected ResponseManager $responseManager;

    public function __construct()
    {
        $this->responseManager = ResponseManager::getInstance();
    }

    /**
     * Відправити редирект
     */
    protected function redirect(string $location, bool $permanent = false): void
    {
        $this->responseManager->sendRedirect($location, $permanent);
    }

    /**
     * Відправити редирект з повідомленням
     */
    protected function redirectWithMessage(string $location, string $type, string $message): void
    {
        Session::setFlashMessage($type, $message);
        $this->redirect($location);
    }

    /**
     * Перевірити права доступу
     */
    protected function requireAuth(): void
    {
        if (!Session::isLoggedIn()) {
            $this->redirect('/login');
        }
    }

    /**
     * Перевірити права адміністратора
     */
    protected function requireAdmin(): void
    {
        $this->requireAuth();

        if (!$this->isAdmin()) {
            ResponseManager::forbidden('Доступ заборонено. Тільки для адміністраторів.');
        }
    }

    /**
     * Перевірити чи користувач є адміном
     */
    protected function isAdmin(): bool
    {
        $userId = Session::getUserId();
        if (!$userId) {
            return false;
        }

        $user = User::findById($userId);
        return $user && $user['role'] === 'admin';
    }


    /**
     * Безпечно отримати параметр з $_GET
     */
    protected function getParam(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Безпечно отримати параметр з $_POST
     */
    protected function postParam(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Отримати int параметр з валідацією
     */
    protected function getIntParam(string $key, int $default = 0): int
    {
        $value = $this->getParam($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }

    /**
     * Отримати string параметр з обрізанням пробілів
     */
    protected function getStringParam(string $key, string $default = ''): string
    {
        return trim($this->getParam($key, $default));
    }

    protected function postIntParam(string $key, int $default = 0): int
    {
        $value = $this->postParam($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }
    /**
     * Try-catch wrapper для методів контролера
     */
    protected function safeExecute(callable $callback): void
    {
        try {
            $callback();
        } catch (Exception $e) {
            error_log("Controller Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

            if ($e instanceof ValidationException) {
                if ($this->isAjaxRequest()) {
                    $this->responseManager->sendJson([
                        'success' => false,
                        'errors' => $e->getErrors(),
                        'message' => $e->getUserMessage()
                    ], ResponseManager::STATUS_UNPROCESSABLE_ENTITY);
                } else {
                    $content = $this->responseManager->renderErrorPage(422, $e->getUserMessage());
                    $this->responseManager->sendClientError(ResponseManager::STATUS_UNPROCESSABLE_ENTITY, $content);
                }
            } elseif ($e instanceof ForbiddenException) {
                ResponseManager::forbidden($e->getMessage());
            } elseif ($e instanceof NotFoundException) {
                ResponseManager::notFound($e->getMessage());
            } elseif ($e instanceof DatabaseException) {
                ResponseManager::serverError($e->getMessage());
            } else {
                ResponseManager::serverError('Виникла непередбачена помилка');
            }
        }
    }

    /**
     * Відправити відповідь на AJAX запит
     */
    protected function sendAjaxResponse(bool $success, $data = null, string $message = ''): void
    {
        $response = [
            'success' => $success,
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        $statusCode = $success ? ResponseManager::STATUS_OK : ResponseManager::STATUS_BAD_REQUEST;
        $this->responseManager->sendJson($response, $statusCode);
    }

    /**
     * Перевірити чи запит є AJAX
     */
    protected function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}