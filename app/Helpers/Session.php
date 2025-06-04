<?php

/**
 * Клас для роботи з сесіями
 */
class Session
{
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

    public static function getUserId(): ?int
    {
        self::start();
        return $_SESSION['user_id'] ?? null;
    }

    public static function getUserName(): ?string
    {
        self::start();
        return $_SESSION['user_name'] ?? null;
    }

    public static function getUserEmail(): ?string
    {
        self::start();
        return $_SESSION['user_email'] ?? null;
    }

    /**
     * Отримати роль користувача
     */
    public static function getUserRole(): ?string
    {
        self::start();

        if (!self::isLoggedIn()) {
            return null;
        }

        // Якщо роль вже є в сесії
        if (isset($_SESSION['user_role'])) {
            return $_SESSION['user_role'];
        }

        // Отримуємо з бази даних
        $userId = self::getUserId();
        try {
            $user = Database::selectOne("SELECT role FROM users WHERE id = ?", [$userId]);
            if ($user) {
                $_SESSION['user_role'] = $user['role'];
                return $user['role'];
            }
        } catch (Exception $e) {
            error_log("Error getting user role: " . $e->getMessage());
        }

        return 'user'; // За замовчуванням
    }

    /**
     * Перевірити чи користувач адмін
     */
    public static function isAdmin(): bool
    {
        return self::getUserRole() === 'admin';
    }

    public static function setUser(int $userId, string $email, string $name, string $role = 'user'): void
    {
        self::start();
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_role'] = $role; // Додано збереження ролі
    }

    public static function destroy(): void
    {
        self::start();
        session_destroy();
    }

    public static function setFlashMessage(string $type, string $message): void
    {
        self::start();
        $_SESSION['flash'][$type] = $message;
    }

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

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }
}