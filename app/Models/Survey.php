<?php

/**
 * Модель опитування
 */
class Survey
{
    /**
     * Створити нове опитування
     */
    public static function create(string $title, string $description, int $userId): int
    {
        $query = "INSERT INTO surveys (title, description, user_id) VALUES (?, ?, ?)";
        return Database::insert($query, [$title, $description, $userId]);
    }

    /**
     * Отримати всі активні опитування
     */
    public static function getAllActive(): array
    {
        $query = "SELECT s.*, u.name as author_name 
                  FROM surveys s 
                  JOIN users u ON s.user_id = u.id 
                  WHERE s.is_active = 1 
                  ORDER BY s.created_at DESC";
        return Database::select($query);
    }

    /**
     * Знайти опитування за ID
     */
    public static function findById(int $id): ?array
    {
        $query = "SELECT s.*, u.name as author_name 
                  FROM surveys s 
                  JOIN users u ON s.user_id = u.id 
                  WHERE s.id = ?";
        return Database::selectOne($query, [$id]);
    }

    /**
     * Отримати опитування користувача
     */
    public static function getByUserId(int $userId): array
    {
        $query = "SELECT * FROM surveys WHERE user_id = ? ORDER BY created_at DESC";
        return Database::select($query, [$userId]);
    }

    /**
     * Оновити опитування
     */
    public static function update(int $id, string $title, string $description): bool
    {
        $query = "UPDATE surveys SET title = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        return Database::execute($query, [$title, $description, $id]) > 0;
    }

    /**
     * Видалити опитування
     */
    public static function delete(int $id): bool
    {
        $query = "DELETE FROM surveys WHERE id = ?";
        return Database::execute($query, [$id]) > 0;
    }

    /**
     * Активувати/деактивувати опитування
     */
    public static function toggleActive(int $id): bool
    {
        $query = "UPDATE surveys SET is_active = !is_active WHERE id = ?";
        return Database::execute($query, [$id]) > 0;
    }

    /**
     * Отримати статистику по опитуванню
     */
    public static function getStats(int $surveyId): array
    {
        $query = "SELECT COUNT(*) as total_responses FROM survey_responses WHERE survey_id = ?";
        $result = Database::selectOne($query, [$surveyId]);

        return [
            'total_responses' => $result['total_responses'] ?? 0,
            'created_at' => self::findById($surveyId)['created_at'] ?? null
        ];
    }

    /**
     * Перевірити чи користувач є автором опитування
     */
    public static function isAuthor(int $surveyId, int $userId): bool
    {
        $query = "SELECT COUNT(*) as count FROM surveys WHERE id = ? AND user_id = ?";
        $result = Database::selectOne($query, [$surveyId, $userId]);
        return $result && $result['count'] > 0;
    }
}