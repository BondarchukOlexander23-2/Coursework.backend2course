<?php

/**
 * Покращена модель відповіді на опитування з підтримкою квізів
 */
class SurveyResponse
{
    private int $id;
    private int $surveyId;
    private ?int $userId;
    private ?string $ipAddress;
    private int $totalScore; // Загальний рахунок за квіз
    private int $maxScore; // Максимально можливий рахунок

    public function __construct(
        int $surveyId,
        ?int $userId = null,
        ?string $ipAddress = null,
        int $totalScore = 0,
        int $maxScore = 0,
        int $id = 0
    ) {
        $this->id = $id;
        $this->surveyId = $surveyId;
        $this->userId = $userId;
        $this->ipAddress = $ipAddress;
        $this->totalScore = $totalScore;
        $this->maxScore = $maxScore;
    }

    /**
     * Створити нову відповідь на опитування
     */
    public static function create(
        int $surveyId,
        ?int $userId = null,
        ?string $ipAddress = null,
        int $totalScore = 0,
        int $maxScore = 0
    ): int {
        // Якщо це повторна спроба, відмічаємо дозвіл як використаний
        if ($userId && Survey::isRetakeAllowed($surveyId, $userId)) {
            Survey::useRetakePermission($surveyId, $userId);
        }

        $response = new self($surveyId, $userId, $ipAddress, $totalScore, $maxScore);

        $query = "INSERT INTO survey_responses (survey_id, user_id, ip_address, total_score, max_score) VALUES (?, ?, ?, ?, ?)";

        return Database::insert($query, [
            $response->surveyId,
            $response->userId,
            $response->ipAddress,
            $response->totalScore,
            $response->maxScore
        ]);
    }

    /**
     * Оновити рахунок відповіді
     */
    public static function updateScore(int $responseId, int $totalScore, int $maxScore): bool
    {
        $query = "UPDATE survey_responses SET total_score = ?, max_score = ? WHERE id = ?";
        return Database::execute($query, [$totalScore, $maxScore, $responseId]) > 0;
    }

    /**
     * Перевірити чи користувач вже відповідав на опитування
     */
    public static function hasUserResponded(int $surveyId, int $userId): bool
    {
        $query = "SELECT COUNT(*) as count FROM survey_responses WHERE survey_id = ? AND user_id = ?";
        $result = Database::selectOne($query, [$surveyId, $userId]);
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Отримати всі спроби користувача по опитуванню
     */
    public static function getUserAttempts(int $surveyId, int $userId): array
    {
        $query = "SELECT sr.*, 
                         CASE WHEN sr.max_score > 0 
                              THEN ROUND((sr.total_score / sr.max_score) * 100, 1) 
                              ELSE 0 END as percentage,
                         ROW_NUMBER() OVER (ORDER BY sr.created_at) as attempt_number
                  FROM survey_responses sr 
                  WHERE sr.survey_id = ? AND sr.user_id = ?
                  ORDER BY sr.created_at ASC";

        return Database::select($query, [$surveyId, $userId]);
    }
    /**
     * Знайти відповідь за ID
     */
    public static function findById(int $id): ?array
    {
        $query = "SELECT sr.*, u.name as user_name,
                         CASE WHEN sr.max_score > 0 THEN ROUND((sr.total_score / sr.max_score) * 100, 1) ELSE 0 END as percentage
                  FROM survey_responses sr 
                  LEFT JOIN users u ON sr.user_id = u.id 
                  WHERE sr.id = ?";
        return Database::selectOne($query, [$id]);
    }

    /**
     * Отримати всі відповіді на опитування з рахунками
     */
    public static function getBySurveyId(int $surveyId): array
    {
        $query = "SELECT sr.*, u.name as user_name,
                         CASE WHEN sr.max_score > 0 THEN ROUND((sr.total_score / sr.max_score) * 100, 1) ELSE 0 END as percentage
                  FROM survey_responses sr 
                  LEFT JOIN users u ON sr.user_id = u.id 
                  WHERE sr.survey_id = ? 
                  ORDER BY sr.total_score DESC, sr.created_at DESC";
        return Database::select($query, [$surveyId]);
    }

    /**
     * Отримати топ результати
     */
    public static function getTopResults(int $surveyId, int $limit = 10): array
    {
        $query = "SELECT sr.*, u.name as user_name,
                         CASE WHEN sr.max_score > 0 THEN ROUND((sr.total_score / sr.max_score) * 100, 1) ELSE 0 END as percentage
                  FROM survey_responses sr 
                  LEFT JOIN users u ON sr.user_id = u.id 
                  WHERE sr.survey_id = ? AND sr.max_score > 0
                  ORDER BY sr.total_score DESC, sr.created_at ASC
                  LIMIT ?";
        return Database::select($query, [$surveyId, $limit]);
    }

    /**
     * Отримати статистику результатів квізу
     */
    public static function getQuizStats(int $surveyId): array
    {
        $query = "SELECT 
                    COUNT(*) as total_attempts,
                    AVG(total_score) as avg_score,
                    MAX(total_score) as best_score,
                    MIN(total_score) as worst_score,
                    AVG(CASE WHEN max_score > 0 THEN (total_score / max_score) * 100 ELSE 0 END) as avg_percentage,
                    COUNT(CASE WHEN total_score = max_score THEN 1 END) as perfect_scores
                  FROM survey_responses 
                  WHERE survey_id = ? AND max_score > 0";

        $result = Database::selectOne($query, [$surveyId]);

        return [
            'total_attempts' => $result['total_attempts'] ?? 0,
            'avg_score' => round($result['avg_score'] ?? 0, 1),
            'best_score' => $result['best_score'] ?? 0,
            'worst_score' => $result['worst_score'] ?? 0,
            'avg_percentage' => round($result['avg_percentage'] ?? 0, 1),
            'perfect_scores' => $result['perfect_scores'] ?? 0
        ];
    }

    /**
     * Отримати кількість відповідей на опитування
     */
    public static function getCountBySurveyId(int $surveyId): int
    {
        $query = "SELECT COUNT(*) as count FROM survey_responses WHERE survey_id = ?";
        $result = Database::selectOne($query, [$surveyId]);
        return $result['count'] ?? 0;
    }

    /**
     * Перевірити чи користувач вже відповідав на опитування
     */


    /**
     * Видалити відповідь
     */
    public static function delete(int $id): bool
    {
        $query = "DELETE FROM survey_responses WHERE id = ?";
        return Database::execute($query, [$id]) > 0;
    }

    /**
     * Getters
     */
    public function getId(): int
    {
        return $this->id;
    }

    public function getSurveyId(): int
    {
        return $this->surveyId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getTotalScore(): int
    {
        return $this->totalScore;
    }

    public function getMaxScore(): int
    {
        return $this->maxScore;
    }

    public function getPercentage(): float
    {
        return $this->maxScore > 0 ? round(($this->totalScore / $this->maxScore) * 100, 1) : 0;
    }
}