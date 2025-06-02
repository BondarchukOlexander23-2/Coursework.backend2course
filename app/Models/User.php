<?php

/**
 * Модель користувача
 */
class User
{
    public static function create(string $name, string $email, string $password, string $role = 'user'): int
    {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $query = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
        return Database::insert($query, [$name, $email, $hashedPassword, $role]);
    }


    public static function findByEmail(string $email): ?array
    {
        $query = "SELECT * FROM users WHERE email = ?";
        return Database::selectOne($query, [$email]);
    }

    public static function findById(int $id): ?array
    {
        $query = "SELECT * FROM users WHERE id = ?";
        return Database::selectOne($query, [$id]);
    }

    public static function emailExists(string $email): bool
    {
        $query = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $result = Database::selectOne($query, [$email]);
        return $result && $result['count'] > 0;
    }

    public static function verifyPassword(string $password, string $hashedPassword): bool
    {
        return password_verify($password, $hashedPassword);
    }

    public static function getAll(): array
    {
        $query = "SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC";
        return Database::select($query);
    }


    public static function authenticate(string $email, string $password): ?array
    {
        $user = self::findByEmail($email);

        if ($user && self::verifyPassword($password, $user['password'])) {
            unset($user['password']);
            return $user;
        }

        return null;
    }
}