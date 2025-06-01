<?php

/**
 * Модель користувача
 */
class User
{
    /**
     * Створити нового користувача
     */
    public static function create(string $name, string $email, string $password): int
    {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $query = "INSERT INTO users (name, email, password) VALUES (?, ?, ?)";
        return Database::insert($query, [$name, $email, $hashedPassword]);
    }

    /**
     * Знайти користувача за email
     */
    public static function findByEmail(string $email): ?array
    {
        $query = "SELECT * FROM users WHERE email = ?";
        return Database::selectOne($query, [$email]);
    }

    /**
     * Знайти користувача за ID
     */
    public static function findById(int $id): ?array
    {
        $query = "SELECT * FROM users WHERE id = ?";
        return Database::selectOne($query, [$id]);
    }

    /**
     * Перевірити чи існує користувач з таким email
     */
    public static function emailExists(string $email): bool
    {
        $query = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $result = Database::selectOne($query, [$email]);
        return $result && $result['count'] > 0;
    }

    /**
     * Перевірити правильність паролю
     */
    public static function verifyPassword(string $password, string $hashedPassword): bool
    {
        return password_verify($password, $hashedPassword);
    }

    /**
     * Отримати всіх користувачів
     */
    public static function getAll(): array
    {
        $query = "SELECT id, name, email, created_at FROM users ORDER BY created_at DESC";
        return Database::select($query);
    }

    /**
     * Аутентифікація користувача
     */
    public static function authenticate(string $email, string $password): ?array
    {
        $user = self::findByEmail($email);

        if ($user && self::verifyPassword($password, $user['password'])) {
            // Не повертаємо пароль в результаті
            unset($user['password']);
            return $user;
        }

        return null;
    }
}