<?php

/**
 * Клас для роботи з сесіями
 */
class Session
{
    /**
     * Початок сесії
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Перевірити чи користувач авторизований
     */
    public static function isLoggedIn(): bool
    {
        self::start();
        return isset($_SESSION['user_id']);
    }

    /**
     * Отримати ID користувача
     */
    public static function getUserId(): ?int
    {
        self::start();
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Отримати ім'я користувача
     */
    public static function getUserName(): ?string
    {
        self::start();
        return $_SESSION['user_name'] ?? null;
    }

    /**
     * Отримати email користувача
     */
    public static function getUserEmail(): ?string
    {
        self::start();
        return $_SESSION['user_email'] ?? null;
    }

    /**
     * Встановити дані користувача в сесію
     */
    public static function setUser(int $userId, string $email, string $name): void
    {
        self::start();
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $name;
    }

    /**
     * Очистити сесію
     */
    public static function destroy(): void
    {
        self::start();
        session_destroy();
    }

    /**
     * Встановити повідомлення для наступного запиту
     */
    public static function setFlashMessage(string $type, string $message): void
    {
        self::start();
        $_SESSION['flash'][$type] = $message;
    }

    /**
     * Отримати та видалити flash повідомлення
     */
    public static function getFlashMessage(string $type): ?string
    {
        self::start();
        if (isset($_SESSION['flash'][$type])) {
            $message = $_SESSION['flash'][$type];
            unset($_SESSION['flash'][$type]);
            return $message;
        }
        return null;
    }

    /**
     * Перенаправити неавторизованих користувачів
     */
    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }
}