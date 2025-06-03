<?php

/**
 * Модель конкретної відповіді на питання з підтримкою квізів
 */
class QuestionAnswer
{
    private int $id;
    private int $responseId;
    private int $questionId;
    private ?int $optionId;
    private ?string $answerText;
    private bool $isCorrect; // Чи правильна відповідь
    private int $pointsEarned; // Зароблені бали

    public function __construct(
        int $responseId,
        int $questionId,
        ?int $optionId = null,
        ?string $answerText = null,
        bool $isCorrect = false,
        int $pointsEarned = 0,
        int $id = 0
    ) {
        $this->validateAnswer($optionId, $answerText);

        $this->id = $id;
        $this->responseId = $responseId;
        $this->questionId = $questionId;
        $this->optionId = $optionId;
        $this->answerText = $answerText ? trim($answerText) : null;
        $this->isCorrect = $isCorrect;
        $this->pointsEarned = $pointsEarned;
    }

    /**
     * Валідація відповіді
     */
    private function validateAnswer(?int $optionId, ?string $answerText): void
    {
        if ($optionId === null && empty(trim($answerText ?? ''))) {
            throw new InvalidArgumentException("Answer must have either option_id or answer_text");
        }
    }

    /**
     * Створити нову відповідь на питання (для варіантів)
     */
    public static function createOptionAnswer(
        int $responseId,
        int $questionId,
        int $optionId,
        bool $isCorrect = false,
        int $pointsEarned = 0
    ): int {
        $answer = new self($responseId, $questionId, $optionId, null, $isCorrect, $pointsEarned);

        $query = "INSERT INTO question_answers (response_id, question_id, option_id, is_correct, points_earned) VALUES (?, ?, ?, ?, ?)";

        return Database::insert($query, [
            $answer->responseId,
            $answer->questionId,
            $answer->optionId,
            $answer->isCorrect ? 1 : 0,
            $answer->pointsEarned
        ]);
    }

    /**
     * Створити нову текстову відповідь
     */
    public static function createTextAnswer(
        int $responseId,
        int $questionId,
        string $answerText,
        bool $isCorrect = false,
        int $pointsEarned = 0
    ): int {
        $answer = new self($responseId, $questionId, null, $answerText, $isCorrect, $pointsEarned);

        $query = "INSERT INTO question_answers (response_id, question_id, answer_text, is_correct, points_earned) VALUES (?, ?, ?, ?, ?)";

        return Database::insert($query, [
            $answer->responseId,
            $answer->questionId,
            $answer->answerText,
            $answer->isCorrect ? 1 : 0,
            $answer->pointsEarned
        ]);
    }

    /**
     * Створити декілька відповідей на одне питання (для checkbox)
     */
    public static function createMultipleOptionAnswers(
        int $responseId,
        int $questionId,
        array $optionIds,
        bool $isCorrect = false,
        int $pointsEarned = 0
    ): array {
        $createdIds = [];

        foreach ($optionIds as $optionId) {
            if (is_numeric($optionId) && $optionId > 0) {
                $id = self::createOptionAnswer($responseId, $questionId, (int)$optionId, $isCorrect, $pointsEarned);
                $createdIds[] = $id;
            }
        }

        return $createdIds;
    }

    /**
     * Отримати всі відповіді на питання в межах відповіді на опитування
     */
    public static function getByResponseAndQuestion(int $responseId, int $questionId): array
    {
        $query = "SELECT qa.*, qo.option_text, qo.is_correct as option_is_correct
                  FROM question_answers qa 
                  LEFT JOIN question_options qo ON qa.option_id = qo.id 
                  WHERE qa.response_id = ? AND qa.question_id = ?";
        return Database::select($query, [$responseId, $questionId]);
    }

    /**
     * Отримати всі відповіді для відповіді на опитування
     */
    public static function getByResponseId(int $responseId): array
    {
        $query = "SELECT qa.*, q.question_text, q.question_type, q.points as max_points, qo.option_text, qo.is_correct as option_is_correct
                  FROM question_answers qa 
                  JOIN questions q ON qa.question_id = q.id 
                  LEFT JOIN question_options qo ON qa.option_id = qo.id 
                  WHERE qa.response_id = ? 
                  ORDER BY q.order_number ASC";
        return Database::select($query, [$responseId]);
    }

    /**
     * Отримати статистику відповідей на питання з правильністю
     */
    public static function getQuestionStats(int $questionId): array
    {
        // Статистика для варіантів відповідей
        $optionStats = Database::select(
            "SELECT qo.option_text, qo.is_correct,
                    COUNT(qa.id) as total_selected,
                    COUNT(CASE WHEN qa.is_correct = 1 THEN 1 END) as correct_selections
             FROM question_options qo 
             LEFT JOIN question_answers qa ON qo.id = qa.option_id 
             WHERE qo.question_id = ? 
             GROUP BY qo.id, qo.option_text, qo.is_correct
             ORDER BY qo.order_number",
            [$questionId]
        );

        // Текстові відповіді з правильністю
        $textAnswers = Database::select(
            "SELECT answer_text, is_correct, points_earned
             FROM question_answers 
             WHERE question_id = ? AND answer_text IS NOT NULL 
             ORDER BY created_at DESC",
            [$questionId]
        );

        // Загальна статистика правильності
        $correctnessStats = Database::selectOne(
            "SELECT 
                COUNT(*) as total_answers,
                COUNT(CASE WHEN is_correct = 1 THEN 1 END) as correct_answers,
                AVG(points_earned) as avg_points
             FROM question_answers 
             WHERE question_id = ?",
            [$questionId]
        );

        return [
            'option_stats' => $optionStats,
            'text_answers' => $textAnswers,
            'correctness' => $correctnessStats ?: [
                'total_answers' => 0,
                'correct_answers' => 0,
                'avg_points' => 0
            ]
        ];
    }

    /**
     * Отримати результат користувача по питанню
     */
    public static function getUserQuestionResult(int $responseId, int $questionId): array
    {
        $query = "SELECT 
                    SUM(points_earned) as points_earned,
                    COUNT(CASE WHEN is_correct = 1 THEN 1 END) as correct_answers,
                    COUNT(*) as total_answers
                  FROM question_answers 
                  WHERE response_id = ? AND question_id = ?";

        $result = Database::selectOne($query, [$responseId, $questionId]);

        return [
            'points_earned' => $result['points_earned'] ?? 0,
            'correct_answers' => $result['correct_answers'] ?? 0,
            'total_answers' => $result['total_answers'] ?? 0,
            'is_fully_correct' => ($result['correct_answers'] ?? 0) === ($result['total_answers'] ?? 0) && ($result['total_answers'] ?? 0) > 0
        ];
    }

    /**
     * Видалити відповідь
     */
    public static function delete(int $id): bool
    {
        $query = "DELETE FROM question_answers WHERE id = ?";
        return Database::execute($query, [$id]) > 0;
    }

    /**
     * Видалити всі відповіді на питання
     */
    public static function deleteByQuestionId(int $questionId): bool
    {
        $query = "DELETE FROM question_answers WHERE question_id = ?";
        return Database::execute($query, [$questionId]) > 0;
    }

    /**
     * Getters
     */
    public function getId(): int
    {
        return $this->id;
    }

    public function getResponseId(): int
    {
        return $this->responseId;
    }

    public function getQuestionId(): int
    {
        return $this->questionId;
    }

    public function getOptionId(): ?int
    {
        return $this->optionId;
    }

    public function getAnswerText(): ?string
    {
        return $this->answerText;
    }

    public function isCorrect(): bool
    {
        return $this->isCorrect;
    }

    public function getPointsEarned(): int
    {
        return $this->pointsEarned;
    }
}