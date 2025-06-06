<?php

/**
 * Модель користувача з підтримкою адміністрування
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

    public static function authenticate(string $email, string $password): ?array
    {
        $user = self::findByEmail($email);

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            return $user;
        }

        return null;
    }


    /**
     * Оновити роль користувача
     */
    public static function updateRole(int $userId, string $role): bool
    {
        $validRoles = ['user', 'admin'];
        if (!in_array($role, $validRoles)) {
            throw new InvalidArgumentException("Invalid role: {$role}");
        }

        $query = "UPDATE users SET role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        return Database::execute($query, [$role, $userId]) > 0;
    }

    /**
     * Видалити користувача
     */
    public static function delete(int $userId): bool
    {
        $query = "DELETE FROM users WHERE id = ?";
        return Database::execute($query, [$userId]) > 0;
    }

    /**
     * Отримати користувачів з пагінацією та пошуком
     */
    public static function getWithPagination(int $page = 1, int $limit = 20, string $search = ''): array
    {
        $offset = ($page - 1) * $limit;
        $searchTerm = '%' . $search . '%';

        $query = "SELECT u.*, 
                         COUNT(DISTINCT s.id) as surveys_count,
                         COUNT(DISTINCT sr.id) as responses_count
                  FROM users u 
                  LEFT JOIN surveys s ON u.id = s.user_id
                  LEFT JOIN survey_responses sr ON u.id = sr.user_id
                  WHERE u.name LIKE ? OR u.email LIKE ?
                  GROUP BY u.id
                  ORDER BY u.created_at DESC
                  LIMIT ? OFFSET ?";

        return Database::select($query, [$searchTerm, $searchTerm, $limit, $offset]);
    }

    /**
     * Отримати кількість користувачів
     */
    public static function getTotalCount(string $search = ''): int
    {
        $searchTerm = '%' . $search . '%';

        $query = "SELECT COUNT(*) as count FROM users WHERE name LIKE ? OR email LIKE ?";
        $result = Database::selectOne($query, [$searchTerm, $searchTerm]);

        return $result['count'] ?? 0;
    }

    /**
     * Отримати кількість адміністраторів
     */
    public static function getAdminCount(): int
    {
        $query = "SELECT COUNT(*) as count FROM users WHERE role = 'admin'";
        $result = Database::selectOne($query);
        return $result['count'] ?? 0;
    }

    /**
     * Перевірити чи користувач є адміном
     */
    public static function isAdmin(int $userId): bool
    {
        $query = "SELECT role FROM users WHERE id = ?";
        $result = Database::selectOne($query, [$userId]);
        return $result && $result['role'] === 'admin';
    }

    /**
     * Перевірити чи може бути видалений користувач
     */
    public static function canBeDeleted(int $userId): array
    {
        $errors = [];
        $user = self::findById($userId);
        if ($user && $user['role'] === 'admin' && self::getAdminCount() <= 1) {
            $errors[] = 'Неможливо видалити останнього адміністратора';
        }

        return $errors;
    }
}