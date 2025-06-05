<?php

/**
 * Покращена модель опитування
 * Відповідає принципам SOLID:
 * - Single Responsibility: відповідає тільки за роботу з опитуваннями
 * - Open/Closed: легко розширювати без зміни існуючого коду
 * - Liskov Substitution: може бути замінена похідними класами
 * - Interface Segregation: використовує тільки необхідні методи
 * - Dependency Inversion: залежить від абстракцій (Database), а не від конкретних реалізацій
 */
class Survey
{
    private int $id;
    private string $title;
    private string $description;
    private int $userId;
    private bool $isActive;

    public function __construct(
        string $title,
        string $description,
        int $userId,
        bool $isActive = true,
        int $id = 0
    ) {
        $this->validateTitle($title);
        $this->validateDescription($description);
        $this->validateUserId($userId);

        $this->id = $id;
        $this->title = trim($title);
        $this->description = trim($description);
        $this->userId = $userId;
        $this->isActive = $isActive;
    }

    /**
     * Валідація назви опитування
     */
    private function validateTitle(string $title): void
    {
        $title = trim($title);
        if (empty($title)) {
            throw new InvalidArgumentException("Survey title cannot be empty");
        }

        if (strlen($title) < 3) {
            throw new InvalidArgumentException("Survey title must be at least 3 characters long");
        }

        if (strlen($title) > 255) {
            throw new InvalidArgumentException("Survey title is too long (max 255 characters)");
        }
    }

    /**
     * Валідація опису опитування
     */
    private function validateDescription(string $description): void
    {
        if (strlen($description) > 1000) {
            throw new InvalidArgumentException("Survey description is too long (max 1000 characters)");
        }
    }

    /**
     * Валідація ID користувача
     */
    private function validateUserId(int $userId): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException("User ID must be a positive integer");
        }
    }

    /**
     * Створити нове опитування
     */
    public static function create(string $title, string $description, int $userId): int
    {
        $survey = new self($title, $description, $userId);

        $query = "INSERT INTO surveys (title, description, user_id, is_active) VALUES (?, ?, ?, ?)";

        return Database::insert($query, [
            $survey->title,
            $survey->description,
            $survey->userId,
            $survey->isActive ? 1 : 0
        ]);
    }

    /**
     * Отримати всі активні опитування з додатковою інформацією
     */
    public static function getAllActive(): array
    {
        $query = "SELECT s.*, u.name as author_name, 
                         COUNT(DISTINCT q.id) as question_count,
                         COUNT(DISTINCT sr.id) as response_count
                  FROM surveys s 
                  JOIN users u ON s.user_id = u.id 
                  LEFT JOIN questions q ON s.id = q.survey_id
                  LEFT JOIN survey_responses sr ON s.id = sr.survey_id
                  WHERE s.is_active = 1 
                  GROUP BY s.id
                  ORDER BY s.created_at DESC";

        return Database::select($query);
    }

    /**
     * Знайти опитування за ID з додатковою інформацією
     */
    public static function findById(int $id): ?array
    {
        $query = "SELECT s.*, u.name as author_name,
                         COUNT(DISTINCT q.id) as question_count,
                         COUNT(DISTINCT sr.id) as response_count
                  FROM surveys s 
                  JOIN users u ON s.user_id = u.id 
                  LEFT JOIN questions q ON s.id = q.survey_id
                  LEFT JOIN survey_responses sr ON s.id = sr.survey_id
                  WHERE s.id = ?
                  GROUP BY s.id";

        return Database::selectOne($query, [$id]);
    }

    /**
     * Отримати опитування користувача з статистикою
     */
    public static function getByUserId(int $userId): array
    {
        $query = "SELECT s.*,
                         COUNT(DISTINCT q.id) as question_count,
                         COUNT(DISTINCT sr.id) as response_count
                  FROM surveys s 
                  LEFT JOIN questions q ON s.id = q.survey_id
                  LEFT JOIN survey_responses sr ON s.id = sr.survey_id
                  WHERE s.user_id = ? 
                  GROUP BY s.id
                  ORDER BY s.created_at DESC";

        return Database::select($query, [$userId]);
    }

    /**
     * Оновити основну інформацію опитування
     */
    public static function update(int $id, string $title, string $description): bool
    {
        // Валідуємо через конструктор (з фіктивним userId)
        $survey = new self($title, $description, 1, true, $id);

        $query = "UPDATE surveys SET title = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";

        return Database::execute($query, [
                $survey->title,
                $survey->description,
                $id
            ]) > 0;
    }

    /**
     * Видалити опитування (каскадне видалення через БД)
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
        $query = "UPDATE surveys SET is_active = !is_active, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        return Database::execute($query, [$id]) > 0;
    }

    /**
     * Встановити статус активності опитування
     */
    public static function setActive(int $id, bool $isActive): bool
    {
        $query = "UPDATE surveys SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        return Database::execute($query, [$isActive ? 1 : 0, $id]) > 0;
    }

    /**
     * Отримати розширену статистику по опитуванню
     */
    public static function getDetailedStats(int $surveyId): array
    {
        // Загальна статистика
        $generalStats = Database::selectOne(
            "SELECT COUNT(DISTINCT sr.id) as total_responses,
                    COUNT(DISTINCT q.id) as total_questions,
                    COUNT(DISTINCT CASE WHEN sr.user_id IS NOT NULL THEN sr.user_id END) as registered_responses,
                    COUNT(DISTINCT CASE WHEN sr.user_id IS NULL THEN sr.id END) as anonymous_responses,
                    MIN(sr.created_at) as first_response,
                    MAX(sr.created_at) as last_response
             FROM surveys s
             LEFT JOIN questions q ON s.id = q.survey_id
             LEFT JOIN survey_responses sr ON s.id = sr.survey_id
             WHERE s.id = ?",
            [$surveyId]
        );

        // Статистика по днях (останні 30 днів)
        $dailyStats = Database::select(
            "SELECT DATE(created_at) as date, COUNT(*) as responses 
             FROM survey_responses 
             WHERE survey_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at) 
             ORDER BY date DESC",
            [$surveyId]
        );

        return [
            'general' => $generalStats ?: [
                'total_responses' => 0,
                'total_questions' => 0,
                'registered_responses' => 0,
                'anonymous_responses' => 0,
                'first_response' => null,
                'last_response' => null
            ],
            'daily' => $dailyStats
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

    /**
     * Перевірити чи опитування готове для публікації (має питання)
     */
    public static function isReadyForPublishing(int $surveyId): bool
    {
        $query = "SELECT COUNT(*) as count FROM questions WHERE survey_id = ?";
        $result = Database::selectOne($query, [$surveyId]);
        return $result && $result['count'] > 0;
    }

    /**
     * Клонувати опитування для користувача
     */
    public static function clone(int $originalSurveyId, int $newUserId, string $newTitle = ''): ?int
    {
        $originalSurvey = self::findById($originalSurveyId);
        if (!$originalSurvey) {
            return null;
        }

        $title = !empty($newTitle) ? $newTitle : "Копія: " . $originalSurvey['title'];

        try {
            // Створюємо нове опитування
            $newSurveyId = self::create($title, $originalSurvey['description'], $newUserId);

            // Копіюємо питання
            $questions = Question::getBySurveyId($originalSurveyId);
            foreach ($questions as $question) {
                $newQuestionId = Question::create(
                    $newSurveyId,
                    $question['question_text'],
                    $question['question_type'],
                    $question['is_required'],
                    $question['order_number']
                );

                // Копіюємо варіанти відповідей, якщо є
                if ($question['has_options']) {
                    $options = QuestionOption::getByQuestionId($question['id']);
                    $optionTexts = array_column($options, 'option_text');
                    QuestionOption::createMultiple($newQuestionId, $optionTexts);
                }
            }

            return $newSurveyId;
        } catch (Exception $e) {
            // Якщо щось пішло не так, видаляємо створене опитування
            if (isset($newSurveyId)) {
                self::delete($newSurveyId);
            }
            throw $e;
        }
    }

    /**
     * Пошук опитувань за назвою або описом
     */
    public static function search(string $searchTerm, bool $activeOnly = true): array
    {
        $searchTerm = '%' . trim($searchTerm) . '%';

        $whereClause = $activeOnly ? "WHERE s.is_active = 1 AND" : "WHERE";

        $query = "SELECT s.*, u.name as author_name,
                         COUNT(DISTINCT q.id) as question_count,
                         COUNT(DISTINCT sr.id) as response_count
                  FROM surveys s 
                  JOIN users u ON s.user_id = u.id 
                  LEFT JOIN questions q ON s.id = q.survey_id
                  LEFT JOIN survey_responses sr ON s.id = sr.survey_id
                  {$whereClause} (s.title LIKE ? OR s.description LIKE ?)
                  GROUP BY s.id
                  ORDER BY s.created_at DESC";

        return Database::select($query, [$searchTerm, $searchTerm]);
    }

    /**
     * Getters
     */
    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Конвертувати об'єкт в масив
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'user_id' => $this->userId,
            'is_active' => $this->isActive
        ];
    }
    /**
     * Дозволити повторне проходження опитування користувачу
     */
    public static function allowRetake(int $surveyId, int $userId): bool
    {
        $query = "INSERT INTO survey_retakes (survey_id, user_id, allowed_by, allowed_at) VALUES (?, ?, ?, NOW())";
        $allowedBy = Session::getUserId();

        try {
            Database::insert($query, [$surveyId, $userId, $allowedBy]);
            return true;
        } catch (Exception $e) {
            error_log("Error allowing retake: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Перевірити чи дозволено повторне проходження
     */
    public static function isRetakeAllowed(int $surveyId, int $userId): bool
    {
        $query = "SELECT COUNT(*) as count FROM survey_retakes 
                  WHERE survey_id = ? AND user_id = ? AND used_at IS NULL";
        $result = Database::selectOne($query, [$surveyId, $userId]);
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Використати дозвіл на повторне проходження
     */
    public static function useRetakePermission(int $surveyId, int $userId): bool
    {
        $query = "UPDATE survey_retakes 
                  SET used_at = NOW() 
                  WHERE survey_id = ? AND user_id = ? AND used_at IS NULL 
                  LIMIT 1";
        return Database::execute($query, [$surveyId, $userId]) > 0;
    }

    /**
     * Отримати список користувачів, які проходили опитування
     */
    public static function getUsersWhoCompleted(int $surveyId): array
    {
        $query = "SELECT DISTINCT sr.user_id, u.name, u.email, 
                         COUNT(sr.id) as attempts_count,
                         MAX(sr.created_at) as last_attempt,
                         MAX(sr.total_score) as best_score,
                         MAX(sr.max_score) as max_possible_score,
                         CASE WHEN rt.id IS NOT NULL THEN 1 ELSE 0 END as has_retake_permission
                  FROM survey_responses sr
                  JOIN users u ON sr.user_id = u.id
                  LEFT JOIN survey_retakes rt ON sr.survey_id = rt.survey_id 
                            AND sr.user_id = rt.user_id AND rt.used_at IS NULL
                  WHERE sr.survey_id = ? AND sr.user_id IS NOT NULL
                  GROUP BY sr.user_id, u.name, u.email, rt.id
                  ORDER BY last_attempt DESC";

        return Database::select($query, [$surveyId]);
    }

    /**
     * Отримати історію повторних спроб
     */
    public static function getRetakeHistory(int $surveyId): array
    {
        $query = "SELECT rt.*, u.name as user_name, u.email as user_email,
                         ab.name as allowed_by_name
                  FROM survey_retakes rt
                  JOIN users u ON rt.user_id = u.id
                  JOIN users ab ON rt.allowed_by = ab.id
                  WHERE rt.survey_id = ?
                  ORDER BY rt.allowed_at DESC";

        return Database::select($query, [$surveyId]);
    }
}