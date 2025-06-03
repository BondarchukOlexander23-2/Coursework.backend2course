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
     * Відправити успішну HTML сторінку
     */
    protected function renderPage(string $title, string $content, bool $enableCaching = false): void
    {
        $htmlContent = $this->buildHtmlPage($title, $content);

        if ($enableCaching) {
            $this->responseManager->sendCachedPage($htmlContent, 3600);
        } else {
            $this->responseManager->sendSuccess($htmlContent);
        }
    }

    /**
     * Відправити сторінку з користувацькими заголовками
     */
    protected function renderPageWithHeaders(string $title, string $content, array $headers = []): void
    {
        foreach ($headers as $name => $value) {
            $this->responseManager->addHeader($name, $value);
        }

        $htmlContent = $this->buildHtmlPage($title, $content);
        $this->responseManager->sendSuccess($htmlContent);
    }

    /**
     * Відправити JSON відповідь
     */
    protected function sendJson(array $data, int $statusCode = ResponseManager::STATUS_OK): void
    {
        $this->responseManager->sendJson($data, $statusCode);
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
     * Відправити помилку 404
     */
    protected function notFound(string $message = "Сторінка не знайдена"): void
    {
        ResponseManager::notFound($message);
    }

    /**
     * Відправити помилку 403
     */
    protected function forbidden(string $message = "Доступ заборонено"): void
    {
        ResponseManager::forbidden($message);
    }

    /**
     * Відправити помилку валідації
     */
    protected function validationError(array $errors): void
    {
        $errorContent = $this->buildValidationErrorPage($errors);
        $this->responseManager->sendClientError(
            ResponseManager::STATUS_UNPROCESSABLE_ENTITY,
            $errorContent
        );
    }

    /**
     * Відправити серверну помилку
     */
    protected function serverError(string $message = "Внутрішня помилка сервера"): void
    {
        ResponseManager::serverError($message);
    }

    /**
     * Завантажити файл
     */
    protected function downloadFile(string $content, string $filename, string $mimeType = 'application/octet-stream'): void
    {
        $this->responseManager->sendDownload($content, $filename, $mimeType);
    }

    /**
     * Завантажити CSV
     */
    protected function downloadCsv(string $content, string $filename): void
    {
        $this->responseManager->sendCsv($content, $filename);
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
            $this->forbidden('Доступ заборонено. Тільки для адміністраторів.');
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
     * Валідувати дані та відправити помилки якщо є
     */
    protected function validateOrFail(array $data, callable $validator): array
    {
        $errors = $validator($data);

        if (!empty($errors)) {
            $this->validationError($errors);
            exit;
        }

        return $data;
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

    /**
     * Побудувати HTML сторінку
     */
    protected function buildHtmlPage(string $title, string $content): string
    {
        $flashSuccess = Session::getFlashMessage('success');
        $flashError = Session::getFlashMessage('error');

        $flashHtml = '';
        if ($flashSuccess) {
            $flashHtml .= "<div class='flash-message success'>{$flashSuccess}</div>";
        }
        if ($flashError) {
            $flashHtml .= "<div class='flash-message error'>{$flashError}</div>";
        }

        return "
        <!DOCTYPE html>
        <html lang='uk'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            <link rel='stylesheet' href='/assets/css/style.css'>
        </head>
        <body>
            <div class='container'>
                {$flashHtml}
                {$content}
            </div>
        </body>
        </html>";
    }

    /**
     * Побудувати сторінку помилки валідації
     */
    protected function buildValidationErrorPage(array $errors): string
    {
        $errorList = implode('</li><li>', array_map('htmlspecialchars', $errors));

        $content = "
            <div class='validation-errors'>
                <h1>Помилки валідації</h1>
                <div class='error-message'>
                    <ul><li>{$errorList}</li></ul>
                </div>
                <div class='form-actions'>
                    <a href='javascript:history.back()' class='btn btn-secondary'>Назад</a>
                    <a href='/' class='btn btn-primary'>На головну</a>
                </div>
            </div>";

        return $this->buildHtmlPage('Помилка валідації', $content);
    }

    /**
     * Обробити виняток з правильним статус кодом
     */
    protected function handleException(Exception $e): void
    {
        // Логуємо помилку
        error_log("Controller Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

        // Визначаємо тип помилки за класом винятку
        if ($e instanceof InvalidArgumentException) {
            $this->validationError([$e->getMessage()]);
        } elseif ($e instanceof UnauthorizedAccessException) {
            $this->forbidden($e->getMessage());
        } elseif ($e instanceof NotFoundException) {
            $this->notFound($e->getMessage());
        } else {
            $this->serverError('Виникла непередбачена помилка');
        }
    }

    /**
     * Try-catch wrapper для методів контролера
     */
    protected function safeExecute(callable $callback): void
    {
        try {
            $callback();
        } catch (Exception $e) {
            $this->handleException($e);
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
        $this->sendJson($response, $statusCode);
    }

    /**
     * Перевірити чи запит є AJAX
     */
    protected function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Відправити відповідь залежно від типу запиту (HTML або JSON)
     */
    protected function respondBasedOnRequest(string $successUrl, string $errorMessage, array $errors = []): void
    {
        if ($this->isAjaxRequest()) {
            if (!empty($errors)) {
                $this->sendAjaxResponse(false, $errors, $errorMessage);
            } else {
                $this->sendAjaxResponse(true, ['redirect' => $successUrl]);
            }
        } else {
            if (!empty($errors)) {
                $this->validationError($errors);
            } else {
                $this->redirect($successUrl);
            }
        }
    }
}