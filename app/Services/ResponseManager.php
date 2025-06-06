<?php

/**
 * Сервіс для управління HTTP відповідями з буферизацією
 */
class ResponseManager
{
    private array $headers = [];
    private int $statusCode = 200;
    private string $contentType = 'text/html';
    private bool $bufferingEnabled = true;

    public const STATUS_OK = 200;
    public const STATUS_CREATED = 201;
    public const STATUS_NO_CONTENT = 204;
    public const STATUS_MOVED_PERMANENTLY = 301;
    public const STATUS_FOUND = 302;
    public const STATUS_NOT_MODIFIED = 304;
    public const STATUS_BAD_REQUEST = 400;
    public const STATUS_UNAUTHORIZED = 401;
    public const STATUS_FORBIDDEN = 403;
    public const STATUS_NOT_FOUND = 404;
    public const STATUS_METHOD_NOT_ALLOWED = 405;
    public const STATUS_CONFLICT = 409;
    public const STATUS_UNPROCESSABLE_ENTITY = 422;
    public const STATUS_INTERNAL_SERVER_ERROR = 500;
    public const STATUS_SERVICE_UNAVAILABLE = 503;


    private static ?ResponseManager $instance = null;

    private function __construct() {}

    public static function getInstance(): ResponseManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function setContentType(string $contentType): self
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setSecurityHeaders(): self
    {
        $this->headers['X-Content-Type-Options'] = 'nos niff';
        $this->headers['X-Frame-Options'] = 'DENY';
        $this->headers['X-XSS-Protection'] = '1; mode=block';
        $this->headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
        return $this;
    }

    public function setCacheHeaders(int $maxAge = 3600, bool $public = true): self
    {
        $cacheControl = $public ? 'public' : 'private';
        $this->headers['Cache-Control'] = "{$cacheControl}, max-age={$maxAge}";
        $this->headers['Expires'] = gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT';
        return $this;
    }

    public function setNoCacheHeaders(): self
    {
        $this->headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
        $this->headers['Pragma'] = 'no-cache';
        $this->headers['Expires'] = '0';
        return $this;
    }

    public function disableBuffering(): self
    {
        $this->bufferingEnabled = false;
        return $this;
    }


    public function enableBuffering(): self
    {
        $this->bufferingEnabled = true;
        return $this;
    }

    /**
     * Надіслати успішну відповідь (200)
     */
    public function sendSuccess(string $content): void
    {
        $this->setStatusCode(self::STATUS_OK)
            ->setSecurityHeaders()
            ->sendResponse($content);
    }

    /**
     * Надіслати створену відповідь (201)
     */
    public function sendCreated(string $content, ?string $location = null): void
    {
        $this->setStatusCode(self::STATUS_CREATED)
            ->setSecurityHeaders();

        if ($location) {
            $this->addHeader('Location', $location);
        }

        $this->sendResponse($content);
    }

    /**
     * Надіслати редирект (302)
     */
    public function sendRedirect(string $location, bool $permanent = false): void
    {
        $statusCode = $permanent ? self::STATUS_MOVED_PERMANENTLY : self::STATUS_FOUND;

        $this->setStatusCode($statusCode)
            ->addHeader('Location', $location)
            ->setNoCacheHeaders()
            ->disableBuffering()
            ->sendResponse('');

        exit;
    }

    /**
     * Надіслати помилку клієнта (4xx)
     */
    public function sendClientError(int $statusCode, string $content): void
    {
        $this->setStatusCode($statusCode)
            ->setNoCacheHeaders()
            ->setSecurityHeaders()
            ->sendResponse($content);
    }

    /**
     * Надіслати помилку сервера (5xx)
     */
    public function sendServerError(int $statusCode, string $content): void
    {
        $this->setStatusCode($statusCode)
            ->setNoCacheHeaders()
            ->setSecurityHeaders()
            ->disableBuffering() // Для серверних помилок відразу відправляємо
            ->sendResponse($content);
    }

    /**
     * Надіслати JSON відповідь
     */
    public function sendJson(array $data, int $statusCode = self::STATUS_OK): void
    {
        $this->setStatusCode($statusCode)
            ->setContentType('application/json')
            ->setSecurityHeaders()
            ->addHeader('Access-Control-Allow-Origin', '*')
            ->sendResponse(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Надіслати CSV файл
     */
    public function sendCsv(string $content, string $filename): void
    {
        $this->setStatusCode(self::STATUS_OK)
            ->setContentType('text/csv; charset=utf-8')
            ->addHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->setNoCacheHeaders()
            ->disableBuffering()
            ->sendResponse($content);

        exit;
    }

    /**
     * Надіслати файл для завантаження
     */
    public function sendDownload(string $content, string $filename, string $mimeType = 'application/octet-stream'): void
    {
        $this->setStatusCode(self::STATUS_OK)
            ->setContentType($mimeType)
            ->addHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->addHeader('Content-Length', (string)strlen($content))
            ->setNoCacheHeaders()
            ->disableBuffering()
            ->sendResponse($content);

        exit;
    }

    /**
     * Надіслати сторінку з кешуванням
     */
    public function sendCachedPage(string $content, int $maxAge = 3600): void
    {
        $this->setStatusCode(self::STATUS_OK)
            ->setCacheHeaders($maxAge)
            ->setSecurityHeaders()
            ->sendResponse($content);
    }

    /**
     * Базовий метод для відправки відповіді
     */
    public function sendResponse(string $content): void
    {
        http_response_code($this->statusCode);

        header("Content-Type: {$this->contentType}; charset=utf-8");

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        $this->handleBufferingStrategy($content);
    }

    /**
     * Обробка стратегії буферизації залежно від статус коду
     */
    private function handleBufferingStrategy(string $content): void
    {
        if (!$this->bufferingEnabled || $this->shouldDisableBuffering()) {
            echo $content;
            if (ob_get_level()) {
                ob_end_flush();
            }
            flush();
            return;
        }

        // Використовуємо буферизацію для оптимізації
        if (!ob_get_level()) {
            ob_start();
        }

        echo $content;

        switch ($this->getStatusCodeRange()) {
            case 2:
                $this->handleSuccessResponse();
                break;

            case 3:
                $this->handleRedirectResponse();
                break;

            case 4:
                $this->handleClientErrorResponse();
                break;

            case 5:
                $this->handleServerErrorResponse();
                break;

            default:
                ob_end_flush();
        }
    }

    /**
     * Перевірити чи потрібно вимкнути буферизацію
     */
    private function shouldDisableBuffering(): bool
    {

        return $this->statusCode >= 500 ||
            strpos($this->contentType, 'application/octet-stream') !== false ||
            isset($this->headers['Content-Disposition']);
    }

    private function getStatusCodeRange(): int
    {
        return (int)floor($this->statusCode / 100);
    }

    private function handleSuccessResponse(): void
    {
        if (ob_get_length() > 4096) {
            ob_end_flush();
        } else {
            $this->compressAndSend();
        }
    }

    private function handleRedirectResponse(): void
    {
        ob_end_clean();
    }

    private function handleClientErrorResponse(): void
    {
        if ($this->statusCode >= 400) {
            $this->logClientError();
        }
        ob_end_flush();
    }

    private function handleServerErrorResponse(): void
    {
        $this->logServerError();
        ob_end_flush();

        $this->notifyAdminIfCritical();
    }

    /**
     * Стиснення та відправка контенту
     */
    private function compressAndSend(): void
    {
        if (function_exists('gzencode') &&
            strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false &&
            !headers_sent()) {

            $compressed = gzencode(ob_get_contents(), 6);
            ob_end_clean();

            header('Content-Encoding: gzip');
            header('Content-Length: ' . strlen($compressed));
            echo $compressed;
        } else {
            ob_end_flush();
        }
    }

    /**
     * Логування помилок клієнта
     */
    private function logClientError(): void
    {
        if ($this->statusCode === self::STATUS_NOT_FOUND) {
            error_log("404 Not Found: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        } elseif ($this->statusCode === self::STATUS_FORBIDDEN) {
            error_log("403 Forbidden: " . ($_SERVER['REQUEST_URI'] ?? 'unknown') .
                " IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
    }

    /**
     * Логування серверних помилок
     */
    private function logServerError(): void
    {
        error_log("Server Error {$this->statusCode}: " .
            ($_SERVER['REQUEST_URI'] ?? 'unknown') .
            " at " . date('Y-m-d H:i:s'));
    }

    private function notifyAdminIfCritical(): void
    {
        if ($this->statusCode >= 500) {
        }
    }

    public static function notFound(string $message = "Сторінка не знайдена"): void
    {
        $instance = self::getInstance();
        $instance->sendClientError(self::STATUS_NOT_FOUND, $instance->renderErrorPage(404, $message));
    }

    public static function forbidden(string $message = "Доступ заборонено"): void
    {
        $instance = self::getInstance();
        $instance->sendClientError(self::STATUS_FORBIDDEN, $instance->renderErrorPage(403, $message));
    }

    public static function serverError(string $message = "Внутрішня помилка сервера"): void
    {
        $instance = self::getInstance();
        $instance->sendServerError(self::STATUS_INTERNAL_SERVER_ERROR, $instance->renderErrorPage(500, $message));
    }

    public function renderErrorPage(int $statusCode, string $message): string
    {
        $statusTexts = [
            404 => 'Не знайдено',
            403 => 'Доступ заборонено',
            500 => 'Помилка сервера',
            422 => 'Помилка валідації',
            401 => 'Не авторизовано'
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
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Скинути стан для нового запиту
     */
    public function reset(): self
    {
        $this->headers = [];
        $this->statusCode = 200;
        $this->contentType = 'text/html';
        $this->bufferingEnabled = true;
        return $this;
    }
}