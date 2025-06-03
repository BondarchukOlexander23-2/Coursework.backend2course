<?php

/**
 * Спеціальні винятки для додатку
 */

/**
 * Базовий виняток додатку
 */
abstract class AppException extends Exception
{
    protected int $httpStatusCode = 500;
    protected string $userMessage = '';

    public function __construct(string $message = '', string $userMessage = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->userMessage = $userMessage ?: $message;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }
}

/**
 * Виняток для помилок валідації
 */
class ValidationException extends AppException
{
    protected int $httpStatusCode = 422;
    private array $errors = [];

    public function __construct(array $errors, string $message = 'Помилка валідації')
    {
        $this->errors = $errors;
        parent::__construct($message, $message);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

/**
 * Виняток для неавторизованого доступу
 */
class UnauthorizedAccessException extends AppException
{
    protected int $httpStatusCode = 401;

    public function __construct(string $message = 'Необхідна авторизація')
    {
        parent::__construct($message, $message);
    }
}

/**
 * Виняток для заборонених дій
 */
class ForbiddenException extends AppException
{
    protected int $httpStatusCode = 403;

    public function __construct(string $message = 'Доступ заборонено')
    {
        parent::__construct($message, $message);
    }
}

/**
 * Виняток для не знайдених ресурсів
 */
class NotFoundException extends AppException
{
    protected int $httpStatusCode = 404;

    public function __construct(string $message = 'Ресурс не знайдено')
    {
        parent::__construct($message, $message);
    }
}

/**
 * Виняток для конфліктів даних
 */
class ConflictException extends AppException
{
    protected int $httpStatusCode = 409;

    public function __construct(string $message = 'Конфлікт даних')
    {
        parent::__construct($message, $message);
    }
}

/**
 * Виняток для помилок бази даних
 */
class DatabaseException extends AppException
{
    protected int $httpStatusCode = 500;

    public function __construct(string $message = 'Помилка бази даних', string $userMessage = 'Виникла технічна помилка')
    {
        parent::__construct($message, $userMessage);
    }
}

/**
 * Виняток для помилок бізнес-логіки
 */
class BusinessLogicException extends AppException
{
    protected int $httpStatusCode = 400;

    public function __construct(string $message)
    {
        parent::__construct($message, $message);
    }
}