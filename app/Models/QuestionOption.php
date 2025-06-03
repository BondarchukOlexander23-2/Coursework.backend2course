<?php

/**
 * Покращена модель варіантів відповідей з підтримкою правильних відповідей
 * Відповідає принципу Single Responsibility
 */
class QuestionOption
{
    private int $id;
    private int $questionId;
    private string $optionText;
    private int $orderNumber;
    private bool $isCorrect; // Нове поле для позначення правильної відповіді

    public function __construct(
        int $questionId,
        string $optionText,
        int $orderNumber = 1,
        bool $isCorrect = false,
        int $id = 0
    ) {
        $this->validateOptionText($optionText);

        $this->id = $id;
        $this->questionId = $questionId;
        $this->optionText = trim($optionText);
        $this->orderNumber = $orderNumber;
        $this->isCorrect = $isCorrect;
    }

    /**
     * Валідація тексту варіанту
     */
    private function validateOptionText(string $text): void
    {
        if (empty(trim($text))) {
            throw new InvalidArgumentException("Option text cannot be empty");
        }

        if (strlen(trim($text)) > 255) {
            throw new InvalidArgumentException("Option text is too long (max 255 characters)");
        }
    }

    /**
     * Створити новий варіант відповіді
     */
    public static function create(
        int $questionId,
        string $optionText,
        int $orderNumber = 1,
        bool $isCorrect = false
    ): int {
        $option = new self($questionId, $optionText, $orderNumber, $isCorrect);

        $query = "INSERT INTO question_options (question_id, option_text, order_number, is_correct) VALUES (?, ?, ?, ?)";

        return Database::insert($query, [
            $option->questionId,
            $option->optionText,
            $option->orderNumber,
            $option->isCorrect ? 1 : 0
        ]);
    }

    /**
     * Створити декілька варіантів одночасно з позначенням правильних
     */
    public static function createMultiple(int $questionId, array $options): array
    {
        $createdIds = [];
        $orderNumber = 1;

        foreach ($options as $optionData) {
            if (is_string($optionData)) {
                // Старий формат - тільки текст
                $optionText = $optionData;
                $isCorrect = false;
            } elseif (is_array($optionData)) {
                // Новий формат - масив з текстом і позначкою правильності
                $optionText = $optionData['text'] ?? '';
                $isCorrect = $optionData['is_correct'] ?? false;
            } else {
                continue;
            }

            if (!empty(trim($optionText))) {
                $id = self::create($questionId, $optionText, $orderNumber, $isCorrect);
                $createdIds[] = $id;
                $orderNumber++;
            }
        }

        return $createdIds;
    }

    /**
     * Отримати всі варіанти питання
     */
    public static function getByQuestionId(int $questionId): array
    {
        $query = "SELECT * FROM question_options WHERE question_id = ? ORDER BY order_number ASC";
        $rows = Database::select($query, [$questionId]);

        $options = [];
        foreach ($rows as $row) {
            $option = new self(
                $row['question_id'],
                $row['option_text'],
                $row['order_number'],
                (bool)$row['is_correct'],
                $row['id']
            );
            $options[] = $option->toArray();
        }

        return $options;
    }

    /**
     * Отримати тільки правильні відповіді для питання
     */
    public static function getCorrectByQuestionId(int $questionId): array
    {
        $query = "SELECT * FROM question_options WHERE question_id = ? AND is_correct = 1 ORDER BY order_number ASC";
        $rows = Database::select($query, [$questionId]);

        $options = [];
        foreach ($rows as $row) {
            $option = new self(
                $row['question_id'],
                $row['option_text'],
                $row['order_number'],
                true,
                $row['id']
            );
            $options[] = $option->toArray();
        }

        return $options;
    }

    /**
     * Знайти варіант за ID
     */
    public static function findById(int $id): ?array
    {
        $query = "SELECT * FROM question_options WHERE id = ?";
        $row = Database::selectOne($query, [$id]);

        if (!$row) {
            return null;
        }

        $option = new self(
            $row['question_id'],
            $row['option_text'],
            $row['order_number'],
            (bool)$row['is_correct'],
            $row['id']
        );

        return $option->toArray();
    }

    /**
     * Оновити варіант відповіді
     */
    public static function update(int $id, string $optionText, int $orderNumber, bool $isCorrect = false): bool
    {
        // Валідуємо через конструктор
        $option = new self(0, $optionText, $orderNumber, $isCorrect, $id);

        $query = "UPDATE question_options SET option_text = ?, order_number = ?, is_correct = ? WHERE id = ?";

        return Database::execute($query, [
                $optionText,
                $orderNumber,
                $isCorrect ? 1 : 0,
                $id
            ]) > 0;
    }

    /**
     * Встановити правильність варіанту відповіді
     */
    public static function setCorrect(int $id, bool $isCorrect): bool
    {
        $query = "UPDATE question_options SET is_correct = ? WHERE id = ?";
        return Database::execute($query, [$isCorrect ? 1 : 0, $id]) > 0;
    }

    /**
     * Встановити правильні відповіді для питання (скидає попередні)
     */
    public static function setCorrectAnswers(int $questionId, array $correctOptionIds): bool
    {
        try {
            // Спочатку скидаємо всі правильні відповіді
            $resetQuery = "UPDATE question_options SET is_correct = 0 WHERE question_id = ?";
            Database::execute($resetQuery, [$questionId]);

            // Потім встановлюємо правильні
            if (!empty($correctOptionIds)) {
                $placeholders = str_repeat('?,', count($correctOptionIds) - 1) . '?';
                $setQuery = "UPDATE question_options SET is_correct = 1 WHERE question_id = ? AND id IN ({$placeholders})";
                $params = array_merge([$questionId], $correctOptionIds);
                Database::execute($setQuery, $params);
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Видалити варіант відповіді
     */
    public static function delete(int $id): bool
    {
        $query = "DELETE FROM question_options WHERE id = ?";
        return Database::execute($query, [$id]) > 0;
    }

    /**
     * Видалити всі варіанти питання
     */
    public static function deleteByQuestionId(int $questionId): bool
    {
        $query = "DELETE FROM question_options WHERE question_id = ?";
        return Database::execute($query, [$questionId]) > 0;
    }

    /**
     * Замінити всі варіанти питання новими
     */
    public static function replaceForQuestion(int $questionId, array $newOptions): array
    {
        // Видаляємо старі варіанти
        self::deleteByQuestionId($questionId);

        // Створюємо нові
        return self::createMultiple($questionId, $newOptions);
    }

    /**
     * Отримати наступний номер порядку для питання
     */
    public static function getNextOrderNumber(int $questionId): int
    {
        $query = "SELECT COALESCE(MAX(order_number), 0) + 1 as next_order FROM question_options WHERE question_id = ?";
        $result = Database::selectOne($query, [$questionId]);
        return $result['next_order'] ?? 1;
    }

    /**
     * Перевірити чи є правильні відповіді для питання
     */
    public static function hasCorrectAnswers(int $questionId): bool
    {
        $query = "SELECT COUNT(*) as count FROM question_options WHERE question_id = ? AND is_correct = 1";
        $result = Database::selectOne($query, [$questionId]);
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Отримати кількість правильних відповідей
     */
    public static function getCorrectAnswersCount(int $questionId): int
    {
        $query = "SELECT COUNT(*) as count FROM question_options WHERE question_id = ? AND is_correct = 1";
        $result = Database::selectOne($query, [$questionId]);
        return $result['count'] ?? 0;
    }

    /**
     * Getters
     */
    public function getId(): int
    {
        return $this->id;
    }

    public function getQuestionId(): int
    {
        return $this->questionId;
    }

    public function getOptionText(): string
    {
        return $this->optionText;
    }

    public function getOrderNumber(): int
    {
        return $this->orderNumber;
    }

    public function isCorrect(): bool
    {
        return $this->isCorrect;
    }

    /**
     * Конвертувати об'єкт в масив
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'question_id' => $this->questionId,
            'option_text' => $this->optionText,
            'order_number' => $this->orderNumber,
            'is_correct' => $this->isCorrect
        ];
    }
}